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

    public function index()
    {
        $user = auth()->user();
        $items = $user->google_drive_access_token ? $this->drive->listItems($user) : [];
        $foldersFromDrive = collect($items)->where('is_folder', true)->values();
        $filesFromDrive = collect($items)->where('is_folder', false)->values();
        $categories = Category::where('is_active', true)->orderBy('name')->get();
        $folders = Folder::accessible($user)->orderBy('name')->get();
        $units = array_merge(User::UNITS, ['PSTI']);

        return view('google-drive.index', compact('items', 'foldersFromDrive', 'filesFromDrive', 'categories', 'folders', 'units'));
    }

    public function connect()
    {
        abort_unless(config('services.google.client_id') && config('services.google.client_secret'), 503, 'Google Drive belum dikonfigurasi oleh administrator.');

        session(['google_drive_oauth_state' => $state = Str::random(40)]);

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
            $document = Document::create([
                'uuid' => (string) Str::uuid(),
                'name' => pathinfo($file['name'], PATHINFO_FILENAME),
                'category_id' => $validated['category_id'],
                'folder_id' => $folder?->id,
                'department' => $folder?->getEffectiveDepartment() ?: $user->department,
                'description' => 'Diimpor dari Google Drive.',
                'document_date' => now()->toDateString(),
                'display_date' => now()->toDateString(),
                'upload_date' => now()->toDateString(),
                'status' => $validated['visibility'] === 'viewer' ? Document::STATUS_APPROVED : Document::STATUS_DRAFT,
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
                'notes' => 'Impor dari Google Drive',
            ]);
        } catch (Throwable $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        AuditLog::log('document_imported', "Dokumen '{$document->name}' diimpor dari Google Drive.", Document::class, $document->id);

        return back()->with('success', "Dokumen '{$document->name}' berhasil diimpor ke penyimpanan lokal.");
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

            // 2. Deteksi semua file di dalam folder Google Drive tersebut
            $files = $this->drive->listFilesInFolder($user, $validated['drive_folder_id']);
            $importedCount = 0;
            $skippedCount = 0;

            foreach ($files as $file) {
                if ($file['is_folder']) {
                    continue;
                }

                if (!$file['importable']) {
                    $skippedCount++;
                    continue;
                }

                try {
                    $downloaded = $this->drive->download($user, $file['id']);

                    $document = Document::create([
                        'uuid' => (string) Str::uuid(),
                        'name' => pathinfo($downloaded['name'], PATHINFO_FILENAME),
                        'category_id' => $validated['category_id'],
                        'folder_id' => $localFolder->id,
                        'department' => $department,
                        'description' => "Berkas diimpor bersama folder '{$localFolder->name}'",
                        'document_date' => now()->toDateString(),
                        'display_date' => now()->toDateString(),
                        'upload_date' => now()->toDateString(),
                        'status' => $validated['visibility'] === 'viewer' ? Document::STATUS_APPROVED : Document::STATUS_DRAFT,
                        'visibility' => $validated['visibility'],
                        'shared_departments' => $sharedDepts,
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
                        'notes' => "Diimpor dari Google Drive ke folder {$localFolder->name}",
                    ]);

                    $importedCount++;
                } catch (\Throwable $e) {
                    $skippedCount++;
                }
            }

            AuditLog::log(
                'folder_imported',
                "Folder '{$localFolder->name}' beserta {$importedCount} dokumen diimpor dari Google Drive ke SMART.",
                Folder::class,
                $localFolder->id
            );

            return redirect()->route('folders.index', ['folder_id' => $localFolder->id])
                ->with('success', "Folder '{$localFolder->name}' berhasil dibuat di SMART dengan {$importedCount} berkas terunduh dan siap dibagikan offline.");
        } catch (\Throwable $exception) {
            return back()->withInput()->with('error', 'Gagal mengimpor folder: ' . $exception->getMessage());
        }
    }
}
