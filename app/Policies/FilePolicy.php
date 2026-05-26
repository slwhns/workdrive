<?php

namespace App\Policies;

use App\Models\File;
use App\Models\User;

class FilePolicy
{
    /**
     * Determine if the user can view the file
     */
    public function view(User $user, File $file): bool
    {
        // Owner can always view
        if ($file->created_by === $user->id) {
            return true;
        }

        // Check if file is shared with user
        return $file->shares()
            ->where('shared_with', $user->id)
            ->exists();
    }

    /**
     * Determine if the user can update the file
     */
    public function update(User $user, File $file): bool
    {
        // Only owner can update
        if ($file->created_by === $user->id) {
            return true;
        }

        // Check if user has edit permission
        return $file->shares()
            ->where('shared_with', $user->id)
            ->where('permission', 'edit')
            ->exists();
    }

    /**
     * Determine if the user can delete the file
     */
    public function delete(User $user, File $file): bool
    {
        // Only owner can delete
        return $file->created_by === $user->id;
    }

    /**
     * Determine if the user can share the file
     */
    public function share(User $user, File $file): bool
    {
        // Only owner can share
        return $file->created_by === $user->id;
    }

    /**
     * Determine if the user can restore the file
     */
    public function restore(User $user, File $file): bool
    {
        // Only owner can restore
        return $file->created_by === $user->id;
    }

    /**
     * Determine if the user can force delete the file
     */
    public function forceDelete(User $user, File $file): bool
    {
        // Only owner can force delete
        return $file->created_by === $user->id;
    }
}
