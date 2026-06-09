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
                $currentFolder->update(['accessed_at' => now()]);
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
        
        $this->purgeExpiredTrash($user->id);
        
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

    public function recents(Request $request)
    {
        $user = $request->user();
        
        $files = File::where('created_by', $user->id)
            ->where('is_folder', false)
            ->orderBy('accessed_at', 'desc')
            ->limit(20)
            ->get();
            
        $folders = File::where('created_by', $user->id)
            ->where('is_folder', true)
            ->orderBy('accessed_at', 'desc')
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
        $parentId = $request->query('parent_id') ?? null;
        $userId = $request->user()->id;

        $uniqueName = $this->getUniqueFileName($parentId, $userId, $template['name']);
        $storagePath = 'drive/onlyoffice/' . $kind . '/' . uniqid() . '_' . str_replace(' ', '-', strtolower($uniqueName));

        Storage::disk('public')->put($storagePath, '');

        $file = File::create([
            'name' => $uniqueName,
            'path' => $storagePath,
            'type' => 'file',
            'mime_type' => $template['mime'],
            'size' => 0,
            'storage_path' => $storagePath,
            'created_by' => $userId,
            'is_folder' => false,
            'parent_id' => $parentId
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
        
        $newName = $validated['name'];
        
        if (!$file->is_folder) {
            $originalExt = pathinfo($file->name, PATHINFO_EXTENSION);
            if ($originalExt !== '') {
                // Check if the new name already ends with the original extension (case-insensitive)
                $pattern = '/\.' . preg_quote($originalExt, '/') . '$/i';
                if (!preg_match($pattern, $newName)) {
                    // If the new name has a different extension, strip it
                    $newExt = pathinfo($newName, PATHINFO_EXTENSION);
                    if ($newExt !== '') {
                        $newName = substr($newName, 0, -(strlen($newExt) + 1));
                    }
                    $newName = $newName . '.' . $originalExt;
                }
            }
        }
        
        $file->name = $newName;
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
        
        $this->forceDeleteRecursive($file);
        
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
        
        $file->update(['accessed_at' => now()]);
        
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
        
        $file->update(['accessed_at' => now()]);
        
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

    /**
     * Get all folders for the authenticated user as JSON
     */
    public function allFolders(Request $request): JsonResponse
    {
        $folders = File::where('created_by', $request->user()->id)
            ->where('is_folder', true)
            ->get();
            
        return response()->json([
            'status' => 'success',
            'folders' => $folders
        ]);
    }

    /**
     * Move a file or folder to another folder
     */
    public function move(Request $request, File $file): JsonResponse
    {
        abort_unless($file->created_by === $request->user()->id, 403);
        
        $validated = $request->validate([
            'parent_id' => 'nullable|integer|exists:files,id',
        ]);
        
        $parentId = $validated['parent_id'];
        
        // Validation checks
        if ($parentId !== null) {
            $targetFolder = File::where('id', $parentId)
                ->where('created_by', $request->user()->id)
                ->where('is_folder', true)
                ->first();
                
            if (!$targetFolder) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Target folder not found or access denied.'
                ], 422);
            }
            
            // Prevent moving a folder into itself
            if ($file->is_folder && $file->id === $parentId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Cannot move a folder inside itself.'
                ], 422);
            }
            
            // Prevent moving a folder into one of its descendants
            if ($file->is_folder) {
                if ($this->isDescendant($parentId, $file->id)) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Cannot move a folder inside its subfolders.'
                    ], 422);
                }
            }
        }
        
        $file->parent_id = $parentId;
        $file->save();
        
        return response()->json([
            'status' => 'success',
            'message' => 'Item moved successfully.',
            'file' => $file
        ]);
    }

    /**
     * Helper to check if a folder is a descendant of another folder
     */
    private function isDescendant(int $childId, int $parentId): bool
    {
        $child = File::find($childId);
        while ($child && $child->parent_id !== null) {
            if ($child->parent_id === $parentId) {
                return true;
            }
            $child = File::find($child->parent_id);
        }
        return false;
    }

    /**
     * Get a unique filename in the given directory context.
     */
    private function getUniqueFileName(?int $parentId, int $userId, string $filename): string
    {
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $basename = pathinfo($filename, PATHINFO_FILENAME);
        
        $name = $filename;
        $counter = 2;
        
        while (File::where('parent_id', $parentId)
            ->where('created_by', $userId)
            ->where('name', $name)
            ->exists()) {
            $name = $basename . $counter . ($extension !== '' ? '.' . $extension : '');
            $counter++;
        }
        
        return $name;
    }

    /**
     * Purge soft-deleted items older than 40 days
     */
    protected function purgeExpiredTrash($userId = null)
    {
        $query = File::onlyTrashed()->where('deleted_at', '<=', now()->subDays(40));
        if ($userId) {
            $query->where('created_by', $userId);
        }
        $expiredItems = $query->get();
        foreach ($expiredItems as $item) {
            $this->forceDeleteRecursive($item);
        }
    }

    /**
     * Recursively delete physical files and database records of a file or folder and all descendants
     */
    /**
     * Show items tagged with a specific tag (or all tags view)
     */
    public function tag(Request $request, string $tag)
    {
        $user = $request->user();
        
        if ($tag === 'all') {
            $filesWithTags = File::where('created_by', $user->id)
                ->whereNotNull('tags')
                ->get(['tags']);
            
            $tags = [];
            foreach ($filesWithTags as $file) {
                if (is_array($file->tags)) {
                    foreach ($file->tags as $t) {
                        $tags[] = $t;
                    }
                }
            }
            $standardTags = ['Red', 'Orange', 'Yellow', 'Green', 'Blue', 'Purple', 'Grey'];
            $allTags = array_unique(array_merge($standardTags, $tags));
            sort($allTags);
            
            if ($request->wantsJson() || $request->query('json')) {
                return response()->json([
                    'status' => 'success',
                    'tags' => $allTags,
                    'folders' => [],
                    'files' => []
                ]);
            }
            
            $folders = collect();
            $files = collect();
            return view('drive.index', compact('folders', 'files'));
        }
        
        $query = File::where('created_by', $user->id);
        
        $query->where(function($q) use ($tag) {
            $q->whereJsonContains('tags', $tag)
              ->orWhere('tags', 'like', '%"' . $tag . '"%');
        });
        
        $folders = (clone $query)->where('is_folder', true)->get();
        $files = (clone $query)->where('is_folder', false)->get();
        
        if ($request->wantsJson() || $request->query('json')) {
            return response()->json([
                'status' => 'success',
                'folders' => $folders,
                'files' => $files,
                'tag' => $tag
            ]);
        }
        
        return view('drive.index', compact('folders', 'files'));
    }

    /**
     * Get all unique tags for the user as JSON
     */
    public function allTags(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $filesWithTags = File::where('created_by', $user->id)
            ->whereNotNull('tags')
            ->get(['tags']);
        
        $tags = [];
        foreach ($filesWithTags as $file) {
            if (is_array($file->tags)) {
                foreach ($file->tags as $t) {
                    $tags[] = $t;
                }
            }
        }
        
        $standardTags = ['Red', 'Orange', 'Yellow', 'Green', 'Blue', 'Purple', 'Grey'];
        $allTags = array_unique(array_merge($standardTags, $tags));
        sort($allTags);
        
        return response()->json([
            'status' => 'success',
            'tags' => $allTags
        ]);
    }

    /**
     * Update tags for a file or folder
     */
    public function updateTags(Request $request, File $file): JsonResponse
    {
        abort_unless($file->created_by === $request->user()->id, 403);
        
        $validated = $request->validate([
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
        ]);
        
        $file->tags = $validated['tags'] ?? [];
        $file->save();
        
        return response()->json([
            'status' => 'success',
            'message' => 'Tags updated successfully.',
            'file' => $file
        ]);
    }

    /**
     * Recursively delete physical files and database records of a file or folder and all descendants
     */
    protected function forceDeleteRecursive($file)
    {
        if ($file->is_folder) {
            $children = File::withTrashed()->where('parent_id', $file->id)->get();
            foreach ($children as $child) {
                $this->forceDeleteRecursive($child);
            }
        } else {
            if ($file->storage_path) {
                Storage::disk('public')->delete($file->storage_path);
            }
        }
        $file->forceDelete();
    }

    /**
     * Get all shares and settings for a file
     */
    public function getShares(Request $request, File $file): JsonResponse
    {
        abort_unless($file->created_by === $request->user()->id, 403);

        $shares = $file->shares()->with('sharedWith')->get()->map(function ($share) {
            return [
                'id' => $share->id,
                'user' => [
                    'id' => $share->sharedWith->id,
                    'name' => $share->sharedWith->name,
                    'email' => $share->sharedWith->email,
                    'avatar_url' => $share->sharedWith->profile_photo_path 
                        ? Storage::url($share->sharedWith->profile_photo_path) 
                        : null,
                ],
                'permission' => $share->permission,
            ];
        });

        $owner = [
            'id' => $request->user()->id,
            'name' => $request->user()->name,
            'email' => $request->user()->email,
            'avatar_url' => $request->user()->profile_photo_path 
                ? Storage::url($request->user()->profile_photo_path) 
                : null,
        ];

        return response()->json([
            'status' => 'success',
            'owner' => $owner,
            'collaborators' => $shares,
            'public_link' => [
                'active' => !empty($file->share_token),
                'share_token' => $file->share_token,
                'share_url' => $file->share_token ? url('/s/' . $file->share_token) : null,
                'expires_at' => $file->share_expires_at ? $file->share_expires_at->format('Y-m-d\TH:i') : null,
                'has_password' => !empty($file->share_password),
                'allow_download' => (bool)$file->share_allow_download,
                'allow_import' => (bool)$file->share_allow_import,
                'allow_direct_access' => (bool)$file->share_allow_direct_access,
            ]
        ]);
    }

    /**
     * Toggle public link status
     */
    public function togglePublicLink(Request $request, File $file): JsonResponse
    {
        abort_unless($file->created_by === $request->user()->id, 403);

        $validated = $request->validate([
            'active' => 'required|boolean',
        ]);

        if ($validated['active']) {
            if (empty($file->share_token)) {
                $file->share_token = \Illuminate\Support\Str::random(32);
            }
        } else {
            $file->share_token = null;
        }

        $file->save();

        return response()->json([
            'status' => 'success',
            'active' => !empty($file->share_token),
            'share_token' => $file->share_token,
            'share_url' => $file->share_token ? url('/s/' . $file->share_token) : null,
        ]);
    }

    /**
     * Update public link settings
     */
    public function updatePublicLinkSettings(Request $request, File $file): JsonResponse
    {
        abort_unless($file->created_by === $request->user()->id, 403);

        $validated = $request->validate([
            'expires_at' => 'nullable|date',
            'password_enabled' => 'required|boolean',
            'password' => 'nullable|string|min:4',
            'allow_download' => 'required|boolean',
            'allow_import' => 'required|boolean',
            'allow_direct_access' => 'required|boolean',
        ]);

        $file->share_expires_at = $validated['expires_at'] ? \Carbon\Carbon::parse($validated['expires_at']) : null;
        
        if ($validated['password_enabled']) {
            if ($request->filled('password')) {
                $file->share_password = bcrypt($validated['password']);
            }
        } else {
            $file->share_password = null;
        }

        $file->share_allow_download = $validated['allow_download'];
        $file->share_allow_import = $validated['allow_import'];
        $file->share_allow_direct_access = $validated['allow_direct_access'];
        $file->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Share settings saved successfully.'
        ]);
    }

    /**
     * Update collaborator permission level
     */
    public function updateSharePermission(Request $request, File $file, Share $share): JsonResponse
    {
        abort_unless($file->created_by === $request->user()->id, 403);
        abort_unless($share->file_id === $file->id, 404);

        $validated = $request->validate([
            'permission' => 'required|in:view,edit',
        ]);

        $share->update(['permission' => $validated['permission']]);

        return response()->json([
            'status' => 'success',
            'message' => 'Permission level updated successfully.'
        ]);
    }

    /**
     * Revoke collaborator user share
     */
    public function revokeShare(Request $request, File $file, Share $share): JsonResponse
    {
        abort_unless($file->created_by === $request->user()->id, 403);
        abort_unless($share->file_id === $file->id, 404);

        $share->delete();

        if ($file->shares()->count() === 0) {
            $file->is_shared = false;
            $file->save();
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Access revoked successfully.'
        ]);
    }

    /**
     * Check public share permissions
     */
    private function validatePublicShare(File $file, bool $checkPassword = true)
    {
        if (empty($file->share_token)) {
            abort(404, 'Shared link not found.');
        }

        if ($file->share_expires_at && now()->greaterThan($file->share_expires_at)) {
            return 'expired';
        }

        if ($checkPassword && !empty($file->share_password)) {
            if (session('shared_auth_' . $file->id) !== true) {
                return 'password_required';
            }
        }

        return 'ok';
    }

    /**
     * Show public share interface
     */
    public function showPublicShare(Request $request, $token)
    {
        $file = File::where('share_token', $token)->first();
        if (!$file) {
            // Check if browsing a subfolder
            $subfolderId = $request->query('folder');
            if ($subfolderId) {
                $subfolder = File::find($subfolderId);
                if ($subfolder) {
                    // Find the main shared parent folder by walking up the tree
                    $parent = $this->findSharedParent($subfolder);
                    if ($parent && $parent->share_token === $token) {
                        $file = $parent;
                        $status = $this->validatePublicShare($file);
                        if ($status === 'expired') {
                            return view('drive.public-share', ['file' => $file, 'status' => 'expired']);
                        } elseif ($status === 'password_required') {
                            return view('drive.public-share', ['file' => $file, 'status' => 'password_required']);
                        }
                        
                        $children = $subfolder->children()->get();
                        return view('drive.public-share', [
                            'file' => $file,
                            'status' => 'ok',
                            'currentFolder' => $subfolder,
                            'children' => $children,
                        ]);
                    }
                }
            }
            abort(404, 'Shared link not found.');
        }

        $status = $this->validatePublicShare($file);
        if ($status === 'expired') {
            return view('drive.public-share', ['file' => $file, 'status' => 'expired']);
        } elseif ($status === 'password_required') {
            return view('drive.public-share', ['file' => $file, 'status' => 'password_required']);
        }

        if ($file->is_folder) {
            $children = $file->children()->get();
            return view('drive.public-share', [
                'file' => $file,
                'status' => 'ok',
                'currentFolder' => $file,
                'children' => $children,
            ]);
        }

        return view('drive.public-share', [
            'file' => $file,
            'status' => 'ok',
        ]);
    }

    /**
     * Find shared parent folder by recursively traversing upwards
     */
    private function findSharedParent(File $file)
    {
        $current = $file;
        while ($current->parent_id !== null) {
            $parent = File::find($current->parent_id);
            if ($parent && !empty($parent->share_token)) {
                return $parent;
            }
            $current = $parent;
            if (!$current) {
                return null;
            }
        }
        return null;
    }

    /**
     * Verify public share password
     */
    public function verifyPublicSharePassword(Request $request, $token)
    {
        $file = File::where('share_token', $token)->firstOrFail();
        
        $request->validate([
            'password' => 'required|string',
        ]);

        if (\Illuminate\Support\Facades\Hash::check($request->password, $file->share_password)) {
            session(['shared_auth_' . $file->id => true]);
            return redirect()->route('drive.public.share', ['token' => $token]);
        }

        return redirect()->back()->withErrors(['password' => 'Incorrect password. Please try again.']);
    }

    /**
     * Download public shared file or folder
     */
    public function downloadPublicShare(Request $request, $token)
    {
        $file = File::where('share_token', $token)->firstOrFail();
        $status = $this->validatePublicShare($file);
        if ($status !== 'ok') {
            abort(403, 'Unauthorized access.');
        }

        abort_unless($file->share_allow_download, 403, 'Downloads are not allowed.');

        if ($file->is_folder) {
            return $this->zipFolder($file);
        }

        $path = Storage::disk('public')->path($file->storage_path);
        return response()->download($path, $file->name);
    }

    /**
     * Download subfile of shared public folder
     */
    public function downloadPublicShareSubfile(Request $request, $token, $subfile)
    {
        $parent = File::where('share_token', $token)->firstOrFail();
        $status = $this->validatePublicShare($parent);
        if ($status !== 'ok') {
            abort(403, 'Unauthorized access.');
        }

        abort_unless($parent->share_allow_download, 403, 'Downloads are not allowed.');

        $file = File::findOrFail($subfile);
        abort_unless($this->isDescendantOf($file, $parent), 403, 'File is not in the shared folder.');

        if ($file->is_folder) {
            return $this->zipFolder($file);
        }

        $path = Storage::disk('public')->path($file->storage_path);
        return response()->download($path, $file->name);
    }

    /**
     * Inline preview subfile of shared public folder
     */
    public function inlinePublicShareSubfile(Request $request, $token, $subfile)
    {
        $parent = File::where('share_token', $token)->firstOrFail();
        $status = $this->validatePublicShare($parent);
        if ($status !== 'ok') {
            abort(403, 'Unauthorized access.');
        }

        abort_unless($parent->share_allow_direct_access, 403, 'Direct access is not allowed.');

        $file = File::findOrFail($subfile);
        abort_unless($this->isDescendantOf($file, $parent), 403, 'File is not in the shared folder.');

        $path = Storage::disk('public')->path($file->storage_path);
        
        return response()->file($path, [
            'Content-Type' => $file->mime_type ?? 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . basename($file->name) . '"'
        ]);
    }

    /**
     * Import public shared folder or file into user's own drive
     */
    public function importPublicShare(Request $request, $token)
    {
        abort_unless(auth()->check(), 401, 'Please log in to import files.');
        
        $file = File::where('share_token', $token)->firstOrFail();
        $status = $this->validatePublicShare($file);
        if ($status !== 'ok') {
            abort(403, 'Unauthorized access.');
        }

        abort_unless($file->share_allow_import, 403, 'Import is not allowed.');

        $this->cloneFileOrFolder($file, null, auth()->id());

        return redirect()->route('drive.index')->with('success', 'Item imported to your Drive successfully.');
    }

    /**
     * Import individual subfile of shared public folder into user's own drive
     */
    public function importPublicShareSubfile(Request $request, $token, $subfile)
    {
        abort_unless(auth()->check(), 401, 'Please log in to import files.');

        $parent = File::where('share_token', $token)->firstOrFail();
        $status = $this->validatePublicShare($parent);
        if ($status !== 'ok') {
            abort(403, 'Unauthorized access.');
        }

        abort_unless($parent->share_allow_import, 403, 'Import is not allowed.');

        $file = File::findOrFail($subfile);
        abort_unless($this->isDescendantOf($file, $parent), 403, 'File is not in the shared folder.');

        $this->cloneFileOrFolder($file, null, auth()->id());

        return response()->json([
            'status' => 'success',
            'message' => 'Item imported to your Drive successfully.'
        ]);
    }

    /**
     * Helper: Check if a file is a descendant of a parent folder
     */
    private function isDescendantOf(File $child, File $parent): bool
    {
        $current = $child;
        while ($current->parent_id !== null) {
            if ($current->parent_id === $parent->id) {
                return true;
            }
            $current = File::find($current->parent_id);
            if (!$current) {
                return false;
            }
        }
        return false;
    }

    /**
     * Helper: Clone a file or folder recursively for a user
     */
    private function cloneFileOrFolder(File $item, $newParentId, $userId)
    {
        $clone = $item->replicate([
            'share_token',
            'share_expires_at',
            'share_password',
            'share_allow_download',
            'share_allow_import',
            'share_allow_direct_access',
            'is_shared',
        ]);
        $clone->created_by = $userId;
        $clone->parent_id = $newParentId;
        
        if (!$item->is_folder) {
            $clone->storage_path = $item->storage_path;
        }
        
        $clone->save();
        
        if ($item->is_folder) {
            foreach ($item->children()->get() as $child) {
                $this->cloneFileOrFolder($child, $clone->id, $userId);
            }
        }
        
        return $clone;
    }

    /**
     * Helper: Zip a folder and all descendants
     */
    private function zipFolder(File $folder)
    {
        $zip = new \ZipArchive();
        $zipName = tempnam(sys_get_temp_dir(), 'zip') . '.zip';
        
        if ($zip->open($zipName, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
            $this->addFolderToZip($folder, $zip, '');
            $zip->close();
            
            return response()->download($zipName, $folder->name . '.zip')->deleteFileAfterSend(true);
        }
        
        abort(500, 'Could not create zip file.');
    }

    /**
     * Helper: Recursively add files to zip archive
     */
    private function addFolderToZip(File $folder, \ZipArchive $zip, string $localPath)
    {
        $children = $folder->children()->get();
        foreach ($children as $child) {
            $currentLocalPath = $localPath === '' ? $child->name : $localPath . '/' . $child->name;
            if ($child->is_folder) {
                $zip->addEmptyDir($currentLocalPath);
                $this->addFolderToZip($child, $zip, $currentLocalPath);
            } else {
                $filePath = Storage::disk('public')->path($child->storage_path);
                if (file_exists($filePath)) {
                    $zip->addFile($filePath, $currentLocalPath);
                }
            }
        }
    }
}
