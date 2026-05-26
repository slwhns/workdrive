<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Drive\DriveController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\ProfileController;

// Public routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});

// Authenticated routes - Drive
Route::middleware(['auth'])->group(function () {
    Route::get('/', [DriveController::class, 'index'])->name('drive.index');
    Route::get('/drive', [DriveController::class, 'index'])->name('drive.list');
    Route::get('/shared', [DriveController::class, 'shared'])->name('drive.shared');
    Route::get('/trash', [DriveController::class, 'trash'])->name('drive.trash');
    Route::get('/search', [DriveController::class, 'search'])->name('drive.search');
    Route::get('/recents', [DriveController::class, 'recents'])->name('drive.recents');
    Route::get('/starred', [DriveController::class, 'starred'])->name('drive.starred');
    Route::post('/folders', [DriveController::class, 'storeFolder'])->name('drive.folders.store');
    Route::post('/uploads/files', [DriveController::class, 'uploadFiles'])->name('drive.upload.files');
    Route::post('/uploads/folder', [DriveController::class, 'uploadFolder'])->name('drive.upload.folder');
    Route::post('/office/{kind}', [DriveController::class, 'createOfficeFile'])
        ->whereIn('kind', ['document', 'spreadsheet', 'presentation'])
        ->name('drive.office.create');
    
    // Profile routes
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    
    // Logout
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
});  