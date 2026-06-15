<?php

namespace App\Http\Controllers\Drive;

use App\Http\Controllers\Controller;
use App\Models\File;
use App\Models\Project;
use App\Models\Share;
use App\Models\User;
use App\Services\Drive\FileService;
use App\Services\Drive\FolderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

class DriveController extends Controller
{
    /**
     * Helper to apply scope constraints to queries
     */
    private function applyScopeConstraints($query, User $user, string $driveScope)
    {
        if ($driveScope === 'personal') {
            $query->where('drive_type', 'personal')->where('created_by', $user->id);
        } else if ($driveScope === 'organization') {
            $company = $user->company;
            $query->where('drive_type', 'organization');
            if (!empty($company)) {
                $query->whereIn('created_by', function($q) use ($company) {
                    $q->select('id')->from('users')->where('company', $company);
                });
            } else {
                $query->whereIn('created_by', function($q) {
                    $q->select('id')->from('users')->whereNull('company')->orWhere('company', '');
                });
            }
        } else if ($driveScope === 'project') {
            $projectIds = DB::table('project_users')->where('user_id', $user->id)->pluck('project_id');
            $query->where('drive_type', 'project')->whereIn('project_id', $projectIds);
        } else if ($driveScope === 'admin') {
            // Admin can see everything, no constraints
        }
        return $query;
    }

    /**
     * Check if user has read access to a file/folder
     */
    public function hasAccess(File $file, User $user): bool
    {
        // Admin / Superadmin bypass
        if (in_array($user->role, ['superadmin', 'admin'])) {
            return true;
        }

        // Personal scope check
        if ($file->drive_type === 'personal' || empty($file->drive_type)) {
            if ($file->created_by === $user->id) {
                return true;
            }
            $isShared = Share::where('file_id', $file->id)
                ->where('shared_with', $user->id)
                ->exists();
            if ($isShared) {
                return true;
            }
            // Walk up to check if any parent is owned or shared
            $parent = $file->parent;
            while ($parent) {
                if ($parent->created_by === $user->id) {
                    return true;
                }
                if (Share::where('file_id', $parent->id)->where('shared_with', $user->id)->exists()) {
                    return true;
                }
                $parent = $parent->parent;
            }
            return false;
        }

        // Organization scope check
        if ($file->drive_type === 'organization') {
            $creator = $file->creator;
            if ($creator) {
                if (!empty($user->company) && $user->company === $creator->company) {
                    return true;
                }
                if (empty($user->company) && empty($creator->company)) {
                    return true;
                }
            }
            return $file->created_by === $user->id;
        }

        // Project scope check
        if ($file->drive_type === 'project') {
            $projectId = $file->project_id;
            if (empty($projectId)) {
                $temp = $file;
                while ($temp && empty($temp->project_id)) {
                    $temp = $temp->parent;
                }
                if ($temp && !empty($temp->project_id)) {
                    $projectId = $temp->project_id;
                }
            }
            if (!empty($projectId)) {
                return DB::table('project_users')
                    ->where('project_id', $projectId)
                    ->where('user_id', $user->id)
                    ->exists();
            }
            return $file->created_by === $user->id;
        }

        return false;
    }

    /**
     * Check if user can manage (write/delete/share) a file/folder
     */
    public function canManageFile(File $file, User $user): bool
    {
        // Admin / Superadmin bypass
        if (in_array($user->role, ['superadmin', 'admin'])) {
            return true;
        }

        // Creator can always manage
        if ($file->created_by === $user->id) {
            return true;
        }

        // Project Manager check
        if ($file->drive_type === 'project') {
            $projectId = $file->project_id;
            if (empty($projectId)) {
                $temp = $file;
                while ($temp && empty($temp->project_id)) {
                    $temp = $temp->parent;
                }
                if ($temp && !empty($temp->project_id)) {
                    $projectId = $temp->project_id;
                }
            }
            if (!empty($projectId)) {
                return DB::table('project_users')
                    ->where('project_id', $projectId)
                    ->where('user_id', $user->id)
                    ->where('role', 'manager')
                    ->exists();
            }
        }

        return false;
    }

