<?php

namespace Tests\Feature;

use App\Models\Folder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoogleDriveTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_when_accessing_google_drive(): void
    {
        $response = $this->get('/google-drive');
        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_can_view_google_drive_page(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->get('/google-drive');
        $response->assertStatus(200);
        $response->assertSee('Integrasi Google Drive');
    }

    public function test_user_can_create_mirror_folder_in_smart_without_downloading_files(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'department' => 'Biro Umum',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->post('/google-drive/create-folder', [
            'folder_name' => 'Arsip Drive 2026',
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('folders', [
            'name' => 'Arsip Drive 2026',
            'created_by' => $user->id,
        ]);
    }

    public function test_import_folder_requires_category_and_valid_parameters(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'department' => 'Biro Umum',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->post('/google-drive/import-folder', [
            'drive_folder_id' => 'folder_123',
        ]);

        $response->assertSessionHasErrors(['category_id', 'visibility']);
    }

    public function test_google_drive_tokens_are_wiped_when_user_logs_out(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
            'google_drive_access_token' => 'token_secret',
            'google_drive_refresh_token' => 'refresh_secret',
            'google_drive_account_email' => 'psti@unisayogya.ac.id',
        ]);

        $response = $this->actingAs($user)->post('/logout');

        $response->assertRedirect('/');
        $user->refresh();
        $this->assertNull($user->google_drive_access_token);
        $this->assertNull($user->google_drive_refresh_token);
        $this->assertNull($user->google_drive_account_email);
    }
}
