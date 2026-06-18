<?php

namespace App\Http\Controllers\Drive;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class OrganizationController extends Controller
{
    /**
     * Helper to verify authorization and get company name.
     */
    protected function getCompanyOrAbort(Request $request, $requireManager = false)
    {
        $currentUser = $request->user();
        $company = $currentUser->company;

        // Must belong to a company OR be an admin/superadmin
        $canAccess = !empty($company) || in_array($currentUser->role, ['admin', 'superadmin']);

        if (!$canAccess) {
            abort(403, 'Unauthorized.');
        }

        if ($requireManager) {
            $isManager = in_array($currentUser->role, ['manager', 'admin', 'superadmin']);
            if (!$isManager) {
                abort(403, 'Unauthorized to manage organization members.');
            }
        }

        return $company;
    }

    /**
     * Get all members of the organization.
     */
    public function getMembers(Request $request)
    {
        $company = $this->getCompanyOrAbort($request, false);

        $members = User::where(function ($query) use ($company) {
                if (empty($company)) {
                    $query->whereNull('company')->orWhere('company', '');
                } else {
                    $query->where('company', $company);
                }
            })
            ->select('id', 'name', 'email', 'company')
            ->orderBy('name', 'asc')
            ->get();

        return response()->json([
            'status' => 'success',
            'members' => $members
        ]);
    }

    /**
     * Get list of users available to be added to this organization.
     */
    public function getAvailableUsers(Request $request)
    {
        $company = $this->getCompanyOrAbort($request, true);

        $users = User::where(function ($query) use ($company) {
                if (empty($company)) {
                    $query->whereNotNull('company')->where('company', '!=', '');
                } else {
                    $query->where('company', '!=', $company)->orWhereNull('company');
                }
            })
            ->select('id', 'name', 'email')
            ->orderBy('name', 'asc')
            ->get();

        return response()->json([
            'status' => 'success',
            'users' => $users
        ]);
    }

    /**
     * Add a member to the organization by email.
     */
    public function addMember(Request $request)
    {
        $company = $this->getCompanyOrAbort($request, true);

        $validated = $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $userToAdd = User::where('email', $validated['email'])->firstOrFail();

        // Check if user is already a member
        $isAlreadyMember = false;
        if (empty($company)) {
            $isAlreadyMember = empty($userToAdd->company);
        } else {
            $isAlreadyMember = ($userToAdd->company === $company);
        }

        if ($isAlreadyMember) {
            return response()->json([
                'status' => 'error',
                'message' => 'User is already a member of this organization.'
            ], 422);
        }

        $userToAdd->update([
            'company' => $company
        ]);

        return response()->json([
            'status' => 'success',
            'message' => "User {$userToAdd->name} added to organization successfully.",
            'user' => [
                'id' => $userToAdd->id,
                'name' => $userToAdd->name,
                'email' => $userToAdd->email,
            ]
        ]);
    }

    /**
     * Remove a member from the organization.
     */
    public function removeMember(Request $request, User $user)
    {
        $company = $this->getCompanyOrAbort($request, true);
        $currentUser = $request->user();

        if ($currentUser->id === $user->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Cannot remove yourself from the organization.'
            ], 422);
        }

        // Verify that this user actually belongs to this company before removing
        $belongsToCompany = false;
        if (empty($company)) {
            $belongsToCompany = empty($user->company);
        } else {
            $belongsToCompany = ($user->company === $company);
        }

        if (!$belongsToCompany) {
            return response()->json([
                'status' => 'error',
                'message' => 'User does not belong to this organization.'
            ], 422);
        }

        $user->update([
            'company' => null
        ]);

        return response()->json([
            'status' => 'success',
            'message' => "User {$user->name} removed from organization successfully."
        ]);
    }
}
