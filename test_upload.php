<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\File;
use App\Http\Controllers\Drive\DriveController;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use App\Services\Drive\FileService;

$user = User::first();
if (!$user) {
    $user = User::factory()->create();
}

// clear files
File::query()->forceDelete();

// Create fake request
$request = Request::create('/uploads/folder', 'POST', [
    'paths' => ['MyUploadFolder/test1.txt', 'MyUploadFolder/sub/test2.txt']
]);
$request->setUserResolver(function () use ($user) {
    return $user;
});

// Mock files
$file1 = UploadedFile::fake()->create('test1.txt', 10);
$file2 = UploadedFile::fake()->create('test2.txt', 20);

$request->files->set('files', [$file1, $file2]);

$controller = new DriveController();
$service = app(FileService::class);
$response = $controller->uploadFolder($request, $service);

echo "Response:\n";
echo $response->getContent() . "\n";

echo "Files in DB:\n";
foreach (File::all() as $f) {
    echo "ID: {$f->id}, Name: {$f->name}, Parent: {$f->parent_id}, IsFolder: {$f->is_folder}\n";
}
