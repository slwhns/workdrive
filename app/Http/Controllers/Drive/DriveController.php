<?php

namespace App\Http\Controllers\Drive;

use App\Http\Controllers\Controller;
use App\Models\File;
use App\Services\Drive\FileService;
use App\Services\Drive\FolderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\Storage;

class DriveController extends Controller
{
    /**
     * Show the main drive/my files view (SPA index)
     */
    public function index(Request $request, FileService $fileService, FolderService $folderService)
    {
        $user = $request->user();
        $parentId = $request->query('folder_id');
        
        $currentFolder = null;
        $breadcrumbs = [];
        if ($parentId) {
            $currentFolder = File::where('id', $parentId)
                ->where('is_folder', true)
                ->first();
                
            if ($currentFolder) {
                // Access check: must be owner OR shared in shares table, or inside a shared folder
                $isOwner = $currentFolder->created_by === $user->id;
                $isShared = \App\Models\Share::where('file_id', $parentId)
                    ->where('shared_with', $user->id)
                    ->exists();
                
                if (!$isOwner && !$isShared) {
                    $hasSharedParent = false;
                    $temp = $currentFolder->parent;
                    while ($temp) {
                        if ($temp->created_by === $user->id) {
                            $hasSharedParent = true;
                            break;
                        }
                        if (\App\Models\Share::where('file_id', $temp->id)->where('shared_with', $user->id)->exists()) {
                            $hasSharedParent = true;
                            break;
                        }
                        $temp = $temp->parent;
                    }
                    if (!$hasSharedParent) {
                        abort(403, 'Unauthorized action.');
                    }
                }
                
                $breadcrumbs = $folderService->getFolderPath($currentFolder);
            } else {
                $parentId = null;
            }
        }

        if ($parentId && $currentFolder) {
            $folders = File::where('parent_id', $parentId)->where('is_folder', true)->get();
            $files = File::where('parent_id', $parentId)->where('is_folder', false)->get();
        } else {
            $folders = $fileService->getUserFolders($user, null);
            $files = $fileService->getUserFiles($user, null);
        }
        
        if ($request->wantsJson() || $request->query('json')) {
            return response()->json([
                'status' => 'success',
                'folders' => $folders,
                'files' => $files,
                'breadcrumbs' => $breadcrumbs,
                'currentFolder' => $currentFolder
            ]);
        }
        
        return view('drive.index', compact('folders', 'files', 'currentFolder', 'breadcrumbs'));
    }

    /**
     * Show files shared with the current user
     */
    public function shared(Request $request)
    {
        $user = $request->user();
        
        $shares = \App\Models\Share::where('shared_with', $user->id)
            ->with(['file.creator'])
            ->get();
            
        $sharedFiles = $shares->pluck('file')->filter(function ($file) {
            return $file && !$file->is_folder && !$file->trashed();
        })->values();
        
        $sharedFolders = $shares->pluck('file')->filter(function ($file) {
            return $file && $file->is_folder && !$file->trashed();
        })->values();
        
        if ($request->wantsJson() || $request->query('json')) {
            return response()->json([
                'status' => 'success',
                'folders' => $sharedFolders,
                'files' => $sharedFiles
            ]);
        }
        
        return view('drive.shared', [
            'folders' => $sharedFolders,
            'files' => $sharedFiles
        ]);
    }

    /**
     * Show trash/deleted files
     */
    public function trash(Request $request)
    {
        $user = $request->user();
        
        $files = File::onlyTrashed()
            ->where('created_by', $user->id)
            ->where('is_folder', false)
            ->get();
            
        $folders = File::onlyTrashed()
            ->where('created_by', $user->id)
            ->where('is_folder', true)
            ->get();
            
        if ($request->wantsJson() || $request->query('json')) {
            return response()->json([
                'status' => 'success',
                'folders' => $folders,
                'files' => $files
            ]);
        }
        
        return view('drive.trash', compact('folders', 'files'));
    }

    /**
     * Show search results
     */
    public function search(Request $request)
    {
        $user = $request->user();
        $query = $request->query('q');
        
        if (empty($query)) {
            $files = collect();
            $folders = collect();
        } else {
            $files = File::where('created_by', $user->id)
                ->where('is_folder', false)
                ->where('name', 'like', "%{$query}%")
                ->get();
                
            $folders = File::where('created_by', $user->id)
                ->where('is_folder', true)
                ->where('name', 'like', "%{$query}%")
                ->get();
        }
        
        if ($request->wantsJson() || $request->query('json')) {
            return response()->json([
                'status' => 'success',
                'folders' => $folders,
                'files' => $files,
                'query' => $query
            ]);
        }
        
        return view('drive.search', compact('folders', 'files', 'query'));
    }

    /**
     * Show recently modified or accessed files
     */
    public function recents(Request $request)
    {
        $user = $request->user();
        
        $files = File::where('created_by', $user->id)
            ->where('is_folder', false)
            ->orderBy('updated_at', 'desc')
            ->limit(20)
            ->get();
            
        $folders = File::where('created_by', $user->id)
            ->where('is_folder', true)
            ->orderBy('updated_at', 'desc')
            ->limit(10)
            ->get();
            
        if ($request->wantsJson() || $request->query('json')) {
            return response()->json([
                'status' => 'success',
                'folders' => $folders,
                'files' => $files
            ]);
        }
        
        return view('drive.recents', compact('folders', 'files'));
    }

