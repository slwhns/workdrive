<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProjectMemberTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that unauthorized user cannot fetch available users.
     */
    public function test_unauthorized_user_cannot_fetch_available_users(): void
    {
        $manager = User::factory()->create(['company' => 'Google']);
        $otherUser = User::factory()->create(['company' => 'Google']);
        
        $project = Project::create([
            'name' => 'Project Alpha',
            'created_by' => $manager->id,
        ]);

        DB::table('project_users')->insert([
            'project_id' => $project->id,
            'user_id' => $manager->id,
            'role' => 'manager',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Requesting as a user not associated with the project
        $response = $this->actingAs($otherUser)->get(route('projects.members.available', ['project' => $project->id]));

        $response->assertStatus(403);
    }

    /**
     * Test fetching available users for project (excluding existing members, regardless of company).
     */
    public function test_can_fetch_available_users_excluding_existing_members_regardless_of_company(): void
    {
        $manager = User::factory()->create(['company' => 'Google']);
        $memberInProject = User::factory()->create(['company' => 'Google']);
        $availableUser = User::factory()->create(['company' => 'Google']);
        $otherCompanyUser = User::factory()->create(['company' => 'Apple']);

        $project = Project::create([
            'name' => 'Project Alpha',
            'created_by' => $manager->id,
        ]);

        DB::table('project_users')->insert([
            ['project_id' => $project->id, 'user_id' => $manager->id, 'role' => 'manager', 'created_at' => now(), 'updated_at' => now()],
            ['project_id' => $project->id, 'user_id' => $memberInProject->id, 'role' => 'member', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $response = $this->actingAs($manager)->get(route('projects.members.available', ['project' => $project->id]));

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
            ]);

        $users = $response->json('users');

        // Both $availableUser and $otherCompanyUser should be in the list of available users
        // $manager and $memberInProject are already in the project
        $this->assertCount(2, $users);
        
        $emails = collect($users)->pluck('email')->toArray();
        $this->assertContains($availableUser->email, $emails);
        $this->assertContains($otherCompanyUser->email, $emails);
    }
}
