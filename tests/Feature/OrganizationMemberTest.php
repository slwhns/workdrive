<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationMemberTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that guest/unauthenticated user cannot fetch organization members or available users.
     */
    public function test_guest_cannot_access_organization_endpoints(): void
    {
        $response1 = $this->getJson(route('organization.members.get'));
        $response1->assertStatus(401);

        $response2 = $this->getJson(route('organization.members.available'));
        $response2->assertStatus(401);

        $response3 = $this->postJson(route('organization.members.add'), ['email' => 'test@example.com']);
        $response3->assertStatus(401);

        $response4 = $this->deleteJson(route('organization.members.remove', ['user' => 1]));
        $response4->assertStatus(401);
    }

    /**
     * Test fetching organization members.
     */
    public function test_can_fetch_organization_members(): void
    {
        $user1 = User::factory()->create(['company' => 'Google', 'role' => 'user']);
        $user2 = User::factory()->create(['company' => 'Google', 'role' => 'user']);
        $otherUser = User::factory()->create(['company' => 'Apple', 'role' => 'user']);

        $response = $this->actingAs($user1)->getJson(route('organization.members.get'));

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
            ]);

        $members = $response->json('members');
        $this->assertCount(2, $members);

        $emails = collect($members)->pluck('email')->toArray();
        $this->assertContains($user1->email, $emails);
        $this->assertContains($user2->email, $emails);
        $this->assertNotContains($otherUser->email, $emails);
    }

    /**
     * Test fetching available users for the organization (excluding existing members).
     */
    public function test_can_fetch_available_users(): void
    {
        $user1 = User::factory()->create(['company' => 'Google', 'role' => 'manager']);
        $user2 = User::factory()->create(['company' => 'Google', 'role' => 'user']);
        $availableUser1 = User::factory()->create(['company' => 'Apple']);
        $availableUser2 = User::factory()->create(['company' => null]);

        $response = $this->actingAs($user1)->getJson(route('organization.members.available'));

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
            ]);

        $users = $response->json('users');
        // $availableUser1 and $availableUser2 should be in available list, but not $user1 or $user2
        $this->assertCount(2, $users);

        $emails = collect($users)->pluck('email')->toArray();
        $this->assertContains($availableUser1->email, $emails);
        $this->assertContains($availableUser2->email, $emails);
        $this->assertNotContains($user1->email, $emails);
        $this->assertNotContains($user2->email, $emails);
    }

    /**
     * Test that a normal user cannot fetch available users.
     */
    public function test_normal_user_cannot_fetch_available_users(): void
    {
        $user = User::factory()->create(['company' => 'Google', 'role' => 'user']);
        
        $response = $this->actingAs($user)->getJson(route('organization.members.available'));
        
        $response->assertStatus(403);
    }

    /**
     * Test adding a user to the organization by email.
     */
    public function test_can_add_member_to_organization(): void
    {
        $manager = User::factory()->create(['company' => 'Google', 'role' => 'manager']);
        $userToAdd = User::factory()->create(['company' => 'Apple']);

        $response = $this->actingAs($manager)->postJson(route('organization.members.add'), [
            'email' => $userToAdd->email
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
            ]);

        // Refresh user and verify company is updated
        $userToAdd->refresh();
        $this->assertEquals('Google', $userToAdd->company);
    }

    /**
     * Test that a normal user cannot add members.
     */
    public function test_normal_user_cannot_add_member(): void
    {
        $user = User::factory()->create(['company' => 'Google', 'role' => 'user']);
        $userToAdd = User::factory()->create(['company' => 'Apple']);

        $response = $this->actingAs($user)->postJson(route('organization.members.add'), [
            'email' => $userToAdd->email
        ]);

        $response->assertStatus(403);
    }

    /**
     * Test adding a user who is already a member of the organization.
     */
    public function test_cannot_add_existing_member(): void
    {
        $manager = User::factory()->create(['company' => 'Google', 'role' => 'manager']);
        $existing = User::factory()->create(['company' => 'Google']);

        $response = $this->actingAs($manager)->postJson(route('organization.members.add'), [
            'email' => $existing->email
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'status' => 'error',
                'message' => 'User is already a member of this organization.'
            ]);
    }

    /**
     * Test removing a user from the organization.
     */
    public function test_can_remove_member_from_organization(): void
    {
        $manager = User::factory()->create(['company' => 'Google', 'role' => 'manager']);
        $member = User::factory()->create(['company' => 'Google']);

        $response = $this->actingAs($manager)->deleteJson(route('organization.members.remove', ['user' => $member->id]));

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
            ]);

        // Refresh member and verify company is null
        $member->refresh();
        $this->assertNull($member->company);
    }

    /**
     * Test that a normal user cannot remove members.
     */
    public function test_normal_user_cannot_remove_member(): void
    {
        $user = User::factory()->create(['company' => 'Google', 'role' => 'user']);
        $member = User::factory()->create(['company' => 'Google']);

        $response = $this->actingAs($user)->deleteJson(route('organization.members.remove', ['user' => $member->id]));

        $response->assertStatus(403);
    }

    /**
     * Test that a user cannot remove themselves from the organization.
     */
    public function test_cannot_remove_self_from_organization(): void
    {
        $user = User::factory()->create(['company' => 'Google', 'role' => 'manager']);

        $response = $this->actingAs($user)->deleteJson(route('organization.members.remove', ['user' => $user->id]));

        $response->assertStatus(422)
            ->assertJson([
                'status' => 'error',
                'message' => 'Cannot remove yourself from the organization.'
            ]);
    }
}
