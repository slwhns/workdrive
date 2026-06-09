<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Drive\DriveController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Office\OnlyOfficeController;
use App\Http\Controllers\Preview\FilePreviewController;

// Public routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});

// OnlyOffice Public callback and signed download routes (No auth session checks)
Route::get('/office/download/{file}', [OnlyOfficeController::class, 'download'])->name('onlyoffice.download');
Route::post('/office/callback', [OnlyOfficeController::class, 'callback'])->name('onlyoffice.callback');

// Public Share routes
Route::get('/s/{token}', [DriveController::class, 'showPublicShare'])->name('drive.public.share');
Route::post('/s/{token}/password', [DriveController::class, 'verifyPublicSharePassword'])->name('drive.public.share.password');
Route::get('/s/{token}/download', [DriveController::class, 'downloadPublicShare'])->name('drive.public.share.download');
Route::get('/s/{token}/file/{subfile}/download', [DriveController::class, 'downloadPublicShareSubfile'])->name('drive.public.share.subfile.download');
Route::get('/s/{token}/file/{subfile}/inline', [DriveController::class, 'inlinePublicShareSubfile'])->name('drive.public.share.subfile.inline');
Route::post('/s/{token}/import', [DriveController::class, 'importPublicShare'])->name('drive.public.share.import');
Route::post('/s/{token}/file/{subfile}/import', [DriveController::class, 'importPublicShareSubfile'])->name('drive.public.share.subfile.import');

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
    
    // OnlyOffice Config route
    Route::get('/office/config/{file}', [OnlyOfficeController::class, 'getConfig'])->name('onlyoffice.config');
    Route::get('/drive/files/{file}/thumbnail', [OnlyOfficeController::class, 'thumbnail'])->name('drive.files.thumbnail');
    
    // SPA File Operations
    Route::get('/drive/folders-list', [DriveController::class, 'allFolders'])->name('drive.folders.all');
    Route::post('/drive/files/{file}/move', [DriveController::class, 'move'])->name('drive.files.move');
    Route::post('/drive/files/{file}/star', [DriveController::class, 'toggleStar'])->name('drive.files.star');
    Route::post('/drive/files/{file}/rename', [DriveController::class, 'rename'])->name('drive.files.rename');
    Route::delete('/drive/files/{file}', [DriveController::class, 'destroy'])->name('drive.files.destroy');
    Route::post('/drive/files/{file}/restore', [DriveController::class, 'restore'])->name('drive.files.restore');
    Route::delete('/drive/files/{file}/force', [DriveController::class, 'forceDelete'])->name('drive.files.forceDelete');
    Route::get('/drive/files/{file}/download', [DriveController::class, 'download'])->name('drive.files.download');
    Route::get('/drive/files/{file}/inline', [DriveController::class, 'inline'])->name('drive.files.inline');
    Route::post('/drive/files/{file}/share', [DriveController::class, 'share'])->name('drive.files.share');
    Route::get('/drive/files/{file}/shares', [DriveController::class, 'getShares'])->name('drive.shares.get');
    Route::post('/drive/files/{file}/public-link', [DriveController::class, 'togglePublicLink'])->name('drive.shares.public-link');
    Route::put('/drive/files/{file}/public-link-settings', [DriveController::class, 'updatePublicLinkSettings'])->name('drive.shares.public-link-settings');
    Route::put('/drive/files/{file}/shares/{share}', [DriveController::class, 'updateSharePermission'])->name('drive.shares.update');
    Route::delete('/drive/files/{file}/shares/{share}', [DriveController::class, 'revokeShare'])->name('drive.shares.revoke');
    Route::get('/tags/{tag}', [DriveController::class, 'tag'])->name('drive.tag');
    Route::get('/drive/tags-list', [DriveController::class, 'allTags'])->name('drive.tags.all');
    Route::post('/drive/files/{file}/tags', [DriveController::class, 'updateTags'])->name('drive.files.tags.update');
    
    // File Preview routes
    Route::get('/preview/{file}', [FilePreviewController::class, 'show'])->name('preview.show');
    Route::get('/api/preview/{file}', [FilePreviewController::class, 'getPreviewData'])->name('api.preview.data');
    
    // Profile routes
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    
    // Logout
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
});  