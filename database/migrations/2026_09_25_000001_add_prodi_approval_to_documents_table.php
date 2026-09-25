<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->string('prodi_approval_status')->nullable()->after('status')->index();
            $table->foreignId('prodi_approved_by')->nullable()->after('prodi_approval_status')->constrained('users')->onDelete('set null');
            $table->timestamp('prodi_approved_at')->nullable()->after('prodi_approved_by');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropForeign(['prodi_approved_by']);
            $table->dropColumn(['prodi_approval_status', 'prodi_approved_by', 'prodi_approved_at']);
        });
    }
};
