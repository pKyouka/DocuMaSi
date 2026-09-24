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

    public function download(User $user, string $fileId): array
    {
        $drive = $this->driveFor($user);
        $file = $drive->files->get($fileId, ['fields' => 'id,name,mimeType,size']);

        if (!self::isImportableMimeType($file->getMimeType())) {
            throw new RuntimeException('Format Google Docs/Sheets/Slides perlu diekspor terlebih dahulu dan belum didukung.');
        }

        if ((int) ($file->getSize() ?? 0) > 20 * 1024 * 1024) {
            throw new RuntimeException('Ukuran file melebihi batas impor 20 MB.');
        }

        $response = $drive->files->get($fileId, ['alt' => 'media']);
        $contents = $response->getBody()->getContents();
        $path = 'documents/' . date('Y/m') . '/' . uniqid('drive_', true) . '_' . basename($file->getName());
        Storage::disk('private')->put($path, $contents);

        return [
            'path' => $path,
            'name' => $file->getName(),
            'size' => Storage::disk('private')->size($path),
            'mime_type' => $file->getMimeType(),
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
        $client->setPrompt('consent');
        $client->setScopes([self::READ_ONLY_SCOPE]);

        return $client;
    }
}
