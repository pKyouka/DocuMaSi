<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\FolderController;
use App\Http\Controllers\GoogleDriveController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use App\Models\User;

// Semua Halaman dan Fitur Wajib Login (auth + active)
Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/', [FolderController::class, 'index'])->name('home');
    Route::get('/dashboard', fn() => redirect()->route('home'))->name('dashboard');
    Route::get('folders', [FolderController::class, 'index'])->name('folders.index');
    Route::get('search', [SearchController::class, 'index'])->name('search.index');
    Route::get('viewer', [SearchController::class, 'index'])->name('viewer.index');

    Route::get('google-drive', [GoogleDriveController::class, 'index'])->name('google-drive.index');
    Route::get('google-drive/connect', [GoogleDriveController::class, 'connect'])->name('google-drive.connect');
    Route::post('google-drive/disconnect', [GoogleDriveController::class, 'disconnect'])->name('google-drive.disconnect');
    Route::post('google-drive/import', [GoogleDriveController::class, 'import'])->name('google-drive.import');
    Route::post('google-drive/create-folder', [GoogleDriveController::class, 'createFolder'])->name('google-drive.create-folder');
    Route::post('google-drive/import-folder', [GoogleDriveController::class, 'importFolder'])->name('google-drive.import-folder');
    Route::get('google-drive/stream/{fileId}', [GoogleDriveController::class, 'stream'])->name('google-drive.stream');

    Route::get('documents/{document}', [DocumentController::class, 'show'])->name('documents.show');
    Route::get('documents/{document}/preview/{version?}', [DocumentController::class, 'preview'])->name('documents.preview');
    Route::get('documents/{document}/download/{version?}', [DocumentController::class, 'download'])->name('documents.download');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Folders (Google Drive Style Folder Explorer - Protected Actions)
    Route::post('folders', [FolderController::class, 'store'])->name('folders.store');
    Route::put('folders/{folder}', [FolderController::class, 'update'])->name('folders.update');
    Route::delete('folders/{folder}', [FolderController::class, 'destroy'])->name('folders.destroy');
    Route::post('folders/{folder}/share', [FolderController::class, 'updateSharing'])->name('folders.share');
    Route::post('folders/quick-upload', [FolderController::class, 'quickUpload'])->name('folders.quick-upload');
    Route::post('documents/{document}/move', [FolderController::class, 'moveDocument'])->name('documents.move');

    // Documents (Protected methods)
    Route::resource('documents', DocumentController::class)->except(['index', 'show']);
    Route::post('documents/{document}/version', [DocumentController::class, 'uploadVersion'])->name('documents.version');
    Route::post('documents/{document}/archive', [DocumentController::class, 'archive'])->name('documents.archive');
    Route::post('documents/{document}/share', [DocumentController::class, 'updateSharing'])->name('documents.share');
    Route::post('documents/{document}/display-date', [DocumentController::class, 'updateDisplayDate'])->name('documents.display-date');

    // Manajemen Pengguna (Superadmin & Admin Unit/Prodi)
    Route::resource('users', UserController::class)->except(['show']);
});

Route::get('google-drive/callback', [GoogleDriveController::class, 'callback'])->middleware(['auth', 'active'])->name('google-drive.callback');

require __DIR__.'/auth.php';
