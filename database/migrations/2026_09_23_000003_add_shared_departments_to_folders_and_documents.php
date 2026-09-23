<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('folders', function (Blueprint $table) {
            $table->json('shared_departments')->nullable()->after('description');
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->json('shared_departments')->nullable()->after('visibility');
        });
    }

    public function down(): void
    {
        Schema::table('folders', function (Blueprint $table) {
            $table->dropColumn('shared_departments');
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn('shared_departments');
        });
    }
};
