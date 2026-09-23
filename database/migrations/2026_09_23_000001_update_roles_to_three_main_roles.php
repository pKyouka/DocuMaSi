<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->where('role', 'super_admin')->update(['role' => 'superadmin']);
        DB::table('users')->whereIn('role', ['biro', 'reviewer'])->update(['role' => 'admin']);
    }

    public function down(): void
    {
        DB::table('users')->where('role', 'superadmin')->update(['role' => 'super_admin']);
    }
};
