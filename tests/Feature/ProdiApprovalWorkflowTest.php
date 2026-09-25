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

class ProdiApprovalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_regular_user_upload_requires_prodi_admin_approval(): void
    {
        Storage::fake('private');

        $adminProdi = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'department' => 'Program Studi Teknologi Informasi',
        ]);

        $regularUser = User::factory()->create([
            'role' => User::ROLE_USER,
            'department' => 'Program Studi Teknologi Informasi',
        ]);

        $otherUser = User::factory()->create([
            'role' => User::ROLE_USER,
            'department' => 'Program Studi Teknologi Informasi',
        ]);

        $folder = Folder::create([
            'name' => 'Tugas Akhir',
            'department' => 'Program Studi Teknologi Informasi',
            'created_by' => $adminProdi->id,
        ]);

        $file = UploadedFile::fake()->create('laporan-mahasiswa.pdf', 300, 'application/pdf');

        // 1. Regular user uploads file via quick upload
        $response = $this->actingAs($regularUser)->postJson('/folders/quick-upload', [
            'file' => $file,
            'folder_id' => $folder->id,
        ]);

        $response->assertStatus(200);

        $doc = Document::where('name', 'laporan-mahasiswa')->first();
        $this->assertNotNull($doc);
        // Status must be submitted (waiting for review)
        $this->assertEquals(Document::STATUS_SUBMITTED, $doc->status);
        $this->assertEquals('yellow', $doc->getDisplayStatusColorForUser($adminProdi));
        $this->assertEquals('Menunggu ACC', $doc->getDisplayStatusLabelForUser($adminProdi));

        // 2. Other regular user CANNOT see the pending document
        $responseOther = $this->actingAs($otherUser)->get("/folders?folder_id={$folder->id}");
        $responseOther->assertStatus(200);
        $responseOther->assertDontSee('laporan-mahasiswa');

        // 3. Admin Prodi CAN see the pending document and sees "Menunggu ACC"
        $responseAdmin = $this->actingAs($adminProdi)->get("/folders?folder_id={$folder->id}");
        $responseAdmin->assertStatus(200);
        $responseAdmin->assertSee('laporan-mahasiswa');
        $responseAdmin->assertSee('Menunggu ACC');

        // 4. Admin Prodi approves (ACC) the document
        $responseApprove = $this->actingAs($adminProdi)->postJson("/approvals/{$doc->uuid}/approve", [
            'notes' => 'Dokumen sesuai standar',
        ]);
        $responseApprove->assertStatus(200);

        $doc->refresh();
        $this->assertEquals(Document::STATUS_APPROVED, $doc->status);
        $this->assertEquals('green', $doc->getDisplayStatusColorForUser($adminProdi));
        $this->assertEquals('Disetujui (ACC)', $doc->getDisplayStatusLabelForUser($adminProdi));

        // 5. Now other regular users CAN see the document
        $responseOtherAfter = $this->actingAs($otherUser)->get("/folders?folder_id={$folder->id}");
        $responseOtherAfter->assertStatus(200);
        $responseOtherAfter->assertSee('laporan-mahasiswa');
    }

    public function test_admin_prodi_upload_is_directly_approved_without_review(): void
    {
        Storage::fake('private');

        $adminProdi = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'department' => 'Program Studi Teknologi Informasi',
        ]);

        $regularUser = User::factory()->create([
            'role' => User::ROLE_USER,
            'department' => 'Program Studi Teknologi Informasi',
        ]);

        $folder = Folder::create([
            'name' => 'SK Mengajar',
            'department' => 'Program Studi Teknologi Informasi',
            'created_by' => $adminProdi->id,
        ]);

        $file = UploadedFile::fake()->create('sk-mengajar-2026.pdf', 300, 'application/pdf');

        // Admin Prodi uploads
        $response = $this->actingAs($adminProdi)->postJson('/folders/quick-upload', [
            'file' => $file,
            'folder_id' => $folder->id,
        ]);

        $response->assertStatus(200);

        $doc = Document::where('name', 'sk-mengajar-2026')->first();
        $this->assertNotNull($doc);
        // Directly approved!
        $this->assertEquals(Document::STATUS_APPROVED, $doc->status);
        $this->assertEquals($adminProdi->id, $doc->approved_by);
        $this->assertEquals('green', $doc->getDisplayStatusColorForUser($adminProdi));

        // Immediately visible to regular users
        $responseUser = $this->actingAs($regularUser)->get("/folders?folder_id={$folder->id}");
        $responseUser->assertStatus(200);
        $responseUser->assertSee('sk-mengajar-2026');
    }

    public function test_biro_shared_document_to_prodi_requires_prodi_admin_approval_before_visible_to_prodi_users(): void
    {
        $adminBiro = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'department' => 'Biro Akademik',
        ]);

        $adminProdi = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'department' => 'Program Studi Teknologi Informasi',
        ]);

        $userProdi = User::factory()->create([
            'role' => User::ROLE_USER,
            'department' => 'Program Studi Teknologi Informasi',
        ]);

        $category = Category::create([
            'name' => 'Pedoman Kurikulum',
            'slug' => 'pedoman-kurikulum',
            'is_active' => true,
        ]);

        $folderBiro = Folder::create([
            'name' => 'Pedoman Akademik Biro',
            'department' => 'Biro Akademik',
            'created_by' => $adminBiro->id,
        ]);

        // Biro uploads document (approved in Biro)
        $doc = Document::create([
            'name' => 'Pedoman MBKM Nasional',
            'category_id' => $category->id,
            'folder_id' => $folderBiro->id,
            'department' => 'Biro Akademik',
            'document_date' => now()->toDateString(),
            'display_date' => now()->toDateString(),
            'upload_date' => now()->toDateString(),
            'status' => Document::STATUS_APPROVED,
            'visibility' => Document::VISIBILITY_VIEWER,
            'created_by' => $adminBiro->id,
        ]);

        // Biro shares document to Program Studi Teknologi Informasi
        $responseShare = $this->actingAs($adminBiro)->postJson("/documents/{$doc->uuid}/share", [
            'shared_departments' => ['Program Studi Teknologi Informasi'],
        ]);
        $responseShare->assertStatus(200);

        $doc->refresh();
        $this->assertEquals(['Program Studi Teknologi Informasi'], $doc->shared_departments);
        $this->assertEquals('pending', $doc->prodi_approval_status);

        // 1. Admin Prodi sees document as Menunggu ACC (Kuning)
        $this->assertEquals('yellow', $doc->getDisplayStatusColorForUser($adminProdi));
        $this->assertEquals('Menunggu ACC', $doc->getDisplayStatusLabelForUser($adminProdi));
        $responseAdmin = $this->actingAs($adminProdi)->get("/folders?folder_id={$folderBiro->id}");
        $responseAdmin->assertStatus(200);
        $responseAdmin->assertSee('Pedoman MBKM Nasional');

        // 2. Prodi regular user CANNOT access or see the document yet
        $responseUserFolder = $this->actingAs($userProdi)->get("/folders?folder_id={$folderBiro->id}");
        $responseUserFolder->assertStatus(200);
        $responseUserFolder->assertDontSee('Pedoman MBKM Nasional');

        $responseUserDoc = $this->actingAs($userProdi)->get("/documents/{$doc->uuid}");
        $responseUserDoc->assertStatus(403);

        // 3. Admin Prodi approves (ACC) the shared document
        $responseApprove = $this->actingAs($adminProdi)->postJson("/approvals/{$doc->uuid}/approve", [
            'notes' => 'Di-ACC untuk mahasiswa & dosen PSTI',
        ]);
        $responseApprove->assertStatus(200);

        $doc->refresh();
        $this->assertEquals('approved', $doc->prodi_approval_status);
        $this->assertEquals('green', $doc->getDisplayStatusColorForUser($adminProdi));
        $this->assertEquals('Disetujui (ACC)', $doc->getDisplayStatusLabelForUser($adminProdi));

        // 4. Now Prodi regular user CAN see and access the document
        $responseUserAfter = $this->actingAs($userProdi)->get("/folders?folder_id={$folderBiro->id}");
        $responseUserAfter->assertStatus(200);
        $responseUserAfter->assertSee('Pedoman MBKM Nasional');

        $responseUserDocAfter = $this->actingAs($userProdi)->get("/documents/{$doc->uuid}");
        $responseUserDocAfter->assertStatus(200);
    }
}
