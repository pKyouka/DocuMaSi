<?php

namespace Tests\Unit;

use App\Services\GoogleDriveService;
use Tests\TestCase;

class GoogleDriveServiceTest extends TestCase
{
    public function test_supported_binary_files_can_be_imported(): void
    {
        $this->assertTrue(GoogleDriveService::isImportableMimeType('application/pdf'));
        $this->assertTrue(GoogleDriveService::isImportableMimeType('image/png'));
    }

    public function test_google_workspace_files_are_not_imported_without_export_mapping(): void
    {
        $this->assertFalse(GoogleDriveService::isImportableMimeType('application/vnd.google-apps.document'));
        $this->assertFalse(GoogleDriveService::isImportableMimeType('application/vnd.google-apps.spreadsheet'));
        $this->assertFalse(GoogleDriveService::isImportableMimeType('application/vnd.google-apps.presentation'));
    }

    public function test_folder_mime_type_is_recognized(): void
    {
        $this->assertTrue(GoogleDriveService::isFolderMimeType('application/vnd.google-apps.folder'));
        $this->assertFalse(GoogleDriveService::isFolderMimeType('application/pdf'));
    }
}
