<?php

namespace Tests\Feature;

use App\Models\File;
use App\Models\User;
use App\Models\Share;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminShareManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $owner;
    private User $user;
    private File $file;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->owner = User::factory()->create(['role' => 'user']);
        $this->user = User::factory()->create(['role' => 'user']);

        $this->file = File::create([
            'name' => 'Secret Admin Document.docx',
            'type' => 'file',
            'is_folder' => false,
            'created_by' => $this->owner->id,
            'drive_type' => 'personal',
            'accessed_at' => now(),
        ]);
    }

    /**
     * Test that admin can fetch share settings of another user's file.
     */
    public function test_admin_can_fetch_share_settings_of_other_user_file(): void
    {
        $response = $this->actingAs($this->admin)->getJson(route('drive.shares.get', ['file' => $this->file->id]));

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'owner' => ['email' => $this->owner->email],
                'collaborators' => []
            ]);
    }

    /**
     * Test that admin can add a member to another user's file.
     */
    public function test_admin_can_add_share_to_other_user_file(): void
    {
        $response = $this->actingAs($this->admin)->postJson(route('drive.files.share', ['file' => $this->file->id]), [
            'email' => $this->user->email,
            'permission' => 'edit'
        ]);

        $response->assertStatus(200)
            ->assertJson(['status' => 'success']);

        $this->assertDatabaseHas('shares', [
            'file_id' => $this->file->id,
            'shared_with' => $this->user->id,
            'permission' => 'edit',
        ]);
    }

    /**
     * Test that admin can update collaborator role on another user's file.
     */
    public function test_admin_can_update_share_on_other_user_file(): void
    {
        $share = Share::create([
            'file_id' => $this->file->id,
            'shared_by' => $this->owner->id,
            'shared_with' => $this->user->id,
            'permission' => 'view'
        ]);

        $response = $this->actingAs($this->admin)->putJson(
            route('drive.shares.update', ['file' => $this->file->id, 'share' => $share->id]),
            ['permission' => 'edit']
        );

        $response->assertStatus(200)
            ->assertJson(['status' => 'success']);

        $this->assertEquals('edit', $share->refresh()->permission);
    }

    /**
     * Test that admin can revoke collaborator access on another user's file.
     */
    public function test_admin_can_revoke_share_on_other_user_file(): void
    {
        $share = Share::create([
            'file_id' => $this->file->id,
            'shared_by' => $this->owner->id,
            'shared_with' => $this->user->id,
            'permission' => 'view'
        ]);

        $response = $this->actingAs($this->admin)->deleteJson(
            route('drive.shares.revoke', ['file' => $this->file->id, 'share' => $share->id])
        );

        $response->assertStatus(200)
            ->assertJson(['status' => 'success']);

        $this->assertDatabaseMissing('shares', [
            'id' => $share->id
        ]);
    }
}
