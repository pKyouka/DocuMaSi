<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->whereIn('department', ['Program Studi PSTI', 'PSTI'])
            ->update(['department' => 'Program Studi Teknologi Informasi']);

        DB::table('folders')
            ->where('name', 'Program Studi PSTI')
            ->update(['name' => 'Program Studi Teknologi Informasi']);

        DB::table('folders')
            ->whereIn('department', ['Program Studi PSTI', 'PSTI'])
            ->update(['department' => 'Program Studi Teknologi Informasi']);

        DB::table('documents')
            ->whereIn('department', ['Program Studi PSTI', 'PSTI'])
            ->update(['department' => 'Program Studi Teknologi Informasi']);
    }

    public function down(): void
    {
    }
};
