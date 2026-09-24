<?php

namespace App\Services;

use App\Models\User;
use Google\Client;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class GoogleDriveService
{
    public const READ_ONLY_SCOPE = 'https://www.googleapis.com/auth/drive.readonly';
    public const FOLDER_MIME_TYPE = 'application/vnd.google-apps.folder';

    public static function isFolderMimeType(?string $mimeType): bool
    {
        return $mimeType === self::FOLDER_MIME_TYPE;
    }

    public static function isImportableMimeType(?string $mimeType): bool
    {
        return $mimeType !== null && in_array($mimeType, [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'image/jpeg',
            'image/png',
            'application/zip',
        ], true);
    }

    public static function isExportableGoogleMimeType(?string $mimeType): bool
    {
        return in_array($mimeType, [
            'application/vnd.google-apps.document',
            'application/vnd.google-apps.spreadsheet',
            'application/vnd.google-apps.presentation',
        ], true);
    }

    public static function canImportOrExport(?string $mimeType): bool
    {
        return self::isImportableMimeType($mimeType) || self::isExportableGoogleMimeType($mimeType);
    }

    public function authorizationUrl(string $state): string
    {
        $client = $this->newClient();
        $client->setState($state);

        return $client->createAuthUrl();
    }

    public function authenticate(User $user, string $code): void
    {
        $client = $this->newClient();
        $token = $client->fetchAccessTokenWithAuthCode($code);

        if (isset($token['error'])) {
            throw new RuntimeException('Google menolak proses login Drive.');
        }

        $client->setAccessToken($token);
        $about = (new Drive($client))->about->get(['fields' => 'user(emailAddress)']);
        $user->forceFill([
            'google_drive_access_token' => $token['access_token'] ?? null,
            'google_drive_refresh_token' => $token['refresh_token'] ?? $user->google_drive_refresh_token,
            'google_drive_token_expires_at' => now()->addSeconds((int) ($token['expires_in'] ?? 3600)),
            'google_drive_account_email' => $about->getUser()->getEmailAddress(),
        ])->save();
    }

    public function listItems(User $user): array
    {
        $drive = $this->driveFor($user);
        $items = [];
        $pageToken = null;

        do {
            $result = $drive->files->listFiles([
                'q' => 'trashed = false',
                'spaces' => 'drive',
                'fields' => 'nextPageToken, files(id, name, mimeType, size, modifiedTime, parents, webViewLink)',
                'pageSize' => 1000,
                'pageToken' => $pageToken,
                'orderBy' => 'folder, name',
            ]);

            foreach ($result->getFiles() as $file) {
                $items[] = [
                    'id' => $file->getId(),
                    'name' => $file->getName(),
                    'mime_type' => $file->getMimeType(),
                    'size' => (int) ($file->getSize() ?? 0),
                    'modified_time' => $file->getModifiedTime(),
                    'parents' => $file->getParents() ?? [],
                    'is_folder' => $file->getMimeType() === 'application/vnd.google-apps.folder',
                    'web_view_link' => $file->getWebViewLink(),
                    'importable' => self::isImportableMimeType($file->getMimeType()),
                ];
            }

            $pageToken = $result->getNextPageToken();
        } while ($pageToken);

        return $items;
    }

    public function listContents(User $user, string $folderId = 'root'): array
    {
        $drive = $this->driveFor($user);
        $escaped = str_replace(["\\", "'"], ["\\\\", "\\'"], $folderId);
        $result = $drive->files->listFiles([
            'q' => "'{$escaped}' in parents and trashed = false",
            'spaces' => 'drive',
            'fields' => 'files(id, name, mimeType, size, modifiedTime, webViewLink)',
            'pageSize' => 500,
            'orderBy' => 'folder, name',
        ]);

        $items = [];
        foreach ($result->getFiles() as $file) {
            $isFolder = $file->getMimeType() === self::FOLDER_MIME_TYPE;
            $items[] = [
                'id' => $file->getId(),
                'name' => $file->getName(),
                'mime_type' => $file->getMimeType(),
                'size' => (int) ($file->getSize() ?? 0),
                'modified_time' => $file->getModifiedTime(),
                'web_view_link' => $file->getWebViewLink(),
                'is_folder' => $isFolder,
                'importable' => self::canImportOrExport($file->getMimeType()),
            ];
        }

        return $items;
    }

    public function getFolderDetails(User $user, string $folderId = 'root'): array
    {
        if ($folderId === 'root') {
            return [
                'id' => 'root',
                'name' => 'Drive Saya',
                'parent_id' => null,
            ];
        }

        try {
            $drive = $this->driveFor($user);
            $folder = $drive->files->get($folderId, ['fields' => 'id, name, parents']);
            $parents = $folder->getParents() ?? [];

            return [
                'id' => $folder->getId(),
                'name' => $folder->getName(),
                'parent_id' => !empty($parents) ? $parents[0] : 'root',
            ];
        } catch (\Throwable) {
            return [
                'id' => 'root',
                'name' => 'Drive Saya',
                'parent_id' => null,
            ];
        }
    }

    public function getFileStream(User $user, string $fileId): array
    {
        $drive = $this->driveFor($user);
        $file = $drive->files->get($fileId, ['fields' => 'id, name, mimeType, size, webViewLink']);
        $mime = $file->getMimeType();
        $name = $file->getName();

        if ($mime === 'application/vnd.google-apps.document') {
            $response = $drive->files->export($fileId, 'application/pdf', ['alt' => 'media']);
            $mime = 'application/pdf';
            if (!str_ends_with(strtolower($name), '.pdf')) {
                $name .= '.pdf';
            }
        } elseif ($mime === 'application/vnd.google-apps.spreadsheet') {
            $exportMime = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
            $response = $drive->files->export($fileId, $exportMime, ['alt' => 'media']);
            $mime = $exportMime;
            if (!str_ends_with(strtolower($name), '.xlsx')) {
                $name .= '.xlsx';
            }
        } elseif ($mime === 'application/vnd.google-apps.presentation') {
            $response = $drive->files->export($fileId, 'application/pdf', ['alt' => 'media']);
            $mime = 'application/pdf';
            if (!str_ends_with(strtolower($name), '.pdf')) {
                $name .= '.pdf';
            }
        } else {
            $response = $drive->files->get($fileId, ['alt' => 'media']);
        }

        return [
            'name' => $name,
            'mime_type' => $mime,
            'size' => (int) ($file->getSize() ?? 0),
            'stream' => $response->getBody(),
            'web_view_link' => $file->getWebViewLink(),
        ];
    }

    public function listFolders(User $user): array
    {
        $drive = $this->driveFor($user);
        $result = $drive->files->listFiles([
            'q' => "mimeType = 'application/vnd.google-apps.folder' and trashed = false",
            'spaces' => 'drive',
            'fields' => 'files(id, name, modifiedTime, parents, webViewLink)',
            'pageSize' => 500,
            'orderBy' => 'name',
        ]);

        $folders = [];
        foreach ($result->getFiles() as $folder) {
            $folders[] = [
                'id' => $folder->getId(),
                'name' => $folder->getName(),
                'parents' => $folder->getParents() ?? [],
                'modified_time' => $folder->getModifiedTime(),
                'web_view_link' => $folder->getWebViewLink(),
            ];
        }

        return $folders;
    }

    public function listFilesInFolder(User $user, string $folderId): array
    {
        $drive = $this->driveFor($user);
        $escaped = str_replace(["\\", "'"], ["\\\\", "\\'"], $folderId);
        $result = $drive->files->listFiles([
            'q' => "'{$escaped}' in parents and trashed = false",
            'spaces' => 'drive',
            'fields' => 'files(id, name, mimeType, size, modifiedTime, webViewLink)',
            'pageSize' => 500,
            'orderBy' => 'folder, name',
        ]);

        $items = [];
        foreach ($result->getFiles() as $file) {
            $items[] = [
                'id' => $file->getId(),
                'name' => $file->getName(),
                'mime_type' => $file->getMimeType(),
                'size' => (int) ($file->getSize() ?? 0),
                'modified_time' => $file->getModifiedTime(),
                'web_view_link' => $file->getWebViewLink(),
                'is_folder' => $file->getMimeType() === self::FOLDER_MIME_TYPE,
                'importable' => self::canImportOrExport($file->getMimeType()),
            ];
        }

        return $items;
    }

    public function getFolderMetadata(User $user, string $folderId): array
    {
        $drive = $this->driveFor($user);
        $folder = $drive->files->get($folderId, ['fields' => 'id,name,mimeType']);

        return [
            'id' => $folder->getId(),
            'name' => $folder->getName(),
        ];
    }

    public function download(User $user, string $fileId): array
    {
        $drive = $this->driveFor($user);
        $file = $drive->files->get($fileId, ['fields' => 'id,name,mimeType,size']);
        $mime = $file->getMimeType();
        $name = $file->getName();

        if ($mime === 'application/vnd.google-apps.document') {
            $response = $drive->files->export($fileId, 'application/pdf', ['alt' => 'media']);
            $contents = $response->getBody()->getContents();
            if (!str_ends_with(strtolower($name), '.pdf')) {
                $name .= '.pdf';
            }
            $mime = 'application/pdf';
        } elseif ($mime === 'application/vnd.google-apps.spreadsheet') {
            $exportMime = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
            $response = $drive->files->export($fileId, $exportMime, ['alt' => 'media']);
            $contents = $response->getBody()->getContents();
            if (!str_ends_with(strtolower($name), '.xlsx')) {
                $name .= '.xlsx';
            }
            $mime = $exportMime;
        } elseif ($mime === 'application/vnd.google-apps.presentation') {
            $response = $drive->files->export($fileId, 'application/pdf', ['alt' => 'media']);
            $contents = $response->getBody()->getContents();
            if (!str_ends_with(strtolower($name), '.pdf')) {
                $name .= '.pdf';
            }
            $mime = 'application/pdf';
        } else {
            if (!self::isImportableMimeType($mime)) {
                throw new RuntimeException("Format berkas '{$name}' belum didukung.");
            }

            if ((int) ($file->getSize() ?? 0) > 20 * 1024 * 1024) {
                throw new RuntimeException("Ukuran berkas '{$name}' melebihi batas 20 MB.");
            }

            $response = $drive->files->get($fileId, ['alt' => 'media']);
            $contents = $response->getBody()->getContents();
        }

        $path = 'documents/' . date('Y/m') . '/' . uniqid('drive_', true) . '_' . basename($name);
        Storage::disk('private')->put($path, $contents);

        return [
            'path' => $path,
            'name' => $name,
            'size' => Storage::disk('private')->size($path),
            'mime_type' => $mime,
        ];
    }

    private function driveFor(User $user): Drive
    {
        $client = $this->newClient();
        $client->setAccessToken([
            'access_token' => $user->google_drive_access_token,
            'refresh_token' => $user->google_drive_refresh_token,
            'expires_in' => max(0, now()->diffInSeconds($user->google_drive_token_expires_at, false)),
        ]);

        if ($client->isAccessTokenExpired() && $user->google_drive_refresh_token) {
            $token = $client->fetchAccessTokenWithRefreshToken($user->google_drive_refresh_token);
            $user->forceFill([
                'google_drive_access_token' => $token['access_token'] ?? $user->google_drive_access_token,
                'google_drive_token_expires_at' => now()->addSeconds((int) ($token['expires_in'] ?? 3600)),
            ])->save();
            $client->setAccessToken($token);
        }

        if (!$client->getAccessToken()) {
            throw new RuntimeException('Akun Google Drive belum terhubung.');
        }

        return new Drive($client);
    }

    private function newClient(): Client
    {
        $client = new Client();
        $client->setClientId((string) config('services.google.client_id'));
        $client->setClientSecret((string) config('services.google.client_secret'));
        $client->setRedirectUri((string) config('services.google.redirect'));
        $client->setAccessType('offline');
        $client->setPrompt('select_account consent');
        $client->setScopes([self::READ_ONLY_SCOPE]);

        return $client;
    }
}
