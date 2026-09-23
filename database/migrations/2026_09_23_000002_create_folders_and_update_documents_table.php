<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Create folders table
        Schema::create('folders', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('parent_id')->nullable()->constrained('folders')->onDelete('cascade');
            $table->string('department')->nullable(); // Biro / Jurusan ownership
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->index('parent_id');
            $table->index('department');
        });

        // 2. Add folder_id, visibility, display_date, actual_uploaded_at, is_downloadable to documents
        Schema::table('documents', function (Blueprint $table) {
            $table->foreignId('folder_id')->nullable()->after('category_id')->constrained('folders')->onDelete('set null');
            $table->string('visibility')->default('viewer')->after('status'); // viewer, internal, private
            $table->date('display_date')->nullable()->after('document_date');
            $table->timestamp('actual_uploaded_at')->nullable()->after('original_uploaded_at');
            $table->boolean('is_downloadable')->default(true)->after('visibility');

            $table->index('folder_id');
            $table->index('visibility');
            $table->index('display_date');
        });

        // Copy existing dates
        DB::statement('UPDATE documents SET display_date = document_date WHERE display_date IS NULL');
        DB::statement('UPDATE documents SET actual_uploaded_at = original_uploaded_at WHERE actual_uploaded_at IS NULL');
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropForeign(['folder_id']);
            $table->dropColumn(['folder_id', 'visibility', 'display_date', 'actual_uploaded_at', 'is_downloadable']);
        });

        Schema::dropIfExists('folders');
    }
};
