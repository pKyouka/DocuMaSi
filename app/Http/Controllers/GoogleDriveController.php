<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Folder;
use App\Models\User;
use App\Services\GoogleDriveService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

class GoogleDriveController extends Controller
{
    public function __construct(private readonly GoogleDriveService $drive)
    {
    }

    public function index(Request $request)
    {
        $user = auth()->user();
        $folderId = (string) $request->query('folder_id', 'root');
        if (trim($folderId) === '') {
            $folderId = 'root';
        }

        $items = [];
        $currentFolder = ['id' => 'root', 'name' => 'Drive Saya', 'parent_id' => null];

        if ($user->google_drive_access_token) {
            try {
                $items = $this->drive->listContents($user, $folderId);
                $currentFolder = $this->drive->getFolderDetails($user, $folderId);
            } catch (Throwable $e) {
                if (str_contains($e->getMessage(), 'invalid_grant') || str_contains($e->getMessage(), 'unauthenticated')) {
                    $user->forceFill([
                        'google_drive_access_token' => null,
                        'google_drive_refresh_token' => null,
                        'google_drive_token_expires_at' => null,
                        'google_drive_account_email' => null,
                    ])->save();

                    return redirect()->route('google-drive.index')->with('error', 'Sesi Google Drive berakhir. Silakan hubungkan kembali.');
                }
                session()->flash('error', 'Gagal memuat isi Drive: ' . $e->getMessage());
            }
        }

        $foldersFromDrive = collect($items)->where('is_folder', true)->values();
        $filesFromDrive = collect($items)->where('is_folder', false)->values();
        $categories = Category::where('is_active', true)->orderBy('name')->get();
        $folders = Folder::accessible($user)->orderBy('name')->get();
        $units = array_merge(User::UNITS, ['PSTI']);

        // Deteksi berkas mana saja yang sudah pernah di-add ke SMART (offline)
        $importedDriveIds = DocumentVersion::where('notes', 'like', 'Impor dari Google Drive [ID:%')
            ->pluck('notes')
            ->map(function ($note) {
                if (preg_match('/\[ID:(.+?)\]/', $note, $matches)) {
                    return $matches[1];
                }
                return null;
            })
            ->filter()
            ->values()
            ->all();

        return view('google-drive.index', compact(
            'items',
            'foldersFromDrive',
            'filesFromDrive',
            'currentFolder',
            'folderId',
            'categories',
            'folders',
            'units',
            'importedDriveIds'
        ));
    }

    public function stream(string $fileId)
    {
        $user = auth()->user();
        abort_unless($user->google_drive_access_token, 403);

        try {
            $data = $this->drive->getFileStream($user, $fileId);

            return response()->stream(function () use ($data) {
                $body = $data['stream'];
                while (!$body->eof()) {
                    echo $body->read(1024 * 8);
                }
            }, 200, [
                'Content-Type' => $data['mime_type'],
                'Content-Disposition' => 'inline; filename="' . addslashes($data['name']) . '"',
                'Cache-Control' => 'no-cache, private',
            ]);
        } catch (Throwable) {
            return redirect()->away("https://drive.google.com/file/d/{$fileId}/view");
        }
    }

    public function connect()
    {
        abort_unless(config('services.google.client_id') && config('services.google.client_secret'), 503, 'Google Drive belum dikonfigurasi oleh administrator.');

        session(['google_drive_oauth_state' => $state = Str::random(40)]);
        session()->save();

        return redirect()->away($this->drive->authorizationUrl($state));
    }

    public function callback(Request $request)
    {
        abort_unless($request->filled('state') && hash_equals((string) session('google_drive_oauth_state'), (string) $request->state), 419, 'Sesi koneksi Google Drive tidak valid.');
        $request->validate(['code' => 'required|string']);

        $this->drive->authenticate(auth()->user(), $request->string('code')->toString());
        session()->forget('google_drive_oauth_state');

        return redirect()->route('google-drive.index')->with('success', 'Google Drive berhasil dihubungkan.');
    }

    public function disconnect()
    {
        auth()->user()->forceFill([
            'google_drive_access_token' => null,
            'google_drive_refresh_token' => null,
            'google_drive_token_expires_at' => null,
            'google_drive_account_email' => null,
        ])->save();

        return redirect()->route('google-drive.index')->with('success', 'Koneksi akun Google Drive berhasil diputuskan.');
    }

