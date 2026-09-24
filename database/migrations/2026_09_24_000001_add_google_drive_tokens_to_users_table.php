<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('google_drive_access_token')->nullable();
            $table->text('google_drive_refresh_token')->nullable();
            $table->timestamp('google_drive_token_expires_at')->nullable();
            $table->string('google_drive_account_email')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'google_drive_access_token',
                'google_drive_refresh_token',
                'google_drive_token_expires_at',
                'google_drive_account_email',
            ]);
        });
    }
};
