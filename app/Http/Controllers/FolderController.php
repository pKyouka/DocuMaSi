<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Http\Request;

class FolderController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $folderId = $request->get('folder_id');

        $currentFolder = null;
        $breadcrumbs = [];

        if ($folderId) {
            $currentFolder = Folder::with(['parent', 'children'])->findOrFail($folderId);
            abort_unless($currentFolder->canAccess($user), 403, 'Unit Anda tidak memiliki izin akses ke folder ini.');
            $breadcrumbs = $currentFolder->getBreadcrumbs();

            if ($user && !$user->isSuperAdmin() && $currentFolder->isSharedFromOtherDepartment($user)) {
                $subfolders = $currentFolder->getAccessibleChildren($user);
            } else {
                $subfolders = $currentFolder->children()->orderBy('name')->get();
            }

            $documentsQuery = $currentFolder->documents()->with(['category', 'latestVersion', 'creator']);

            // If user is from another department and the folder itself was not directly shared (only specific files shared)
            if ($user && !$user->isSuperAdmin() && $currentFolder->isSharedFromOtherDepartment($user)) {
                if (!$currentFolder->isDirectlyAccessibleBy($user)) {
                    // Only show documents that are explicitly shared to user's department
                    $aliases = $user->getDepartmentAliases();
                    $documentsQuery->where(function ($q) use ($aliases, $user) {
                        $q->where('created_by', $user->id);
                        foreach ($aliases as $alias) {
                            $q->orWhere('shared_departments', 'like', '%"' . $alias . '"%');
                        }
                    });
                }
            }

            // If guest or viewer, only show published documents
            if (!$user || $user->isUser()) {
                $documentsQuery->forViewer();
            }
        } else {
            // Root View
            if (!$user || $user->isSuperAdmin()) {
                $subfolders = Folder::whereNull('parent_id')->orderBy('name')->get();
                $documentsQuery = Document::whereNull('folder_id')->with(['category', 'latestVersion', 'creator']);
            } else {
                $allRootFolders = Folder::whereNull('parent_id')
                    ->with('children')
                    ->orderBy('name')
                    ->get();

                $subfolders = $allRootFolders->filter(function ($rf) use ($user) {
                    return $rf->canAccess($user);
                })->values();

                $aliases = $user->getDepartmentAliases();
                $documentsQuery = Document::whereNull('folder_id')
                    ->with(['category', 'latestVersion', 'creator'])
                    ->where(function ($q) use ($aliases, $user) {
                        $q->where(function ($dq) use ($aliases) {
                            foreach ($aliases as $alias) {
                                $dq->orWhere('department', $alias);
                            }
                        })
                        ->orWhere(function ($sq) use ($aliases) {
                            foreach ($aliases as $alias) {
                                $sq->orWhere('shared_departments', 'like', '%"' . $alias . '"%');
                            }
                        });
                    });
            }

            if (!$user || $user->isUser()) {
                $documentsQuery->forViewer();
            }
        }

        // Filter / Search inside folder
        if ($request->filled('search')) {
            $s = $request->search;
            $documentsQuery->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('document_number', 'like', "%{$s}%")
                  ->orWhere('description', 'like', "%{$s}%");
            });
        }

        $documents = $documentsQuery->orderBy('display_date', 'desc')->paginate(24)->withQueryString();
        $categories = Category::where('is_active', true)->orderBy('name')->get();

        // 1. Folder Tree Hierarchy for Left Navigation Pane (Explorer Style)
        if (!$user || $user->isSuperAdmin()) {
            $folderTree = Folder::whereNull('parent_id')
                ->with(['children' => function ($q) {
                    $q->orderBy('name')->with('children.children');
                }])
                ->orderBy('name')
                ->get();
        } else {
            $allRoots = Folder::whereNull('parent_id')
                ->with(['children' => function ($q) {
                    $q->orderBy('name')->with('children.children');
                }])
                ->orderBy('name')
                ->get();

            $folderTree = $allRoots->filter(function ($rf) use ($user) {
                return $rf->canAccess($user);
            })->values();
        }

        // 2. Get all accessible folders for "Move Document" dropdown
        $allAccessibleFolders = collect();
        if ($user) {
            if ($user->isSuperAdmin()) {
                $allAccessibleFolders = Folder::orderBy('name')->get();
            } else {
                $allAccessibleFolders = Folder::orderBy('name')->get()->filter(function ($f) use ($user) {
                    return $f->canManage($user);
                })->values();
            }
        }

        return view('folders.index', compact(
            'currentFolder',
            'breadcrumbs',
            'subfolders',
            'documents',
            'categories',
            'folderTree',
            'allAccessibleFolders'
        ));
    }

    public function store(Request $request)
    {
        $user = auth()->user();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:folders,id',
            'department' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
        ]);

        $department = $validated['department'] ?? null;

        if (!empty($validated['parent_id'])) {
            $parent = Folder::findOrFail($validated['parent_id']);
            abort_unless($parent->canManage($user), 403, 'Akses ditolak: Hanya unit pemilik yang dapat membuat subfolder di folder ini.');
            $department = $department ?: $parent->getEffectiveDepartment();
        } else {
            if (!$user->isSuperAdmin()) {
                $department = $user->department;
            }
        }

        $existing = Folder::where('name', $validated['name'])
            ->where('parent_id', $validated['parent_id'] ?? null)
            ->first();

        if ($existing) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => "Folder '{$existing->name}' sudah ada.",
                    'folder' => $existing,
                ]);
            }
        }

        $folder = Folder::create([
            'name' => $validated['name'],
            'parent_id' => $validated['parent_id'] ?? null,
            'department' => $department,
            'description' => $validated['description'] ?? null,
            'created_by' => $user->id,
        ]);

        AuditLog::log(
            'folder_created',
            "Folder '{$folder->name}' berhasil dibuat di unit '{$folder->department}'.",
            Folder::class,
            $folder->id
        );

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => "Folder '{$folder->name}' berhasil dibuat.",
                'folder' => $folder,
            ]);
        }

        return back()->with('success', "Folder '{$folder->name}' berhasil dibuat.");
    }

    public function update(Request $request, Folder $folder)
    {
        $user = auth()->user();
        abort_unless($folder->canManage($user), 403, 'Akses ditolak: Hanya unit pemilik yang dapat mengubah folder ini.');

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
        ]);

        $folder->update($validated);

        AuditLog::log(
            'folder_updated',
            "Folder '{$folder->name}' diperbarui.",
            Folder::class,
            $folder->id
        );

        return back()->with('success', 'Folder berhasil diperbarui.');
    }

    public function destroy(Folder $folder)
    {
        $user = auth()->user();
        abort_unless($folder->canManage($user), 403, 'Akses ditolak: Hanya unit pemilik yang dapat menghapus folder ini.');

        if (!$user->isSuperAdmin() && empty($folder->parent_id)) {
            abort(403, 'Hanya Superadmin yang dapat menghapus folder utama.');
        }

        AuditLog::log(
            'folder_deleted',
            "Folder '{$folder->name}' dihapus.",
            Folder::class,
            $folder->id
        );

        $parentId = $folder->parent_id;
        $folder->delete();

        return redirect()->route('folders.index', ['folder_id' => $parentId])
            ->with('success', 'Folder berhasil dihapus.');
    }

    public function moveDocument(Request $request, $document)
    {
        $user = auth()->user();
        abort_unless($user && $user->canUploadDocuments(), 403);

        $doc = $document instanceof Document ? $document : Document::where('uuid', $document)->orWhere('id', $document)->firstOrFail();

        $validated = $request->validate([
            'folder_id' => 'nullable|exists:folders,id',
        ]);

        if (!empty($validated['folder_id'])) {
            $targetFolder = Folder::findOrFail($validated['folder_id']);
            abort_unless($targetFolder->canManage($user), 403, 'Akses ditolak: Anda tidak memiliki izin mengelola folder tujuan.');
            $doc->update(['folder_id' => $targetFolder->id]);
            $targetName = $targetFolder->name;
        } else {
            $doc->update(['folder_id' => null]);
            $targetName = 'Root Dokumen';
        }

        AuditLog::log(
            'document_moved',
            "Dokumen '{$doc->name}' dipindahkan ke folder '{$targetName}'.",
            Document::class,
            $doc->id
        );

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => "Dokumen '{$doc->name}' berhasil dipindahkan ke '{$targetName}'.",
                'target_folder_id' => $validated['folder_id'] ?? null,
                'target_folder_name' => $targetName,
            ]);
        }

        return back()->with('success', "Dokumen berhasil dipindahkan ke folder '{$targetName}'.");
    }

    public function updateSharing(Request $request, Folder $folder)
    {
        $user = auth()->user();
        abort_unless($user && ($user->isSuperAdmin() || $folder->canManage($user)), 403, 'Anda tidak memiliki hak untuk mengatur pembagian folder ini.');

        $validated = $request->validate([
            'shared_departments' => 'nullable|array',
            'shared_departments.*' => 'string|in:' . implode(',', array_merge(User::UNITS, ['PSTI'])),
        ]);

        $sharedDepts = $validated['shared_departments'] ?? [];
        $folder->update(['shared_departments' => $sharedDepts]);

        AuditLog::log(
            'folder_shared',
            "Izin akses folder '{$folder->name}' diperbarui ke: " . (empty($sharedDepts) ? 'Hanya unit pemilik' : implode(', ', $sharedDepts)),
            Folder::class,
            $folder->id
        );

        return response()->json([
            'success' => true,
            'message' => 'Izin akses folder berhasil disimpan.',
            'shared_departments' => $sharedDepts,
        ]);
    }

    public function quickUpload(Request $request, ?Folder $folder = null)
    {
        $user = auth()->user();
        abort_unless($user->canUploadDocuments(), 403);

        $request->validate([
            'file' => 'required|file|max:20480|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,jpg,jpeg,png,webp,gif,svg,txt,csv,zip,rar,7z,rtf',
            'folder_id' => 'nullable|exists:folders,id',
        ]);

        $folderId = $request->folder_id ?? ($folder ? $folder->id : null);
        $targetFolder = $folderId ? Folder::find($folderId) : null;
        if ($targetFolder) {
            abort_unless($targetFolder->canManage($user), 403, 'Akses ditolak: Hanya unit pemilik yang dapat mengunggah berkas ke folder ini.');
            $department = $targetFolder->getEffectiveDepartment();
        } else {
            $department = $user->department;
        }

        $file = $request->file('file');
        $originalFilename = $file->getClientOriginalName();
        $name = pathinfo($originalFilename, PATHINFO_FILENAME);
        $filePath = $file->store('documents/' . date('Y/m'), 'private');

        $category = Category::firstOrCreate(['name' => 'Umum'], ['slug' => 'umum', 'is_active' => true]);

        $doc = Document::create([
            'name' => $name,
            'category_id' => $category->id,
            'folder_id' => $targetFolder ? $targetFolder->id : null,
            'department' => $department,
            'display_date' => now()->toDateString(),
            'document_date' => now()->toDateString(),
            'upload_date' => now()->toDateString(),
            'status' => Document::STATUS_APPROVED,
            'visibility' => Document::VISIBILITY_VIEWER,
            'is_downloadable' => true,
            'current_version' => 1,
            'created_by' => $user->id,
        ]);

        DocumentVersion::create([
            'document_id' => $doc->id,
            'version_number' => 1,
            'file_path' => $filePath,
            'original_filename' => $originalFilename,
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'uploaded_by' => $user->id,
            'notes' => 'Quick upload drag and drop',
        ]);

        AuditLog::log(
            'document_created',
            "Dokumen '{$doc->name}' berhasil diunggah via drag-and-drop.",
            Document::class,
            $doc->id
        );

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => "Berkas '{$originalFilename}' berhasil diunggah.",
                'document' => $doc->load(['category', 'latestVersion']),
            ]);
        }

        return back()->with('success', "Berkas '{$originalFilename}' berhasil diunggah.");
    }
}