    public function import(Request $request)
    {
        $user = auth()->user();
        abort_unless($user->canUploadDocuments(), 403);

        $validated = $request->validate([
            'drive_file_id' => 'required|string|max:255',
            'document_name' => 'nullable|string|max:255',
            'category_id' => 'required|exists:categories,id',
            'folder_id' => 'nullable|exists:folders,id',
            'visibility' => 'required|in:viewer,internal,private',
            'shared_departments' => 'nullable|array',
            'shared_departments.*' => 'string|in:' . implode(',', array_merge(User::UNITS, ['PSTI'])),
        ]);

        $folder = !empty($validated['folder_id']) ? Folder::findOrFail($validated['folder_id']) : null;
        if ($folder) {
            abort_unless($folder->canManage($user), 403, 'Akses ke folder tujuan ditolak.');
        }

        try {
            $file = $this->drive->download($user, $validated['drive_file_id']);
            $docName = trim($validated['document_name'] ?? '') ?: pathinfo($file['name'], PATHINFO_FILENAME);

            $document = Document::create([
                'uuid' => (string) Str::uuid(),
                'name' => $docName,
                'category_id' => $validated['category_id'],
                'folder_id' => $folder?->id,
                'department' => $folder?->getEffectiveDepartment() ?: $user->department,
                'description' => 'Diimpor dari Google Drive (Offline)',
                'document_date' => now()->toDateString(),
                'display_date' => now()->toDateString(),
                'upload_date' => now()->toDateString(),
                'status' => $user->isUser() ? Document::STATUS_SUBMITTED : Document::STATUS_APPROVED,
                'prodi_approval_status' => (!$user->isUser() && ($user->isAdminProdi() || $user->isSuperAdmin())) ? 'approved' : null,
                'visibility' => $validated['visibility'],
                'shared_departments' => $validated['shared_departments'] ?? [],
                'is_downloadable' => true,
                'current_version' => 1,
                'created_by' => $user->id,
            ]);

            DocumentVersion::create([
                'document_id' => $document->id,
                'version_number' => 1,
                'file_path' => $file['path'],
                'original_filename' => $file['name'],
                'file_size' => $file['size'],
                'mime_type' => $file['mime_type'],
                'uploaded_by' => $user->id,
                'notes' => "Impor dari Google Drive [ID:{$validated['drive_file_id']}]",
            ]);
        } catch (Throwable $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        AuditLog::log('document_imported', "Dokumen '{$document->name}' diimpor offline dari Google Drive.", Document::class, $document->id);

        return back()->with('success', "Dokumen '{$document->name}' berhasil di-add ke sistem SMART (tersimpan offline).");
    }

    public function createFolder(Request $request)
    {
        $user = auth()->user();
        abort_unless($user->canUploadDocuments(), 403);

        $validated = $request->validate([
            'folder_name' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:folders,id',
            'shared_departments' => 'nullable|array',
            'shared_departments.*' => 'string|in:' . implode(',', array_merge(User::UNITS, ['PSTI'])),
        ]);

        $parentFolder = !empty($validated['parent_id']) ? Folder::findOrFail($validated['parent_id']) : null;
        if ($parentFolder) {
            abort_unless($parentFolder->canManage($user), 403, 'Akses ke folder tujuan ditolak.');
        }

        $folder = Folder::create([
            'name' => $validated['folder_name'],
            'parent_id' => $parentFolder?->id,
            'department' => $parentFolder?->getEffectiveDepartment() ?: $user->department,
            'description' => 'Folder dibuat dari referensi Google Drive',
            'shared_departments' => $validated['shared_departments'] ?? [],
            'created_by' => $user->id,
        ]);

        AuditLog::log('folder_created', "Folder '{$folder->name}' dibuat di SMART.", Folder::class, $folder->id);

        return back()->with('success', "Folder '{$folder->name}' berhasil dibuat di SMART. Berkas dapat dipilih dan ditambahkan ke dalam folder ini.");
    }

    public function importFolder(Request $request)
    {
        $user = auth()->user();
        abort_unless($user->canUploadDocuments(), 403);

        $validated = $request->validate([
            'drive_folder_id' => 'required|string|max:255',
            'folder_name' => 'nullable|string|max:255',
            'parent_id' => 'nullable|exists:folders,id',
            'category_id' => 'required|exists:categories,id',
            'visibility' => 'required|in:viewer,internal,private',
            'shared_departments' => 'nullable|array',
            'shared_departments.*' => 'string|in:' . implode(',', array_merge(User::UNITS, ['PSTI'])),
        ]);

        $parentFolder = !empty($validated['parent_id']) ? Folder::findOrFail($validated['parent_id']) : null;
        if ($parentFolder) {
            abort_unless($parentFolder->canManage($user), 403, 'Akses ke folder tujuan ditolak.');
        }

        @set_time_limit(300);

        try {
            $meta = $this->drive->getFolderMetadata($user, $validated['drive_folder_id']);
            $folderName = trim($validated['folder_name'] ?? '') ?: $meta['name'];

            $department = $parentFolder?->getEffectiveDepartment() ?: $user->department;
            $sharedDepts = $validated['shared_departments'] ?? [];

            // 1. Buat folder di SMART
            $localFolder = Folder::create([
                'name' => $folderName,
                'parent_id' => $parentFolder?->id,
                'department' => $department,
                'description' => "Folder diimpor dari Google Drive",
                'shared_departments' => $sharedDepts,
                'created_by' => $user->id,
            ]);

            // 2. Impor seluruh berkas & subfolder di dalam folder tersebut
            $importedCount = $this->importFolderContentsRecursively(
                $user,
                $validated['drive_folder_id'],
                $localFolder,
                (int) $validated['category_id'],
                $validated['visibility'],
                $sharedDepts
            );

            AuditLog::log(
                'folder_imported',
                "Folder '{$localFolder->name}' beserta {$importedCount} dokumen diimpor dari Google Drive ke SMART.",
                Folder::class,
                $localFolder->id
            );

            return redirect()->route('google-drive.index', ['folder_id' => $validated['drive_folder_id']])
                ->with('success', "Folder '{$localFolder->name}' beserta {$importedCount} berkas di dalamnya berhasil diimpor offline ke SMART.");
        } catch (Throwable $exception) {
            return back()->withInput()->with('error', 'Gagal mengimpor folder: ' . $exception->getMessage());
        }
    }

    private function importFolderContentsRecursively(
        User $user,
        string $driveFolderId,
        Folder $targetLocalFolder,
        int $categoryId,
        string $visibility,
        array $sharedDepartments
    ): int {
        $files = $this->drive->listFilesInFolder($user, $driveFolderId);
        $importedCount = 0;

        foreach ($files as $file) {
            if ($file['is_folder']) {
                $subFolder = Folder::create([
                    'name' => $file['name'],
                    'parent_id' => $targetLocalFolder->id,
                    'department' => $targetLocalFolder->getEffectiveDepartment() ?: $user->department,
                    'description' => "Subfolder diimpor dari Google Drive",
                    'shared_departments' => $sharedDepartments,
                    'created_by' => $user->id,
                ]);

                $importedCount += $this->importFolderContentsRecursively(
                    $user,
                    $file['id'],
                    $subFolder,
                    $categoryId,
                    $visibility,
                    $sharedDepartments
                );
                continue;
            }

            try {
                $downloaded = $this->drive->download($user, $file['id']);

                $document = Document::create([
                    'uuid' => (string) Str::uuid(),
                    'name' => pathinfo($downloaded['name'], PATHINFO_FILENAME),
                    'category_id' => $categoryId,
                    'folder_id' => $targetLocalFolder->id,
                    'department' => $targetLocalFolder->getEffectiveDepartment() ?: $user->department,
                    'description' => "Berkas diimpor bersama folder '{$targetLocalFolder->name}'",
                    'document_date' => now()->toDateString(),
                    'display_date' => now()->toDateString(),
                    'upload_date' => now()->toDateString(),
                    'status' => $user->isUser() ? Document::STATUS_SUBMITTED : Document::STATUS_APPROVED,
                    'prodi_approval_status' => (!$user->isUser() && ($user->isAdminProdi() || $user->isSuperAdmin())) ? 'approved' : null,
                    'visibility' => $visibility,
                    'shared_departments' => $sharedDepartments,
                    'is_downloadable' => true,
                    'current_version' => 1,
                    'created_by' => $user->id,
                ]);

                DocumentVersion::create([
                    'document_id' => $document->id,
                    'version_number' => 1,
                    'file_path' => $downloaded['path'],
                    'original_filename' => $downloaded['name'],
                    'file_size' => $downloaded['size'],
                    'mime_type' => $downloaded['mime_type'],
                    'uploaded_by' => $user->id,
                    'notes' => "Impor dari Google Drive [ID:{$file['id']}]",
                ]);

                $importedCount++;
            } catch (Throwable) {
                // Lewati berkas jika ada kendala agar berkas lainnya tetap terimpor
            }
        }

        return $importedCount;
    }
}