    /**
     * Show starred/favourite files
     */
    public function starred(Request $request)
    {
        $user = $request->user();
        
        $files = File::where('created_by', $user->id)
            ->where('is_starred', true)
            ->where('is_folder', false)
            ->get();
            
        $folders = File::where('created_by', $user->id)
            ->where('is_starred', true)
            ->where('is_folder', true)
            ->get();
            
        if ($request->wantsJson() || $request->query('json')) {
            return response()->json([
                'status' => 'success',
                'folders' => $folders,
                'files' => $files
            ]);
        }
        
        return view('drive.starred', compact('folders', 'files'));
    }

    /**
     * Create a new folder from the sidebar menu or SPA modal
     */
    public function storeFolder(Request $request, FolderService $folderService)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', 'exists:files,id'],
        ]);

        $folder = $folderService->createFolder($request->user(), $validated['name'], $validated['parent_id'] ?? null);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Folder created successfully.',
                'folder' => $folder
            ]);
        }

        return redirect()->route('drive.index')->with('status', 'Folder created successfully.');
    }

    /**
     * Upload one or more files
     */
    public function uploadFiles(Request $request, FileService $fileService)
    {
        $validated = $request->validate([
            'files' => ['required', 'array', 'min:1'],
            'files.*' => ['file', 'max:51200'],
            'parent_id' => ['nullable', 'integer', 'exists:files,id'],
        ]);

        $createdFiles = [];
        foreach ($validated['files'] as $uploadedFile) {
            $storagePath = $uploadedFile->store('drive/uploads', 'public');

            $file = $fileService->createFile($request->user(), [
                'name' => $uploadedFile->getClientOriginalName(),
                'path' => $storagePath,
                'mime_type' => $uploadedFile->getClientMimeType(),
                'size' => $uploadedFile->getSize(),
                'storage_path' => $storagePath,
                'parent_id' => $validated['parent_id'] ?? null,
            ]);
            $createdFiles[] = $file;
        }

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Files uploaded successfully.',
                'files' => $createdFiles
            ]);
        }

        return redirect()->route('drive.index')->with('status', 'Files uploaded successfully.');
    }

    /**
     * Upload a folder as a collection of files, reconstructing directory tree.
     */
    public function uploadFolder(Request $request, FileService $fileService)
    {
        $validated = $request->validate([
            'files' => ['required', 'array', 'min:1'],
            'files.*' => ['file', 'max:51200'],
            'paths' => ['required', 'array', 'min:1'],
            'parent_id' => ['nullable', 'integer', 'exists:files,id'],
        ]);

        $user = $request->user();
        $rootParentId = $validated['parent_id'] ?? null;
        $createdFiles = [];

        // Key format: parentId_folderPath -> value: folder Database ID
        $folderCache = [];

        foreach ($validated['files'] as $index => $uploadedFile) {
            $relativePath = $validated['paths'][$index] ?? $uploadedFile->getClientOriginalName();
            
            // Standardize path separators
            $relativePath = str_replace('\\', '/', $relativePath);
            $pathSegments = explode('/', $relativePath);
            
            // Last segment is the file name
            $fileName = array_pop($pathSegments);
            
            $currentParentId = $rootParentId;
            $pathAccumulator = "";

            // Traverse and build dynamic folder structures if not exist
            foreach ($pathSegments as $segment) {
                if (empty($segment)) continue;
                
                $pathAccumulator .= ($pathAccumulator === "" ? "" : "/") . $segment;
                $cacheKey = ($currentParentId ?? 'root') . '_' . $pathAccumulator;

                if (isset($folderCache[$cacheKey])) {
                    $currentParentId = $folderCache[$cacheKey];
                } else {
                    $folder = File::where('created_by', $user->id)
                        ->where('parent_id', $currentParentId)
                        ->where('name', $segment)
                        ->where('is_folder', true)
                        ->first();

                    if (!$folder) {
                        $folder = File::create([
                            'name' => $segment,
                            'type' => 'folder',
                            'is_folder' => true,
                            'parent_id' => $currentParentId,
                            'created_by' => $user->id,
                        ]);
                    }

                    $currentParentId = $folder->id;
                    $folderCache[$cacheKey] = $currentParentId;
                }
            }

            // Store file under the finalized directory folder ID
            $storagePath = $uploadedFile->store('drive/folders', 'public');

            $file = $fileService->createFile($user, [
                'name' => $fileName,
                'path' => $storagePath,
                'mime_type' => $uploadedFile->getClientMimeType(),
                'size' => $uploadedFile->getSize(),
                'storage_path' => $storagePath,
                'parent_id' => $currentParentId,
            ]);
            $createdFiles[] = $file;
        }

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Folder uploaded successfully with matching directory structures.',
                'files' => $createdFiles
            ]);
        }

        return redirect()->route('drive.index')->with('status', 'Folder uploaded successfully.');
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

        $file = File::create([
            'name' => $template['name'],
            'path' => $storagePath,
            'type' => 'file',
            'mime_type' => $template['mime'],
            'size' => 0,
            'storage_path' => $storagePath,
            'created_by' => $request->user()->id,
            'is_folder' => false,
            'parent_id' => $request->query('parent_id') ?? null
        ]);

        $redirectUrl = route('drive.index', array_filter([
            'folder_id' => $file->parent_id,
            'open_file_id' => $file->id
        ]));

        return redirect($redirectUrl)->with('status', ucfirst($kind) . ' created successfully.');
    }

    /**
     * Star/unstar a file
     */
    public function toggleStar(Request $request, File $file): JsonResponse
    {
        abort_unless($file->created_by === $request->user()->id, 403);
        
        $file->is_starred = !$file->is_starred;
        $file->save();
        
        return response()->json([
            'status' => 'success',
            'message' => $file->is_starred ? 'File starred successfully.' : 'File unstarred successfully.',
            'is_starred' => $file->is_starred
        ]);
    }

    /**
     * Rename a file or folder
     */
    public function rename(Request $request, File $file): JsonResponse
    {
        abort_unless($file->created_by === $request->user()->id, 403);
        
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);
        
        $file->name = $validated['name'];
        $file->save();
        
        return response()->json([
            'status' => 'success',
            'message' => 'Item renamed successfully.',
            'file' => $file
        ]);
    }

    /**
     * Soft delete a file (move to trash)
     */
    public function destroy(Request $request, File $file): JsonResponse
    {
        abort_unless($file->created_by === $request->user()->id, 403);
        
        $file->delete();
        
        return response()->json([
            'status' => 'success',
            'message' => 'Moved to Trash successfully.'
        ]);
    }

    /**
     * Restore a file from trash
     */
    public function restore(Request $request, $id): JsonResponse
    {
        $file = File::withTrashed()->findOrFail($id);
        abort_unless($file->created_by === $request->user()->id, 403);
        
        $file->restore();
        
        return response()->json([
            'status' => 'success',
            'message' => 'Item restored successfully.'
        ]);
    }

    /**
     * Permanently delete a file
     */
    public function forceDelete(Request $request, $id): JsonResponse
    {
        $file = File::withTrashed()->findOrFail($id);
        abort_unless($file->created_by === $request->user()->id, 403);
        
        if (!$file->is_folder && $file->storage_path) {
            Storage::disk('public')->delete($file->storage_path);
        }
        
        $file->forceDelete();
        
        return response()->json([
            'status' => 'success',
            'message' => 'Permanently deleted successfully.'
        ]);
    }

    /**
     * Download a file
     */
    public function download(Request $request, File $file)
    {
        $isOwner = $file->created_by === $request->user()->id;
        $isShared = \App\Models\Share::where('file_id', $file->id)
            ->where('shared_with', $request->user()->id)
            ->exists();
            
        abort_unless($isOwner || $isShared, 403);
        abort_if($file->is_folder, 400, 'Cannot download folders directly.');
        
        if (!Storage::disk('public')->exists($file->storage_path)) {
            abort(404, 'File does not exist on storage.');
        }
        
        return Storage::disk('public')->download($file->storage_path, $file->name);
    }

    /**
     * View a file inline in the browser (specifically PDFs)
     */
    public function inline(Request $request, File $file)
    {
        $isOwner = $file->created_by === $request->user()->id;
        $isShared = \App\Models\Share::where('file_id', $file->id)
            ->where('shared_with', $request->user()->id)
            ->exists();
            
        abort_unless($isOwner || $isShared, 403);
        abort_if($file->is_folder, 400, 'Cannot view folders inline.');
        
        if (!Storage::disk('public')->exists($file->storage_path)) {
            abort(404, 'File does not exist on storage.');
        }
        
        $path = Storage::disk('public')->path($file->storage_path);
        
        return response()->file($path, [
            'Content-Type' => $file->mime_type ?? 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . basename($file->name) . '"'
        ]);
    }

    /**
     * Share a file with another user
     */
    public function share(Request $request, File $file): JsonResponse
    {
        abort_unless($file->created_by === $request->user()->id, 403);
        
        $validated = $request->validate([
            'email' => 'required|email|exists:users,email',
            'permission' => 'required|in:view,edit',
        ]);
        
        $sharedWithUser = \App\Models\User::where('email', $validated['email'])->firstOrFail();
        
        if ($sharedWithUser->id === $request->user()->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'You cannot share files with yourself.'
            ], 422);
        }
        
        \App\Models\Share::updateOrCreate([
            'file_id' => $file->id,
            'shared_with' => $sharedWithUser->id,
        ], [
            'shared_by' => $request->user()->id,
            'permission' => $validated['permission'],
        ]);
        
        $file->is_shared = true;
        $file->save();
        
        return response()->json([
            'status' => 'success',
            'message' => 'Shared with ' . $sharedWithUser->name . ' (' . $sharedWithUser->email . ') successfully.'
        ]);
    }
}
