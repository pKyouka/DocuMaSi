<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Folder;
use App\Models\User;
use App\Models\Document;
use App\Models\DocumentVersion;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create Super Admin
        $superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => 'admin@simdok.local',
            'password' => Hash::make('password'),
            'role' => User::ROLE_SUPERADMIN,
            'department' => 'Pusat',
            'is_active' => true,
        ]);

        // 2. Create Admin per Biro, Badan, Lembaga, dan Prodi
        $unitAdminsConfig = [
            'Biro Akademik' => 'admin.akademik@simdok.local',
            'Biro Penjaminan Mutu' => 'admin.mutu@simdok.local',
            'Biro Kemahasiswaan dan Alumni' => 'admin.kemahasiswaan@simdok.local',
            'Biro Aset dan Umum' => 'admin.aset@simdok.local',
            'Lembaga Pengkajian dan Pengamalan Islam' => 'admin.lppi@simdok.local',
            'UPT Perpustakaan' => 'admin.perpustakaan@simdok.local',
            'Badan Perencanaan dan Pengembangan (BPP)' => 'admin.bpp@simdok.local',
            'Lembaga Penelitian dan Pengabdian kepada Masyarakat' => 'admin.lppm@simdok.local',
            'Badan Pengembangan Teknologi dan Sistem Informasi' => 'admin.bptsi@simdok.local',
            'UPT Laboratiorium' => 'admin.lab@simdok.local',
            'Biro Humas dan Protokol' => 'admin.humas@simdok.local',
            'Biro Kerjasama dan Urusan Internasional' => 'admin.bkui@simdok.local',
            'Program Studi PSTI' => 'admin.psti@simdok.local',
        ];

        $adminUsers = [];
        foreach ($unitAdminsConfig as $unitName => $email) {
            $dept = $unitName === 'Program Studi PSTI' ? 'PSTI' : $unitName;
            $adminUsers[$unitName] = User::create([
                'name' => "Admin {$unitName}",
                'email' => $email,
                'password' => Hash::make('password'),
                'role' => User::ROLE_ADMIN,
                'department' => $dept,
                'is_active' => true,
            ]);
        }

        $admin = $adminUsers['Program Studi PSTI'];
        $reviewer = $admin;

        // 3. Create Regular User / Viewer
        $dosen = User::create([
            'name' => 'Dosen / Pengguna PSTI',
            'email' => 'user@simdok.local',
            'password' => Hash::make('password'),
            'role' => User::ROLE_USER,
            'department' => 'PSTI',
            'is_active' => true,
        ]);

        // Create Categories
        $categories = [
            'RPS',
            'Portofolio Mata Kuliah',
            'Kurikulum',
            'Tracer Study',
            'Penelitian',
            'Pengabdian',
            'Akreditasi',
            'Audit Mutu',
            'Surat',
            'SK',
            'Undangan',
            'Notulen',
            'Berita Acara',
            'Bukti Kegiatan',
            'Kerja Sama',
            'Sertifikat',
            'Laporan'
        ];

        foreach ($categories as $index => $category) {
            Category::create([
                'name' => $category,
                'slug' => Str::slug($category),
                'is_active' => true,
                'sort_order' => $index + 1
            ]);
        }

        $rpsCategory = Category::where('name', 'RPS')->first();
        $skCategory = Category::where('name', 'SK')->first();
        $laporanCategory = Category::firstOrCreate(['name' => 'Laporan'], ['slug' => 'laporan', 'is_active' => true, 'sort_order' => 17]);
        $pedomanCategory = Category::firstOrCreate(['name' => 'Pedoman'], ['slug' => 'pedoman', 'is_active' => true, 'sort_order' => 18]);

        // Create Root Folders for all Biros
        $biroFolders = [];
        foreach (User::BIROS as $biroName) {
            $biroFolders[$biroName] = Folder::create([
                'name' => $biroName,
                'parent_id' => null,
                'department' => $biroName,
                'description' => "Folder dokumen resmi {$biroName}",
                'created_by' => $superAdmin->id,
            ]);
        }

        // Program Studi Root Folder
        $prodiFolder = Folder::create([
            'name' => 'Program Studi PSTI',
            'parent_id' => null,
            'department' => 'PSTI',
            'description' => 'Folder dokumen Program Studi PSTI',
            'created_by' => $superAdmin->id,
        ]);

        // Subfolders for Biro Akademik (from Diagram)
        $subSuratAkademik = Folder::create(['name' => 'Surat', 'parent_id' => $biroFolders['Biro Akademik']->id, 'department' => 'Biro Akademik', 'created_by' => $superAdmin->id]);
        $subPedomanAkademik = Folder::create(['name' => 'Pedoman', 'parent_id' => $biroFolders['Biro Akademik']->id, 'department' => 'Biro Akademik', 'created_by' => $superAdmin->id]);
        $subKalenderAkademik = Folder::create(['name' => 'Kalender Akademik', 'parent_id' => $biroFolders['Biro Akademik']->id, 'department' => 'Biro Akademik', 'created_by' => $superAdmin->id]);

        // Subfolders for Program Studi (from Diagram)
        $subRpsProdi = Folder::create(['name' => 'RPS', 'parent_id' => $prodiFolder->id, 'department' => 'PSTI', 'created_by' => $admin->id]);
        $subKurikulumProdi = Folder::create(['name' => 'Kurikulum', 'parent_id' => $prodiFolder->id, 'department' => 'PSTI', 'created_by' => $admin->id]);
        $subAkreditasiProdi = Folder::create(['name' => 'Akreditasi', 'parent_id' => $prodiFolder->id, 'department' => 'PSTI', 'created_by' => $admin->id]);

        // Create Sample Documents (based on Diagram)
        
        // 1. Pedoman Akademik (Biro Akademik)
        Document::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Pedoman Akademik 2026',
            'document_number' => 'PED/01/BAA/2026',
            'category_id' => $pedomanCategory->id,
            'folder_id' => $subPedomanAkademik->id,
            'department' => 'Biro Akademik',
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'pic' => 'Admin Biro Akademik',
            'description' => 'Buku pedoman peraturan akademik mahasiswa tahun 2026/2027.',
            'document_date' => '2026-07-23',
            'display_date' => '2026-07-23',
            'upload_date' => '2026-07-23',
            'original_uploaded_at' => now()->subMonths(2),
            'actual_uploaded_at' => now()->subMonths(2),
            'status' => Document::STATUS_APPROVED,
            'visibility' => Document::VISIBILITY_VIEWER,
            'is_downloadable' => true,
            'current_version' => 1,
            'created_by' => $superAdmin->id,
            'approved_by' => $superAdmin->id,
            'approved_at' => now()->subMonths(2),
        ]);

        // 2. Draft RPS Pemrograman Web (PSTI)
        Document::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Draft RPS Pemrograman Web',
            'document_number' => 'RPS-PW-2026',
            'category_id' => $rpsCategory->id,
            'folder_id' => $subRpsProdi->id,
            'department' => 'PSTI',
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'course_name' => 'Pemrograman Web',
            'pic' => 'Dosen Pengajar',
            'document_date' => '2026-09-01',
            'display_date' => '2026-09-01',
            'upload_date' => '2026-09-02',
            'original_uploaded_at' => now(),
            'actual_uploaded_at' => now(),
            'status' => Document::STATUS_DRAFT,
            'visibility' => Document::VISIBILITY_INTERNAL,
            'is_downloadable' => true,
            'current_version' => 1,
            'created_by' => $dosen->id,
        ]);

        // 3. RPS Basis Data (PSTI - Published to Viewer)
        Document::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'RPS Basis Data',
            'document_number' => 'RPS-BD-2026',
            'category_id' => $rpsCategory->id,
            'folder_id' => $subRpsProdi->id,
            'department' => 'PSTI',
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'course_name' => 'Basis Data',
            'pic' => 'Dosen Pengajar',
            'document_date' => '2026-09-05',
            'display_date' => '2026-09-05',
            'upload_date' => '2026-09-06',
            'original_uploaded_at' => now(),
            'actual_uploaded_at' => now(),
            'status' => Document::STATUS_APPROVED,
            'visibility' => Document::VISIBILITY_VIEWER,
            'is_downloadable' => true,
            'current_version' => 1,
            'created_by' => $dosen->id,
            'approved_by' => $admin->id,
            'approved_at' => now(),
        ]);

        // 4. SK Mengajar Semester Ganjil 2026
        Document::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'SK Mengajar Semester Ganjil 2026',
            'document_number' => 'SK/001/PSTI/2026',
            'category_id' => $skCategory->id,
            'folder_id' => $subRpsProdi->id,
            'department' => 'PSTI',
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'pic' => 'Admin PSTI',
            'document_date' => '2026-08-20',
            'display_date' => '2026-08-20',
            'upload_date' => '2026-08-21',
            'original_uploaded_at' => now()->subMonth(),
            'actual_uploaded_at' => now()->subMonth(),
            'status' => Document::STATUS_APPROVED,
            'visibility' => Document::VISIBILITY_VIEWER,
            'is_downloadable' => true,
            'current_version' => 1,
            'created_by' => $admin->id,
            'approved_by' => $reviewer->id,
            'approved_at' => now()->subWeeks(3),
        ]);

        // 5. Archived Document
        Document::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'SK Mengajar Semester Ganjil 2025',
            'document_number' => 'SK/001/PSTI/2025',
            'category_id' => $skCategory->id,
            'folder_id' => $subRpsProdi->id,
            'department' => 'PSTI',
            'academic_year' => '2025/2026',
            'semester' => 'Ganjil',
            'pic' => 'Admin PSTI',
            'document_date' => '2025-08-20',
            'display_date' => '2025-08-20',
            'upload_date' => '2025-08-21',
            'original_uploaded_at' => now()->subYear(),
            'actual_uploaded_at' => now()->subYear(),
            'status' => Document::STATUS_ARCHIVED,
            'visibility' => Document::VISIBILITY_INTERNAL,
            'is_downloadable' => false,
            'current_version' => 1,
            'created_by' => $admin->id,
            'approved_by' => $reviewer->id,
            'approved_at' => now()->subYear()->addDays(5),
            'archived_at' => now(),
        ]);
    }
}
