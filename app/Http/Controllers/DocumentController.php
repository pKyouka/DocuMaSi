<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $categories = Category::where('is_active', true)->orderBy('name')->get();

        $query = Document::with(['category', 'creator', 'folder'])
            ->active()
            ->when($request->search, fn($q, $s) => $q->search($s))
            ->filter($request->only([
                'category_id', 'academic_year', 'semester',
                'status', 'pic', 'department', 'date_from', 'date_to'
            ]));

        // If user is Admin (Biro/Jurusan), only view their unit's documents
        if ($user && $user->isAdmin() && !$user->isSuperAdmin() && $user->department) {
            $query->where('department', $user->department);
        }

        $documents = $query->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString();

        $academicYears = Document::distinct()->whereNotNull('academic_year')->pluck('academic_year')->sort()->values();

        return view('documents.index', compact('documents', 'categories', 'academicYears'));
    }

    public function create(Request $request)
    {
        $this->authorize('upload', Document::class);
        $user = auth()->user();

        $categories = Category::where('is_active', true)->orderBy('name')->get();
        
        if ($user->isSuperAdmin()) {
            $folders = Folder::orderBy('name')->get();
        } else {
            $folders = Folder::accessible($user)->orderBy('name')->get();
        }

        $selectedFolderId = $request->get('folder_id');

        return view('documents.create', compact('categories', 'folders', 'selectedFolderId'));
    }

    public function store(Request $request)
    {
        $this->authorize('upload', Document::class);
        $user = auth()->user();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'document_number' => 'nullable|string|max:100',
            'category_id' => 'required|exists:categories,id',
            'folder_id' => 'nullable|exists:folders,id',
            'department' => 'nullable|string|max:255',
            'academic_year' => 'nullable|string|max:20',
            'semester' => 'nullable|string|max:20',
            'course_name' => 'nullable|string|max:255',
            'pic' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:2000',
            'tags' => 'nullable|string',
            'display_date' => 'required|date',
            'visibility' => 'required|in:viewer,internal,private',
            'is_downloadable' => 'nullable|boolean',
            'file' => 'required|file|max:20480|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,jpg,jpeg,png,zip',
        ]);

        // Validate folder permission
        if (!empty($validated['folder_id'])) {
            $targetFolder = Folder::findOrFail($validated['folder_id']);
            abort_unless($targetFolder->canUploadTo($user), 403, 'Akses ditolak: Anda tidak memiliki izin mengunggah berkas ke folder ini.');
            if (empty($validated['department'])) {
                $validated['department'] = $targetFolder->getEffectiveDepartment();
            }
        }

        if (empty($validated['department']) && $user->department) {
            $validated['department'] = $user->department;
        }

        $file = $request->file('file');
        $originalFilename = $file->getClientOriginalName();
        $filePath = $file->store('documents/' . date('Y/m'), 'private');

        $tags = null;
        if (!empty($validated['tags'])) {
            $tags = array_map('trim', explode(',', $validated['tags']));
        }

        // Logic approval:
        // Jika user biasa: status = submitted (menunggu verifikasi admin prodi).
        // Jika admin prodi / superadmin / admin unit: langsung ACC (approved).
        $isUserUploader = $user->isUser();
        $status = $isUserUploader ? Document::STATUS_SUBMITTED : Document::STATUS_APPROVED;
        $prodiStatus = null;
        if (!$isUserUploader && ($user->isAdminProdi() || $user->isSuperAdmin())) {
            $prodiStatus = 'approved';
        }
        if (!empty($validated['folder_id'])) {
            $targetFolder = Folder::find($validated['folder_id']);
            if ($targetFolder) {
                $isFolderSharedToProdi = in_array('Program Studi Teknologi Informasi', $targetFolder->getEffectiveSharedDepartments()) || in_array('PSTI', $targetFolder->getEffectiveSharedDepartments());
                if ($isFolderSharedToProdi && !in_array($validated['department'], ['Program Studi Teknologi Informasi', 'PSTI', 'Program Studi PSTI'])) {
                    $prodiStatus = 'pending';
                }
            }
        }

        $document = Document::create([
            'uuid' => (string) Str::uuid(),
            'name' => $validated['name'],
            'document_number' => $validated['document_number'] ?? null,
            'category_id' => $validated['category_id'],
            'folder_id' => $validated['folder_id'] ?? null,
            'department' => $validated['department'],
            'academic_year' => $validated['academic_year'] ?? null,
            'semester' => $validated['semester'] ?? null,
            'course_name' => $validated['course_name'] ?? null,
            'pic' => $validated['pic'] ?? null,
            'description' => $validated['description'] ?? null,
            'tags' => $tags,
            'document_date' => $validated['display_date'],
            'display_date' => $validated['display_date'],
            'upload_date' => now()->toDateString(),
            'original_uploaded_at' => now(),
            'actual_uploaded_at' => now(),
            'status' => $status,
            'prodi_approval_status' => $prodiStatus,
            'visibility' => $validated['visibility'],
            'is_downloadable' => $request->boolean('is_downloadable', true),
            'current_version' => 1,
            'created_by' => $user->id,
            'approved_by' => $isUserUploader ? null : $user->id,
            'approved_at' => $isUserUploader ? null : now(),
        ]);

        DocumentVersion::create([
            'document_id' => $document->id,
            'version_number' => 1,
            'file_path' => $filePath,
            'original_filename' => $originalFilename,
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'uploaded_by' => $user->id,
            'notes' => 'Upload awal',
        ]);

        if ($isUserUploader) {
            \App\Models\DocumentApproval::create([
                'document_id' => $document->id,
                'user_id' => $user->id,
                'status' => 'submitted',
                'notes' => 'Dokumen diunggah oleh pengguna dan menunggu verifikasi Admin Prodi.',
            ]);
        }

        AuditLog::log(
            'document_created',
            "Dokumen '{$document->name}' berhasil dibuat" . ($isUserUploader ? " (menunggu verifikasi Admin Prodi)." : " dengan visibilitas {$document->visibility_label}."),
            Document::class,
            $document->id,
        );

        $flashMsg = $isUserUploader
            ? 'Dokumen berhasil diunggah dan sedang menunggu verifikasi Admin Prodi.'
            : 'Dokumen berhasil ditambahkan.';

        if (!empty($document->folder_id)) {
            return redirect()->route('folders.index', ['folder_id' => $document->folder_id])
                ->with('success', $flashMsg);
        }

        return redirect()->route('documents.show', $document)
            ->with('success', $flashMsg);
    }

    public function show(Document $document)
    {
        $user = auth()->user();
        abort_unless($document->canAccess($user), 403, 'Unit Anda tidak memiliki izin akses ke dokumen ini.');

        $document->load(['category', 'folder', 'creator', 'approver', 'versions.uploader', 'approvals.user']);

        return view('documents.show', compact('document'));
    }

    public function edit(Document $document)
    {
        $this->authorize('update', $document);
        $user = auth()->user();

        $categories = Category::where('is_active', true)->orderBy('name')->get();
        if ($user->isSuperAdmin()) {
            $folders = Folder::orderBy('name')->get();
        } else {
            $folders = Folder::accessible($user)->orderBy('name')->get();
        }

        $auditLogs = AuditLog::where('model_type', Document::class)
            ->where('model_id', $document->id)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return view('documents.edit', compact('document', 'categories', 'folders', 'auditLogs'));
    }

    public function update(Request $request, Document $document)
    {
        $this->authorize('update', $document);
        $user = auth()->user();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'document_number' => 'nullable|string|max:100',
            'category_id' => 'required|exists:categories,id',
            'folder_id' => 'nullable|exists:folders,id',
            'department' => 'nullable|string|max:255',
            'academic_year' => 'nullable|string|max:20',
            'semester' => 'nullable|string|max:20',
            'course_name' => 'nullable|string|max:255',
            'pic' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:2000',
            'tags' => 'nullable|string',
            'display_date' => 'required|date',
            'visibility' => 'required|in:viewer,internal,private',
            'is_downloadable' => 'nullable|boolean',
            'status' => 'required|in:draft,submitted,review,revision,approved,archived',
        ]);

        if (!empty($validated['folder_id'])) {
            $targetFolder = Folder::findOrFail($validated['folder_id']);
            abort_unless($targetFolder->canAccess($user), 403, 'Akses ke folder tujuan ditolak.');
        }

        $oldValues = $document->only(['name', 'document_number', 'category_id', 'folder_id', 'display_date', 'visibility', 'status']);

        $tags = null;
        if (!empty($validated['tags'])) {
            $tags = array_map('trim', explode(',', $validated['tags']));
        }

        $document->update([
            'name' => $validated['name'],
            'document_number' => $validated['document_number'] ?? null,
            'category_id' => $validated['category_id'],
            'folder_id' => $validated['folder_id'] ?? null,
            'department' => $validated['department'],
            'academic_year' => $validated['academic_year'] ?? null,
            'semester' => $validated['semester'] ?? null,
            'course_name' => $validated['course_name'] ?? null,
            'pic' => $validated['pic'] ?? null,
            'description' => $validated['description'] ?? null,
            'tags' => $tags,
            'display_date' => $validated['display_date'],
            'document_date' => $validated['display_date'],
            'visibility' => $validated['visibility'],
            'is_downloadable' => $request->boolean('is_downloadable', true),
            'status' => $validated['status'],
        ]);

        AuditLog::log(
            'document_updated',
            "Dokumen '{$document->name}' diperbarui.",
            Document::class,
            $document->id,
            $oldValues,
            $document->only(['name', 'document_number', 'category_id', 'folder_id', 'display_date', 'visibility', 'status']),
        );

        return redirect()->route('documents.show', $document)
            ->with('success', 'Dokumen berhasil diperbarui.');
    }

    public function destroy(Document $document)
    {
        $this->authorize('delete', $document);

        AuditLog::log(
            'document_deleted',
            "Dokumen '{$document->name}' dihapus.",
            Document::class,
            $document->id,
        );

        $document->delete();

        return redirect()->route('documents.index')
            ->with('success', 'Dokumen berhasil dihapus.');
    }

    public function download(Document $document, ?DocumentVersion $version = null)
    {
        $user = auth()->user();
        abort_unless($document->canAccess($user), 403, 'Unit Anda tidak memiliki izin akses ke dokumen ini.');

        if (!$document->is_downloadable && $user && $user->isUser()) {
            return back()->with('error', 'Dokumen ini tidak diizinkan untuk diunduh (hanya pratinjau).');
        }

        $ver = $version ?? $document->latestVersion;
        abort_unless($ver && $ver->document_id === $document->id, 404);

        if (!Storage::disk('private')->exists($ver->file_path)) {
            return back()->with('error', 'File tidak ditemukan di penyimpanan server.');
        }

        AuditLog::log(
            'document_downloaded',
            "Dokumen '{$document->name}' v{$ver->version_number} diunduh.",
            Document::class,
            $document->id,
        );

        return Storage::disk('private')->download($ver->file_path, $ver->original_filename);
    }

    public function preview(Document $document, ?DocumentVersion $version = null)
    {
        $user = auth()->user();
        abort_unless($document->canAccess($user), 403, 'Unit Anda tidak memiliki izin akses ke dokumen ini.');

        $ver = $version ?? $document->latestVersion;
        abort_unless($ver && $ver->document_id === $document->id, 404);

        if (!Storage::disk('private')->exists($ver->file_path)) {
            abort(404, 'File tidak ditemukan di penyimpanan server.');
        }

        AuditLog::log(
            'document_previewed',
            "Dokumen '{$document->name}' v{$ver->version_number} dilihat pratinjau.",
            Document::class,
            $document->id,
        );

        return Storage::disk('private')->response($ver->file_path, $ver->original_filename, [
            'Content-Type' => $ver->mime_type,
            'Content-Disposition' => 'inline; filename="' . $ver->original_filename . '"',
        ]);
    }

    public function updateSharing(Request $request, $document)
    {
        $user = auth()->user();
        $doc = $document instanceof Document ? $document : Document::where('uuid', $document)->orWhere('id', $document)->firstOrFail();

        $isOwnerDept = $doc->department && $user->matchesDepartment($doc->department);

        abort_unless($user && ($user->isSuperAdmin() || $doc->created_by === $user->id || $isOwnerDept), 403, 'Anda tidak memiliki hak untuk mengatur pembagian dokumen ini.');

        $validated = $request->validate([
            'shared_departments' => 'nullable|array',
            'shared_departments.*' => 'string|in:' . implode(',', array_merge(User::UNITS, ['PSTI'])),
        ]);

        $sharedDepts = $validated['shared_departments'] ?? [];
        $isSharedToProdi = in_array('Program Studi Teknologi Informasi', $sharedDepts) || in_array('PSTI', $sharedDepts);
        $isFromOutsideProdi = !in_array($doc->department, ['Program Studi Teknologi Informasi', 'PSTI', 'Program Studi PSTI']);

        $updateData = ['shared_departments' => $sharedDepts];
        if ($isFromOutsideProdi && $isSharedToProdi) {
            if ($doc->prodi_approval_status !== 'approved') {
                $updateData['prodi_approval_status'] = 'pending';
            }
        } elseif ($isFromOutsideProdi && !$isSharedToProdi) {
            $updateData['prodi_approval_status'] = null;
        }

        $doc->update($updateData);

        AuditLog::log(
            'document_shared',
            "Izin akses dokumen '{$doc->name}' diperbarui ke: " . (empty($sharedDepts) ? 'Hanya unit pemilik' : implode(', ', $sharedDepts)),
            Document::class,
            $doc->id
        );

        return response()->json([
            'success' => true,
            'message' => 'Izin akses dokumen berhasil disimpan.',
            'shared_departments' => $sharedDepts,
        ]);
    }

    public function updateDisplayDate(Request $request, $document)
    {
        $user = auth()->user();
        $doc = $document instanceof Document ? $document : Document::where('uuid', $document)->orWhere('id', $document)->firstOrFail();

        $this->authorize('update', $doc);

        $validated = $request->validate([
            'display_date' => 'required|date',
        ]);

        $oldDate = $doc->effective_display_date ? $doc->effective_display_date->format('Y-m-d') : '-';
        $newDate = $validated['display_date'];

        $doc->update([
            'display_date' => $newDate,
            'document_date' => $newDate,
        ]);

        AuditLog::log(
            'display_date_changed',
            "Tanggal tampil dokumen '{$doc->name}' diubah dari '{$oldDate}' menjadi '{$newDate}'",
            Document::class,
            $doc->id
        );

        return response()->json([
            'success' => true,
            'message' => 'Tanggal tampil dokumen berhasil diperbarui.',
            'display_date' => $doc->effective_display_date->format('Y-m-d'),
            'display_date_formatted' => $doc->effective_display_date->format('d F Y'),
        ]);
    }

    public function uploadVersion(Request $request, Document $document)
    {
        $this->authorize('update', $document);

        $request->validate([
            'file' => 'required|file|max:20480|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,jpg,jpeg,png,zip',
            'notes' => 'nullable|string|max:1000',
        ]);

        $file = $request->file('file');
        $filePath = $file->store('documents/' . date('Y/m'), 'private');
        $newVersion = $document->current_version + 1;

        DocumentVersion::create([
            'document_id' => $document->id,
            'version_number' => $newVersion,
            'file_path' => $filePath,
            'original_filename' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'uploaded_by' => auth()->id(),
            'notes' => $request->notes,
        ]);

        $document->update(['current_version' => $newVersion]);

        AuditLog::log(
            'version_uploaded',
            "Versi {$newVersion} dokumen '{$document->name}' diunggah.",
            Document::class,
            $document->id,
        );

        return back()->with('success', "Versi {$newVersion} berhasil diunggah.");
    }

    public function submit(Document $document)
    {
        if (!$document->isSubmittable()) {
            return back()->with('error', 'Dokumen tidak dapat diajukan.');
        }

        $document->update(['status' => Document::STATUS_SUBMITTED]);

        AuditLog::log(
            'document_submitted',
            "Dokumen '{$document->name}' diajukan untuk review.",
            Document::class,
            $document->id,
        );

        return back()->with('success', 'Dokumen berhasil diajukan untuk review.');
    }

    public function archive(Document $document)
    {
        $this->authorize('update', $document);

        $document->update([
            'status' => Document::STATUS_ARCHIVED,
            'archived_at' => now(),
        ]);

        AuditLog::log(
            'document_archived',
            "Dokumen '{$document->name}' diarsipkan.",
            Document::class,
            $document->id,
        );

        return back()->with('success', 'Dokumen berhasil diarsipkan.');
    }
}
