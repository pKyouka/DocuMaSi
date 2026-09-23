<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\FolderController;
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

    // Superadmin Only: Manajemen Pengguna
    Route::middleware('role:'.User::ROLE_SUPERADMIN)->group(function () {
        Route::resource('users', UserController::class)->except(['show']);
    });
});

require __DIR__.'/auth.php';
