<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_when_accessing_users(): void
    {
        $response = $this->get('/users');
        $response->assertRedirect('/login');
    }

    public function test_regular_user_cannot_access_users_management(): void
    {
        $regularUser = User::factory()->create([
            'role' => User::ROLE_USER,
            'is_active' => true,
        ]);

        $response = $this->actingAs($regularUser)->get('/users');
        $response->assertStatus(403);
    }

    public function test_superadmin_can_view_all_users_and_create_any_role(): void
    {
        $superadmin = User::factory()->create([
            'role' => User::ROLE_SUPERADMIN,
            'is_active' => true,
        ]);

        $userPsti = User::factory()->create([
            'name' => 'Staf PSTI',
            'department' => 'Program Studi Teknologi Informasi',
            'role' => User::ROLE_USER,
        ]);

        $userAkademik = User::factory()->create([
            'name' => 'Staf Akademik',
            'department' => 'Biro Akademik',
            'role' => User::ROLE_USER,
        ]);

        $response = $this->actingAs($superadmin)->get('/users');
        $response->assertStatus(200);
        $response->assertSee('Staf PSTI');
        $response->assertSee('Staf Akademik');

        // Superadmin create user
        $responseCreate = $this->actingAs($superadmin)->post('/users', [
            'name' => 'Admin Baru',
            'email' => 'admin.baru@simdok.local',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => User::ROLE_ADMIN,
            'department' => 'Biro Akademik',
            'is_active' => 1,
        ]);

        $responseCreate->assertRedirect('/users');
        $this->assertDatabaseHas('users', [
            'email' => 'admin.baru@simdok.local',
            'department' => 'Biro Akademik',
        ]);
    }

    public function test_prodi_or_biro_admin_only_sees_users_in_their_own_department(): void
    {
        $adminPsti = User::factory()->create([
            'name' => 'Admin Prodi TI',
            'role' => User::ROLE_ADMIN,
            'department' => 'Program Studi Teknologi Informasi',
            'is_active' => true,
        ]);

        $userPsti = User::factory()->create([
            'name' => 'Bawahan PSTI',
            'role' => User::ROLE_USER,
            'department' => 'Program Studi Teknologi Informasi',
            'is_active' => true,
        ]);

        $userAkademik = User::factory()->create([
            'name' => 'Bawahan Akademik',
            'role' => User::ROLE_USER,
            'department' => 'Biro Akademik',
            'is_active' => true,
        ]);

        $response = $this->actingAs($adminPsti)->get('/users');
        $response->assertStatus(200);
        $response->assertSee('Bawahan PSTI');
        $response->assertDontSee('Bawahan Akademik');
    }

    public function test_prodi_or_biro_admin_creates_subordinate_locked_to_their_department(): void
    {
        $adminPsti = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'department' => 'Program Studi Teknologi Informasi',
            'is_active' => true,
        ]);

        // Coba kirim request tambah user bawahan
        $response = $this->actingAs($adminPsti)->post('/users', [
            'name' => 'Dosen Baru TI',
            'email' => 'dosen.ti@simdok.local',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => User::ROLE_USER,
            'department' => 'Biro Akademik', // Mencoba menyusupkan unit lain
            'is_active' => 1,
        ]);

        $response->assertRedirect('/users');

        // Harus tetap dipaksa ke unit admin (Program Studi Teknologi Informasi)
        $this->assertDatabaseHas('users', [
            'email' => 'dosen.ti@simdok.local',
            'department' => 'Program Studi Teknologi Informasi',
        ]);
    }

    public function test_prodi_or_biro_admin_cannot_create_superadmin(): void
    {
        $adminPsti = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'department' => 'Program Studi Teknologi Informasi',
            'is_active' => true,
        ]);

        $response = $this->actingAs($adminPsti)->post('/users', [
            'name' => 'Hacker Superadmin',
            'email' => 'hacker@simdok.local',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => User::ROLE_SUPERADMIN,
            'is_active' => 1,
        ]);

        $response->assertSessionHasErrors('role');
        $this->assertDatabaseMissing('users', [
            'email' => 'hacker@simdok.local',
        ]);
    }

    public function test_prodi_or_biro_admin_cannot_edit_or_delete_user_from_other_department(): void
    {
        $adminPsti = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'department' => 'Program Studi Teknologi Informasi',
            'is_active' => true,
        ]);

        $userAkademik = User::factory()->create([
            'role' => User::ROLE_USER,
            'department' => 'Biro Akademik',
            'is_active' => true,
        ]);

        // Edit user biro lain ditolak 403
        $responseEdit = $this->actingAs($adminPsti)->get("/users/{$userAkademik->id}/edit");
        $responseEdit->assertStatus(403);

        // Update user biro lain ditolak 403
        $responseUpdate = $this->actingAs($adminPsti)->put("/users/{$userAkademik->id}", [
            'name' => 'Nama Baru',
            'email' => $userAkademik->email,
            'role' => User::ROLE_USER,
        ]);
        $responseUpdate->assertStatus(403);

        // Hapus user biro lain ditolak 403
        $responseDelete = $this->actingAs($adminPsti)->delete("/users/{$userAkademik->id}");
        $responseDelete->assertStatus(403);
    }
}
