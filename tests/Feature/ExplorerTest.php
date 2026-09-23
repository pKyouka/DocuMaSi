<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Document;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExplorerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_when_accessing_explorer(): void
    {
        $response = $this->get('/');
        $response->assertRedirect('/login');

        $responseFolders = $this->get('/folders');
        $responseFolders->assertRedirect('/login');
    }

    public function test_authenticated_admin_sees_management_controls(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'department' => 'Biro Akademik',
        ]);

        $response = $this->actingAs($admin)->get('/');

        $response->assertStatus(200);
        $response->assertSee('Upload Berkas');
        $response->assertSee('+ Folder');
        $response->assertSee($admin->name);
    }

    public function test_admin_can_create_folder(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'department' => 'Biro Akademik',
        ]);

        $response = $this->actingAs($admin)->post('/folders', [
            'name' => 'Surat Keputusan',
            'department' => 'Biro Akademik',
            'description' => 'Folder dokumen SK',
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('folders', [
            'name' => 'Surat Keputusan',
            'department' => 'Biro Akademik',
        ]);
    }

    public function test_admin_can_quick_upload_document(): void
    {
        Storage::fake('private');

        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'department' => 'Biro Akademik',
        ]);

        $folder = Folder::create([
            'name' => 'Pedoman',
            'department' => 'Biro Akademik',
            'created_by' => $admin->id,
        ]);

        $file = UploadedFile::fake()->create('pedoman-2026.pdf', 500, 'application/pdf');

        $response = $this->actingAs($admin)->postJson('/folders/quick-upload', [
            'file' => $file,
            'folder_id' => $folder->id,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);

        $this->assertDatabaseHas('documents', [
            'name' => 'pedoman-2026',
            'folder_id' => $folder->id,
            'department' => 'Biro Akademik',
        ]);
    }

    public function test_user_can_upload_document_via_form(): void
    {
        Storage::fake('private');

        $user = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'department' => 'Biro Akademik',
        ]);

        $category = Category::create([
            'name' => 'Pedoman Form',
            'slug' => 'pedoman-form',
            'is_active' => true,
        ]);

        $file = UploadedFile::fake()->create('dokumen-resmi.pdf', 300, 'application/pdf');

        $response = $this->actingAs($user)->post('/documents', [
            'name' => 'Dokumen Resmi 2026',
            'category_id' => $category->id,
            'display_date' => now()->toDateString(),
            'visibility' => 'viewer',
            'file' => $file,
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('documents', [
            'name' => 'Dokumen Resmi 2026',
            'category_id' => $category->id,
            'created_by' => $user->id,
        ]);
    }

    public function test_admin_can_move_document_to_another_folder(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'department' => 'Biro Akademik',
        ]);

        $category = Category::create([
            'name' => 'Pedoman',
            'slug' => 'pedoman',
            'is_active' => true,
        ]);

        $folderSource = Folder::create([
            'name' => 'Folder Asal',
            'department' => 'Biro Akademik',
            'created_by' => $admin->id,
        ]);

        $folderTarget = Folder::create([
            'name' => 'Folder Tujuan',
            'department' => 'Biro Akademik',
            'created_by' => $admin->id,
        ]);

        $document = Document::create([
            'name' => 'Dokumen Pindah',
            'category_id' => $category->id,
            'folder_id' => $folderSource->id,
            'department' => 'Biro Akademik',
            'document_date' => now()->toDateString(),
            'display_date' => now()->toDateString(),
            'upload_date' => now()->toDateString(),
            'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->postJson("/documents/{$document->id}/move", [
            'folder_id' => $folderTarget->id,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);

        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'folder_id' => $folderTarget->id,
        ]);
    }

    public function test_admin_can_rename_folder(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'department' => 'Biro Akademik',
        ]);

        $folder = Folder::create([
            'name' => 'Folder Lama',
            'department' => 'Biro Akademik',
            'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->put("/folders/{$folder->id}", [
            'name' => 'Folder Baru Direname',
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('folders', [
            'id' => $folder->id,
            'name' => 'Folder Baru Direname',
        ]);
    }

    public function test_admin_can_delete_folder(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_SUPERADMIN,
        ]);

        $folder = Folder::create([
            'name' => 'Folder Hapus',
            'department' => 'Biro Akademik',
            'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->delete("/folders/{$folder->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('folders', [
            'id' => $folder->id,
        ]);
    }

    public function test_authenticated_user_can_access_shared_document(): void
    {
        $user = User::factory()->create();

        $category = Category::create([
            'name' => 'Umum',
            'slug' => 'umum',
            'is_active' => true,
        ]);

        $document = Document::create([
            'name' => 'Dokumen Publik Share',
            'category_id' => $category->id,
            'department' => 'Biro Akademik',
            'document_date' => now()->toDateString(),
            'display_date' => now()->toDateString(),
            'upload_date' => now()->toDateString(),
            'visibility' => Document::VISIBILITY_VIEWER,
            'status' => Document::STATUS_APPROVED,
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->get("/documents/{$document->uuid}");
        $response->assertStatus(200);
        $response->assertSee('Dokumen Publik Share');
    }

    public function test_admin_can_update_folder_sharing(): void
    {
        $adminAkademik = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'department' => 'Biro Akademik',
        ]);

        $folder = Folder::create([
            'name' => 'Folder Khusus',
            'department' => 'Biro Akademik',
            'created_by' => $adminAkademik->id,
        ]);

        $response = $this->actingAs($adminAkademik)->postJson("/folders/{$folder->id}/share", [
            'shared_departments' => ['Biro Kemahasiswaan dan Alumni'],
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'shared_departments' => ['Biro Kemahasiswaan dan Alumni'],
        ]);

        $folder->refresh();
        $this->assertEquals(['Biro Kemahasiswaan dan Alumni'], $folder->shared_departments);
    }

    public function test_biro_access_control_on_shared_folder(): void
    {
        $adminAkademik = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'department' => 'Biro Akademik',
        ]);

        $adminKemahasiswaan = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'department' => 'Biro Kemahasiswaan dan Alumni',
        ]);

        $adminMutu = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'department' => 'Biro Penjaminan Mutu',
        ]);

        $folder = Folder::create([
            'name' => 'Folder Terbatas',
            'department' => 'Biro Akademik',
            'shared_departments' => ['Biro Kemahasiswaan dan Alumni'],
            'created_by' => $adminAkademik->id,
        ]);

        // Biro yang dicentang (Kemahasiswaan) BISA akses
        $responseAllowed = $this->actingAs($adminKemahasiswaan)->get("/folders?folder_id={$folder->id}");
        $responseAllowed->assertStatus(200);

        // Biro yang TIDAK dicentang (Mutu) DITOLAK (403)
        $responseForbidden = $this->actingAs($adminMutu)->get("/folders?folder_id={$folder->id}");
        $responseForbidden->assertStatus(403);
    }

    public function test_biro_access_control_on_shared_document(): void
    {
        $adminAkademik = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'department' => 'Biro Akademik',
        ]);

        $adminKemahasiswaan = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'department' => 'Biro Kemahasiswaan dan Alumni',
        ]);

        $adminMutu = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'department' => 'Biro Penjaminan Mutu',
        ]);

        $category = Category::create([
            'name' => 'Surat',
            'slug' => 'surat',
            'is_active' => true,
        ]);

        $document = Document::create([
            'name' => 'Dokumen Khusus Dua Biro',
            'category_id' => $category->id,
            'department' => 'Biro Akademik',
            'document_date' => now()->toDateString(),
            'display_date' => now()->toDateString(),
            'upload_date' => now()->toDateString(),
            'visibility' => Document::VISIBILITY_INTERNAL,
            'shared_departments' => ['Biro Kemahasiswaan dan Alumni'],
            'created_by' => $adminAkademik->id,
        ]);

        // Biro yang dicentang (Kemahasiswaan) BISA akses
        $responseAllowed = $this->actingAs($adminKemahasiswaan)->get("/documents/{$document->uuid}");
        $responseAllowed->assertStatus(200);

        // Biro yang TIDAK dicentang (Mutu) DITOLAK (403)
        $responseForbidden = $this->actingAs($adminMutu)->get("/documents/{$document->uuid}");
        $responseForbidden->assertStatus(403);
    }

    public function test_admin_can_update_document_display_date(): void
    {
        $adminAkademik = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'department' => 'Biro Akademik',
        ]);

        $category = Category::create([
            'name' => 'Pedoman',
            'slug' => 'pedoman-test',
            'is_active' => true,
        ]);

        $actualUploadedAt = now()->subDays(5);
        $document = Document::create([
            'name' => 'Buku Pedoman',
            'category_id' => $category->id,
            'department' => 'Biro Akademik',
            'document_date' => now()->toDateString(),
            'display_date' => now()->toDateString(),
            'upload_date' => now()->toDateString(),
            'actual_uploaded_at' => $actualUploadedAt,
            'visibility' => Document::VISIBILITY_VIEWER,
            'created_by' => $adminAkademik->id,
        ]);

        // Admin Biro Akademik mengubah display_date ke 2026-07-23 (seperti di diagram)
        $response = $this->actingAs($adminAkademik)->postJson("/documents/{$document->uuid}/display-date", [
            'display_date' => '2026-07-23',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'display_date' => '2026-07-23',
        ]);

        $document->refresh();
        $this->assertEquals('2026-07-23', $document->display_date->toDateString());
        // actual_uploaded_at tetap utuh untuk audit
        $this->assertEquals($actualUploadedAt->toDateTimeString(), $document->actual_uploaded_at->toDateTimeString());
    }
}
