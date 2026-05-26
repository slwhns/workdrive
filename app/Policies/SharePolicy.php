<?php

namespace App\Policies;

use App\Models\Share;
use App\Models\User;

class SharePolicy
{
    /**
     * Determine if the user can create a share
     */
    public function create(User $user, Share $share): bool
    {
        // User who created the share must be the owner of the file
        return $share->file->created_by === $user->id;
    }

    /**
     * Determine if the user can delete a share
     */
    public function delete(User $user, Share $share): bool
    {
        // Only the file owner or the recipient can delete a share
        return $share->file->created_by === $user->id ||
               $share->shared_with === $user->id;
    }
}
