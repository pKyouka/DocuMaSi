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

    public function test_shared_folder_inherits_to_child_folders_and_files_and_appears_in_sidebar_for_target_biro(): void
    {
        $adminPsti = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'department' => 'PSTI',
        ]);

        $adminAkademik = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'department' => 'Biro Akademik',
        ]);

        $adminHumas = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'department' => 'Biro Humas dan Protokol',
        ]);

        $category = Category::create([
            'name' => 'Akreditasi',
            'slug' => 'akreditasi-test',
            'is_active' => true,
        ]);

        // Root folder PSTI
        $pstiRoot = Folder::create([
            'name' => 'Folder PSTI Khusus',
            'department' => 'PSTI',
            'created_by' => $adminPsti->id,
        ]);

        // Subfolder Akreditasi yang DIBAGIKAN ke Biro Akademik
        $subAkreditasi = Folder::create([
            'name' => 'Akreditasi PSTI',
            'parent_id' => $pstiRoot->id,
            'department' => 'PSTI',
            'shared_departments' => ['Biro Akademik'],
            'created_by' => $adminPsti->id,
        ]);

        // File di dalam Akreditasi (mewarisi izin folder)
        $doc = Document::create([
            'name' => 'Borang Akreditasi 2026',
            'category_id' => $category->id,
            'folder_id' => $subAkreditasi->id,
            'department' => 'PSTI',
            'document_date' => now()->toDateString(),
            'display_date' => now()->toDateString(),
            'upload_date' => now()->toDateString(),
            'visibility' => Document::VISIBILITY_INTERNAL,
            'created_by' => $adminPsti->id,
        ]);

        // Biro Akademik melihat PSTI di root dan tree
        $responseAkademik = $this->actingAs($adminAkademik)->get('/folders');
        $responseAkademik->assertStatus(200);
        $responseAkademik->assertSee('Folder PSTI Khusus');

        // Biro Akademik bisa membuka subfolder Akreditasi dan melihat borang (warisan akses)
        $responseSubfolder = $this->actingAs($adminAkademik)->get("/folders?folder_id={$subAkreditasi->id}");
        $responseSubfolder->assertStatus(200);
        $responseSubfolder->assertSee('Borang Akreditasi 2026');

        // Biro Humas (tidak dibagikan) TIDAK melihat PSTI di root
        $responseHumas = $this->actingAs($adminHumas)->get('/folders');
        $responseHumas->assertStatus(200);
        $responseHumas->assertDontSee('Folder PSTI Khusus');
        $responseHumas->assertDontSee('Akreditasi PSTI');

        // Biro Humas ditolak saat membuka folder Akreditasi
        $responseHumasForbidden = $this->actingAs($adminHumas)->get("/folders?folder_id={$subAkreditasi->id}");
        $responseHumasForbidden->assertStatus(403);
    }

    public function test_shared_single_file_shows_folder_hierarchy_but_only_shows_shared_file_inside(): void
    {
        $adminPsti = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'department' => 'PSTI',
        ]);

        $adminAkademik = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'department' => 'Biro Akademik',
        ]);

        $category = Category::create([
            'name' => 'Kurikulum',
            'slug' => 'kurikulum-test',
            'is_active' => true,
        ]);

        // Root PSTI
        $pstiRoot = Folder::create([
            'name' => 'Program Studi PSTI',
            'department' => 'PSTI',
            'created_by' => $adminPsti->id,
        ]);

        // Subfolder Kurikulum (Folder ini TIDAK dibagikan secara utuh)
        $subKurikulum = Folder::create([
            'name' => 'Kurikulum',
            'parent_id' => $pstiRoot->id,
            'department' => 'PSTI',
            'created_by' => $adminPsti->id,
        ]);

        // File 1: DIBAGIKAN ke Biro Akademik
        $docShared = Document::create([
            'name' => 'Pedoman Kurikulum 2026 (Shared)',
            'category_id' => $category->id,
            'folder_id' => $subKurikulum->id,
            'department' => 'PSTI',
            'document_date' => now()->toDateString(),
            'display_date' => now()->toDateString(),
            'upload_date' => now()->toDateString(),
            'visibility' => Document::VISIBILITY_INTERNAL,
            'shared_departments' => ['Biro Akademik'],
            'created_by' => $adminPsti->id,
        ]);

        // File 2: TIDAK dibagikan (internal PSTI)
        $docPrivate = Document::create([
            'name' => 'Draft Rahasia Kurikulum (Private)',
            'category_id' => $category->id,
            'folder_id' => $subKurikulum->id,
            'department' => 'PSTI',
            'document_date' => now()->toDateString(),
            'display_date' => now()->toDateString(),
            'upload_date' => now()->toDateString(),
            'visibility' => Document::VISIBILITY_INTERNAL,
            'shared_departments' => null,
            'created_by' => $adminPsti->id,
        ]);

        // Biro Akademik melihat PSTI di root dan bisa navigasi ke Kurikulum
        $responseRoot = $this->actingAs($adminAkademik)->get('/folders');
        $responseRoot->assertStatus(200);
        $responseRoot->assertSee('Program Studi PSTI');

        // Buka folder Kurikulum
        $responseFolder = $this->actingAs($adminAkademik)->get("/folders?folder_id={$subKurikulum->id}");
        $responseFolder->assertStatus(200);

        // HANYA File 1 yang tampil! File 2 TIDAK tampil!
        $responseFolder->assertSee('Pedoman Kurikulum 2026 (Shared)');
        $responseFolder->assertDontSee('Draft Rahasia Kurikulum (Private)');
    }
}
