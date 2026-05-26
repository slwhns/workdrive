<?php

namespace App\Http\Controllers\Drive;

use App\Http\Controllers\Controller;
use App\Models\File;
use App\Services\Drive\FileService;
use App\Services\Drive\FolderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\Storage;

class DriveController extends Controller
{
    /**
     * Show the main drive/my files view
     */
    public function index(): View
    {
        return view('drive.index');
    }

    /**
     * Show files shared with the current user
     */
    public function shared(): View
    {
        return view('drive.shared');
    }

    /**
     * Show trash/deleted files
     */
    public function trash(): View
    {
        return view('drive.trash');
    }

    /**
     * Show search results
     */
    public function search(): View
    {
        return view('drive.search');
    }

    /**
     * Show recently modified or accessed files
     */
    public function recents(): View
    {
        return view('drive.recents');
    }

    /**
     * Show starred/favourite files
     */
    public function starred(): View
    {
        return view('drive.starred');
    }

    /**
     * Create a new folder from the sidebar menu
     */
    public function storeFolder(Request $request, FolderService $folderService): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $folderService->createFolder($request->user(), $validated['name']);

        return redirect()->route('drive.index')->with('status', 'Folder created successfully.');
    }

    /**
     * Upload one or more files from the sidebar menu
     */
    public function uploadFiles(Request $request, FileService $fileService): RedirectResponse
    {
        $validated = $request->validate([
            'files' => ['required', 'array', 'min:1'],
            'files.*' => ['file', 'max:51200'],
        ]);

        foreach ($validated['files'] as $uploadedFile) {
            $storagePath = $uploadedFile->store('drive/uploads', 'public');

            $fileService->createFile($request->user(), [
                'name' => $uploadedFile->getClientOriginalName(),
                'path' => $storagePath,
                'mime_type' => $uploadedFile->getClientMimeType(),
                'size' => $uploadedFile->getSize(),
                'storage_path' => $storagePath,
            ]);
        }

        return redirect()->route('drive.index')->with('status', 'Files uploaded successfully.');
    }

    /**
     * Upload a folder as a collection of files.
     *
     * Browsers submit the folder contents as a batch of files.
     */
    public function uploadFolder(Request $request, FileService $fileService): RedirectResponse
    {
        $validated = $request->validate([
            'folder_files' => ['required', 'array', 'min:1'],
            'folder_files.*' => ['file', 'max:51200'],
        ]);

        foreach ($validated['folder_files'] as $uploadedFile) {
            $storagePath = $uploadedFile->store('drive/folders', 'public');

            $fileService->createFile($request->user(), [
                'name' => $uploadedFile->getClientOriginalName(),
                'path' => $storagePath,
                'mime_type' => $uploadedFile->getClientMimeType(),
                'size' => $uploadedFile->getSize(),
                'storage_path' => $storagePath,
            ]);
        }

        return redirect()->route('drive.index')->with('status', 'Folder contents uploaded successfully.');
    }

    /**
     * Create a blank Office file record for the selected template type.
     */
    public function createOfficeFile(Request $request, string $kind): RedirectResponse
    {
        $templates = [
            'document' => [
                'name' => 'New Document.docx',
                'mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ],
            'spreadsheet' => [
                'name' => 'New Spreadsheet.xlsx',
                'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ],
            'presentation' => [
                'name' => 'New Presentation.pptx',
                'mime' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            ],
        ];

        abort_unless(isset($templates[$kind]), 404);

        $template = $templates[$kind];
        $storagePath = 'drive/onlyoffice/' . $kind . '/' . str_replace(' ', '-', strtolower($template['name']));

        Storage::disk('public')->put($storagePath, '');

        File::create([
            'name' => $template['name'],
            'path' => $storagePath,
            'type' => 'file',
            'mime_type' => $template['mime'],
            'size' => 0,
            'storage_path' => $storagePath,
            'created_by' => $request->user()->id,
            'is_folder' => false,
        ]);

        return redirect()->route('drive.index')->with('status', ucfirst($kind) . ' created successfully.');
    }
}