    /**
     * Show the main drive/my files view (SPA index)
     */
    public function index(Request $request, FileService $fileService, FolderService $folderService)
    {
        $user = $request->user();
        $parentId = $request->query('folder_id');
        $driveScope = $request->query('drive_scope') ?? session('drive_scope', 'personal');

        if (!in_array($driveScope, ['personal', 'organization', 'project', 'admin'])) {
            $driveScope = 'personal';
        }

        // Restrict admin scope
        if ($driveScope === 'admin' && !in_array($user->role, ['admin', 'superadmin'])) {
            $driveScope = 'personal';
        }

        session(['drive_scope' => $driveScope]);
        
        $currentFolder = null;
        $breadcrumbs = [];
        if ($parentId) {
            $currentFolder = File::where('id', $parentId)
                ->where('is_folder', true)
                ->when($driveScope === 'project', function($q) {
                    $q->with('project.members');
                })
                ->first();
                
            if ($currentFolder) {
                $currentFolder->update(['accessed_at' => now()]);
                
                if (!$this->hasAccess($currentFolder, $user)) {
                    abort(403, 'Unauthorized action.');
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
            // Root level filtering based on drive scope
            if ($driveScope === 'personal') {
                $folders = File::where('drive_type', 'personal')
                    ->where('created_by', $user->id)
                    ->whereNull('parent_id')
                    ->where('is_folder', true)
                    ->get();
                $files = File::where('drive_type', 'personal')
                    ->where('created_by', $user->id)
                    ->whereNull('parent_id')
                    ->where('is_folder', false)
                    ->get();
            } else if ($driveScope === 'organization') {
                $company = $user->company;
                $query = File::where('drive_type', 'organization')
                    ->whereNull('parent_id');
                
                if (!empty($company)) {
                    $query->whereIn('created_by', function($q) use ($company) {
                        $q->select('id')->from('users')->where('company', $company);
                    });
                } else {
                    $query->whereIn('created_by', function($q) {
                        $q->select('id')->from('users')->whereNull('company')->orWhere('company', '');
                    });
                }
                
                $folders = (clone $query)->where('is_folder', true)->get();
                $files = (clone $query)->where('is_folder', false)->get();
            } else if ($driveScope === 'project') {
                $projectIds = DB::table('project_users')->where('user_id', $user->id)->pluck('project_id');
                
                $folders = File::where('drive_type', 'project')
                    ->whereNull('parent_id')
                    ->whereIn('project_id', $projectIds)
                    ->with('project.members')
                    ->get();
                $files = collect(); // no root level files in project scope
            } else if ($driveScope === 'admin') {
                $folders = File::whereNull('parent_id')->where('is_folder', true)->get();
                $files = File::whereNull('parent_id')->where('is_folder', false)->get();
            }
        }
        
        if ($request->wantsJson() || $request->query('json')) {
            return response()->json([
                'status' => 'success',
                'folders' => $folders,
                'files' => $files,
                'breadcrumbs' => $breadcrumbs,
                'currentFolder' => $currentFolder,
                'drive_scope' => $driveScope
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
        
        $shares = Share::where('shared_with', $user->id)
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
        $driveScope = $request->query('drive_scope') ?? session('drive_scope', 'personal');
        
        $this->purgeExpiredTrash($user->id);
        
        $queryFolders = File::onlyTrashed()->where('is_folder', true);
        $queryFiles = File::onlyTrashed()->where('is_folder', false);

        $this->applyScopeConstraints($queryFolders, $user, $driveScope);
        $this->applyScopeConstraints($queryFiles, $user, $driveScope);

        $folders = $queryFolders->get();
        $files = $queryFiles->get();
            
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
        $queryStr = $request->query('q');
        $driveScope = $request->query('drive_scope') ?? session('drive_scope', 'personal');
        
        if (empty($queryStr)) {
            $files = collect();
            $folders = collect();
        } else {
            $queryFolders = File::where('is_folder', true)->where('name', 'like', "%{$queryStr}%");
            $queryFiles = File::where('is_folder', false)->where('name', 'like', "%{$queryStr}%");

            $this->applyScopeConstraints($queryFolders, $user, $driveScope);
            $this->applyScopeConstraints($queryFiles, $user, $driveScope);

            $folders = $queryFolders->get();
            $files = $queryFiles->get();
        }
        
        if ($request->wantsJson() || $request->query('json')) {
            return response()->json([
                'status' => 'success',
                'folders' => $folders,
                'files' => $files,
                'query' => $queryStr
            ]);
        }
        
        return view('drive.search', compact('folders', 'files', 'queryStr'));
    }

    /**
     * Show recents view
     */
    public function recents(Request $request)
    {
        $user = $request->user();
        $driveScope = $request->query('drive_scope') ?? session('drive_scope', 'personal');
        
        $queryFolders = File::where('is_folder', true)->orderBy('accessed_at', 'desc')->limit(10);
        $queryFiles = File::where('is_folder', false)->orderBy('accessed_at', 'desc')->limit(20);

        $this->applyScopeConstraints($queryFolders, $user, $driveScope);
        $this->applyScopeConstraints($queryFiles, $user, $driveScope);

        $folders = $queryFolders->get();
        $files = $queryFiles->get();
            
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
        $driveScope = $request->query('drive_scope') ?? session('drive_scope', 'personal');
        
        $queryFolders = File::where('is_starred', true)->where('is_folder', true);
        $queryFiles = File::where('is_starred', true)->where('is_folder', false);

        $this->applyScopeConstraints($queryFolders, $user, $driveScope);
        $this->applyScopeConstraints($queryFiles, $user, $driveScope);

        $folders = $queryFolders->get();
        $files = $queryFiles->get();
            
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
     * Create a new folder
     */
    public function storeFolder(Request $request, FolderService $folderService)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', 'exists:files,id'],
        ]);

        $user = $request->user();
        $parentId = $validated['parent_id'] ?? null;
        $driveScope = $request->query('drive_scope') ?? session('drive_scope', 'personal');

        if ($parentId) {
            $parent = File::findOrFail($parentId);
            if (!$this->hasAccess($parent, $user)) {
                abort(403, 'Unauthorized action.');
            }
            $driveType = $parent->drive_type;
            $projectId = $parent->project_id;
        } else {
            // Root level creation
            if ($driveScope === 'project') {
                // Create a Project
                $project = Project::create([
                    'name' => $validated['name'],
                    'created_by' => $user->id,
                ]);

                DB::table('project_users')->insert([
                    'project_id' => $project->id,
                    'user_id' => $user->id,
                    'role' => 'manager',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $driveType = 'project';
                $projectId = $project->id;
            } else {
                $driveType = ($driveScope === 'admin') ? 'personal' : $driveScope;
                $projectId = null;
            }
        }

        $folder = File::create([
            'name' => $validated['name'],
            'type' => 'folder',
            'is_folder' => true,
            'parent_id' => $parentId,
            'created_by' => $user->id,
            'drive_type' => $driveType,
            'project_id' => $projectId,
        ]);

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

        $user = $request->user();
        $parentId = $validated['parent_id'] ?? null;
        $driveScope = $request->query('drive_scope') ?? session('drive_scope', 'personal');

        if ($parentId) {
            $parent = File::findOrFail($parentId);
            if (!$this->hasAccess($parent, $user)) {
                abort(403, 'Unauthorized action.');
            }
            $driveType = $parent->drive_type;
            $projectId = $parent->project_id;
        } else {
            if ($driveScope === 'project') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Files cannot be uploaded to the root of the Project Drive. Please create or enter a project folder first.'
                ], 422);
            }
            $driveType = ($driveScope === 'admin') ? 'personal' : $driveScope;
            $projectId = null;
        }

        $createdFiles = [];
        foreach ($validated['files'] as $uploadedFile) {
            $storagePath = $uploadedFile->store('drive/uploads', 'public');

            $file = File::create([
                'name' => $uploadedFile->getClientOriginalName(),
                'path' => $storagePath,
                'mime_type' => $uploadedFile->getClientMimeType(),
                'size' => $uploadedFile->getSize(),
                'storage_path' => $storagePath,
                'parent_id' => $parentId,
                'created_by' => $user->id,
                'is_folder' => false,
                'drive_type' => $driveType,
                'project_id' => $projectId,
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
     * Upload a folder recursively
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
        $parentId = $validated['parent_id'] ?? null;
        $driveScope = $request->query('drive_scope') ?? session('drive_scope', 'personal');

        if ($parentId) {
            $parent = File::findOrFail($parentId);
            if (!$this->hasAccess($parent, $user)) {
                abort(403, 'Unauthorized action.');
            }
            $driveType = $parent->drive_type;
            $projectId = $parent->project_id;
        } else {
            if ($driveScope === 'project') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Folders cannot be uploaded to the root of the Project Drive. Please create or enter a project folder first.'
                ], 422);
            }
            $driveType = ($driveScope === 'admin') ? 'personal' : $driveScope;
            $projectId = null;
        }

        $createdFiles = [];
        $folderCache = [];

        foreach ($validated['files'] as $index => $uploadedFile) {
            $relativePath = $validated['paths'][$index] ?? $uploadedFile->getClientOriginalName();
            $relativePath = str_replace('\\', '/', $relativePath);
            $pathSegments = explode('/', $relativePath);
            
            $fileName = array_pop($pathSegments);
            $currentParentId = $parentId;
            $pathAccumulator = "";

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
                            'drive_type' => $driveType,
                            'project_id' => $projectId,
                        ]);
                    }

                    $currentParentId = $folder->id;
                    $folderCache[$cacheKey] = $currentParentId;
                }
            }

            $storagePath = $uploadedFile->store('drive/folders', 'public');

            $file = File::create([
                'name' => $fileName,
                'path' => $storagePath,
                'mime_type' => $uploadedFile->getClientMimeType(),
                'size' => $uploadedFile->getSize(),
                'storage_path' => $storagePath,
                'parent_id' => $currentParentId,
                'created_by' => $user->id,
                'is_folder' => false,
                'drive_type' => $driveType,
                'project_id' => $projectId,
            ]);
            $createdFiles[] = $file;
        }

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Folder uploaded successfully.',
                'files' => $createdFiles
            ]);
        }

        return redirect()->route('drive.index')->with('status', 'Folder uploaded successfully.');
    }

    /**
     * Create blank office file
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
        $user = $request->user();
        $driveScope = $request->query('drive_scope') ?? session('drive_scope', 'personal');

        if ($parentId) {
            $parent = File::findOrFail($parentId);
            if (!$this->hasAccess($parent, $user)) {
                abort(403, 'Unauthorized action.');
            }
            $driveType = $parent->drive_type;
            $projectId = $parent->project_id;
        } else {
            if ($driveScope === 'project') {
                abort(422, 'Office files cannot be created at the root of the Project Drive.');
            }
            $driveType = ($driveScope === 'admin') ? 'personal' : $driveScope;
            $projectId = null;
        }

        $uniqueName = $this->getUniqueFileName($parentId, $user->id, $template['name']);
        $storagePath = 'drive/onlyoffice/' . $kind . '/' . uniqid() . '_' . str_replace(' ', '-', strtolower($uniqueName));

        Storage::disk('public')->put($storagePath, '');

        $file = File::create([
            'name' => $uniqueName,
            'path' => $storagePath,
            'type' => 'file',
            'mime_type' => $template['mime'],
            'size' => 0,
            'storage_path' => $storagePath,
            'created_by' => $user->id,
            'is_folder' => false,
            'parent_id' => $parentId,
            'drive_type' => $driveType,
            'project_id' => $projectId,
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
        abort_unless($this->canManageFile($file, $request->user()), 403);
        
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
        abort_unless($this->canManageFile($file, $request->user()), 403);
        
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);
        
        $newName = $validated['name'];
        
        if (!$file->is_folder) {
            $originalExt = pathinfo($file->name, PATHINFO_EXTENSION);
            if ($originalExt !== '') {
                $pattern = '/\.' . preg_quote($originalExt, '/') . '$/i';
                if (!preg_match($pattern, $newName)) {
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
        
        // Rename the project title if this was a root project folder
        if ($file->is_folder && $file->drive_type === 'project' && empty($file->parent_id) && !empty($file->project_id)) {
            $project = Project::find($file->project_id);
            if ($project) {
                $project->update(['name' => $newName]);
            }
        }
        
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
        abort_unless($this->canManageFile($file, $request->user()), 403);
        
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
        abort_unless($this->canManageFile($file, $request->user()), 403);
        
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
        abort_unless($this->canManageFile($file, $request->user()), 403);
        
        // If it's a project root folder, also delete the project entity!
        if ($file->is_folder && $file->drive_type === 'project' && empty($file->parent_id) && !empty($file->project_id)) {
            $project = Project::find($file->project_id);
            if ($project) {
                $project->delete();
            }
        }

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
        abort_unless($this->hasAccess($file, $request->user()), 403);
        abort_if($file->is_folder, 400, 'Cannot download folders directly.');
        
        if (!Storage::disk('public')->exists($file->storage_path)) {
            abort(404, 'File does not exist on storage.');
        }
        
        $file->update(['accessed_at' => now()]);
        
        return Storage::disk('public')->download($file->storage_path, $file->name);
    }

    /**
     * View a file inline in the browser
     */
    public function inline(Request $request, File $file)
    {
        abort_unless($this->hasAccess($file, $request->user()), 403);
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
        abort_unless($this->canManageFile($file, $request->user()), 403);
        
        $validated = $request->validate([
            'email' => 'required|email|exists:users,email',
            'permission' => 'required|in:view,edit',
        ]);
        
        $sharedWithUser = User::where('email', $validated['email'])->firstOrFail();
        
        if ($sharedWithUser->id === $request->user()->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'You cannot share files with yourself.'
            ], 422);
        }
        
        Share::updateOrCreate([
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
        $user = $request->user();
        $driveScope = $request->query('drive_scope') ?? session('drive_scope', 'personal');
        
        $query = File::where('is_folder', true);
        $this->applyScopeConstraints($query, $user, $driveScope);
        $folders = $query->get();
            
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
        abort_unless($this->canManageFile($file, $request->user()), 403);
        
        $validated = $request->validate([
            'parent_id' => 'nullable|integer|exists:files,id',
        ]);
        
        $parentId = $validated['parent_id'];
        
        if ($parentId !== null) {
            $targetFolder = File::find($parentId);
                
            if (!$targetFolder || !$this->hasAccess($targetFolder, $request->user())) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Target folder not found or access denied.'
                ], 422);
            }
            
            // Check that they match scopes
            if ($file->drive_type !== $targetFolder->drive_type) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Cannot move items between different drive scopes.'
                ], 422);
            }
            
            if ($file->is_folder && $file->id === $parentId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Cannot move a folder inside itself.'
                ], 422);
            }
            
            if ($file->is_folder) {
                if ($this->isDescendant($parentId, $file->id)) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Cannot move a folder inside its subfolders.'
                    ], 422);
                }
            }

            $file->project_id = $targetFolder->project_id;
        } else {
            // Moving to root level of its scope
            if ($file->drive_type === 'project') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Cannot move items to the root level of the Project Drive.'
                ], 422);
            }
            $file->project_id = null;
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
     * Recursively delete physical files and database records
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
     * Show items tagged with a specific tag
     */
    public function tag(Request $request, string $tag)
    {
        $user = $request->user();
        $driveScope = $request->query('drive_scope') ?? session('drive_scope', 'personal');
        
        if ($tag === 'all') {
            $query = File::query();
            $this->applyScopeConstraints($query, $user, $driveScope);
            $filesWithTags = $query->whereNotNull('tags')->get(['tags']);
            
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
        
        $queryFolders = File::where('is_folder', true)->where(function($q) use ($tag) {
            $q->whereJsonContains('tags', $tag)
              ->orWhere('tags', 'like', '%"' . $tag . '"%');
        });
        $queryFiles = File::where('is_folder', false)->where(function($q) use ($tag) {
            $q->whereJsonContains('tags', $tag)
              ->orWhere('tags', 'like', '%"' . $tag . '"%');
        });

        $this->applyScopeConstraints($queryFolders, $user, $driveScope);
        $this->applyScopeConstraints($queryFiles, $user, $driveScope);

        $folders = $queryFolders->get();
        $files = $queryFiles->get();
        
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
        $driveScope = $request->query('drive_scope') ?? session('drive_scope', 'personal');
        
        $query = File::query();
        $this->applyScopeConstraints($query, $user, $driveScope);
        $filesWithTags = $query->whereNotNull('tags')->get(['tags']);
        
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
        abort_unless($this->canManageFile($file, $request->user()), 403);
        
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
     * Get all shares and settings for a file
     */
    public function getShares(Request $request, File $file): JsonResponse
    {
        abort_unless($this->canManageFile($file, $request->user()), 403);

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
            'id' => $file->creator->id ?? $request->user()->id,
            'name' => $file->creator->name ?? $request->user()->name,
            'email' => $file->creator->email ?? $request->user()->email,
            'avatar_url' => ($file->creator && $file->creator->profile_photo_path) 
                ? Storage::url($file->creator->profile_photo_path) 
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
        abort_unless($this->canManageFile($file, $request->user()), 403);

        $validated = $request->validate([
            'active' => 'required|boolean',
        ]);

        if ($validated['active']) {
            if (empty($file->share_token)) {
                $file->share_token = Str::random(32);
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
        abort_unless($this->canManageFile($file, $request->user()), 403);

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
        abort_unless($this->canManageFile($file, $request->user()), 403);
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
        abort_unless($this->canManageFile($file, $request->user()), 403);
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
            $subfolderId = $request->query('folder');
            if ($subfolderId) {
                $subfolder = File::find($subfolderId);
                if ($subfolder) {
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

        if (Hash::check($request->password, $file->share_password)) {
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
        $clone->drive_type = 'personal'; // imported files always go to personal drive
        $clone->project_id = null;
        
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
