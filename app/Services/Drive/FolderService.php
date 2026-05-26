<?php

namespace App\Services\Drive;

use App\Models\File;
use App\Models\User;

class FolderService
{
    /**
     * Create a new folder
     */
    public function createFolder(User $user, string $name, ?int $parentId = null): File
    {
        return File::create([
            'name' => $name,
            'type' => 'folder',
            'is_folder' => true,
            'parent_id' => $parentId,
            'created_by' => $user->id,
        ]);
    }

    /**
     * Get folder contents (files and subfolders)
     */
    public function getFolderContents(User $user, int $folderId)
    {
        $folder = File::find($folderId);

        if (!$folder || $folder->created_by !== $user->id || !$folder->is_folder) {
            return null;
        }

        return $folder->children()->with('creator')->get();
    }

    /**
     * Rename a folder
     */
    public function renameFolder(File $folder, string $newName): bool
    {
        $folder->name = $newName;
        return $folder->save();
    }

    /**
     * Move a folder
     */
    public function moveFolder(File $folder, ?int $newParentId = null): bool
    {
        $folder->parent_id = $newParentId;
        return $folder->save();
    }

    /**
     * Get folder path (breadcrumbs)
     */
    public function getFolderPath(File $folder): array
    {
        $path = [];
        $current = $folder;

        while ($current) {
            array_unshift($path, $current);
            $current = $current->parent;
        }

        return $path;
    }
}
