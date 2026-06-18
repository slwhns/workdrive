<?php

namespace App\Http\Controllers\Drive;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProjectController extends Controller
{
    /**
     * Get list of users available to be added to this project.
     */
    public function getAvailableUsers(Request $request, Project $project)
    {
        $currentUser = $request->user();

        // Only creator, managers, or admin/superadmin can manage/view members
        $isManager = DB::table('project_users')
            ->where('project_id', $project->id)
            ->where('user_id', $currentUser->id)
            ->where('role', 'manager')
            ->exists() || $project->created_by === $currentUser->id || in_array($currentUser->role, ['admin', 'superadmin']);

        if (!$isManager) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized.'
            ], 403);
        }

        $existingMemberIds = DB::table('project_users')
            ->where('project_id', $project->id)
            ->pluck('user_id');

        $users = User::whereNotIn('id', $existingMemberIds)
            ->select('id', 'name', 'email')
            ->orderBy('name', 'asc')
            ->get();

        return response()->json([
            'status' => 'success',
            'users' => $users
        ]);
    }

    /**
     * Add a member to the project by email.
     */
    public function addMember(Request $request, Project $project)
    {
        $currentUser = $request->user();
        
        // Only creator, managers, or admin/superadmin can add members
        $isManager = DB::table('project_users')
            ->where('project_id', $project->id)
            ->where('user_id', $currentUser->id)
            ->where('role', 'manager')
            ->exists() || $project->created_by === $currentUser->id || in_array($currentUser->role, ['admin', 'superadmin']);

        if (!$isManager) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized to manage project members.'
            ], 403);
        }

        $validated = $request->validate([
            'email' => 'required|email|exists:users,email',
            'role' => 'nullable|string|in:member,manager',
        ]);

        $userToAdd = User::where('email', $validated['email'])->firstOrFail();

        // Check if user is already a member
        $alreadyMember = DB::table('project_users')
            ->where('project_id', $project->id)
            ->where('user_id', $userToAdd->id)
            ->exists();

        if ($alreadyMember) {
            return response()->json([
                'status' => 'error',
                'message' => 'User is already a member of this project.'
            ], 422);
        }

        DB::table('project_users')->insert([
            'project_id' => $project->id,
            'user_id' => $userToAdd->id,
            'role' => $validated['role'] ?? 'member',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => "User {$userToAdd->name} added to project successfully.",
            'user' => [
                'id' => $userToAdd->id,
                'name' => $userToAdd->name,
                'email' => $userToAdd->email,
                'pivot' => ['role' => $validated['role'] ?? 'member']
            ]
        ]);
    }

    /**
     * Remove a member from the project.
     */
    public function removeMember(Request $request, Project $project, User $user)
    {
        $currentUser = $request->user();

        // Only creator, managers, or admin/superadmin can remove members
        $isManager = DB::table('project_users')
            ->where('project_id', $project->id)
            ->where('user_id', $currentUser->id)
            ->where('role', 'manager')
            ->exists() || $project->created_by === $currentUser->id || in_array($currentUser->role, ['admin', 'superadmin']);

        if (!$isManager) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized to manage project members.'
            ], 403);
        }

        // Prevent removing the project creator
        if ($project->created_by === $user->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Cannot remove the project creator.'
            ], 422);
        }

        DB::table('project_users')
            ->where('project_id', $project->id)
            ->where('user_id', $user->id)
            ->delete();

        return response()->json([
            'status' => 'success',
            'message' => "User {$user->name} removed from project successfully."
        ]);
    }
}
