<?php

namespace App\Services\Drive;

use App\Models\File;
use App\Models\User;

class FileService
{
    /**
     * Get files for the user (root level)
     */
    public function getUserFiles(User $user, ?int $parentId = null)
    {
        return File::where('created_by', $user->id)
            ->where('parent_id', $parentId)
            ->where('is_folder', false)
            ->get();
    }

    /**
     * Get folders for the user
     */
    public function getUserFolders(User $user, ?int $parentId = null)
    {
        return File::where('created_by', $user->id)
            ->where('parent_id', $parentId)
            ->where('is_folder', true)
            ->get();
    }

    /**
     * Create a new file record
     */
    public function createFile(User $user, array $data)
    {
        return File::create([
            'name' => $data['name'],
            'path' => $data['path'] ?? null,
            'type' => $data['type'] ?? 'file',
            'mime_type' => $data['mime_type'] ?? null,
            'size' => $data['size'] ?? 0,
            'storage_path' => $data['storage_path'] ?? null,
            'parent_id' => $data['parent_id'] ?? null,
            'created_by' => $user->id,
            'is_folder' => false,
        ]);
    }

    /**
     * Get total storage used by user
     */
    public function getTotalStorageUsed(User $user): int
    {
        return File::where('created_by', $user->id)
            ->sum('size');
    }

    /**
     * Delete a file (soft delete)
     */
    public function deleteFile(File $file)
    {
        return $file->delete();
    }

    /**
     * Restore a file from trash
     */
    public function restoreFile(File $file)
    {
        return $file->restore();
    }

    /**
     * Permanently delete a file
     */
    public function forceDeleteFile(File $file)
    {
        return $file->forceDelete();
    }
}
