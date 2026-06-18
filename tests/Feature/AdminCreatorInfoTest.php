<?php

namespace Tests\Feature;

use App\Models\File;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCreatorInfoTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $creator;
    private File $folder;
    private File $file;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->creator = User::factory()->create(['role' => 'user', 'name' => 'John Creator', 'email' => 'john@creator.com']);

        // Create dummy items under the creator
        $this->folder = File::create([
            'name' => 'Admin Test Folder',
            'type' => 'folder',
            'is_folder' => true,
            'created_by' => $this->creator->id,
            'drive_type' => 'personal',
            'accessed_at' => now(),
        ]);

        $this->file = File::create([
            'name' => 'Admin Test File.txt',
            'type' => 'file',
            'is_folder' => false,
            'created_by' => $this->creator->id,
            'drive_type' => 'personal',
            'accessed_at' => now(),
            'parent_id' => $this->folder->id,
        ]);
    }

    public function test_admin_index_returns_creator_info(): void
    {
        $response = $this->actingAs($this->admin)->getJson(route('drive.index', [
            'drive_scope' => 'admin',
            'json' => 1
        ]));

        $response->assertStatus(200);
        $folders = $response->json('folders');
        
        $this->assertNotEmpty($folders);
        $this->assertEquals('John Creator', $folders[0]['creator']['name']);
        $this->assertEquals('john@creator.com', $folders[0]['creator']['email']);
    }

    public function test_admin_subfolder_contents_returns_creator_info(): void
    {
        $response = $this->actingAs($this->admin)->getJson(route('drive.index', [
            'drive_scope' => 'admin',
            'folder_id' => $this->folder->id,
            'json' => 1
        ]));

        $response->assertStatus(200);
        $files = $response->json('files');
        
        $this->assertNotEmpty($files);
        $this->assertEquals('John Creator', $files[0]['creator']['name']);
        $this->assertEquals('john@creator.com', $files[0]['creator']['email']);
    }

    public function test_admin_trash_returns_creator_info(): void
    {
        $this->folder->delete();

        $response = $this->actingAs($this->admin)->getJson(route('drive.trash', [
            'drive_scope' => 'admin',
            'json' => 1
        ]));

        $response->assertStatus(200);
        $folders = $response->json('folders');
        
        $this->assertNotEmpty($folders);
        $this->assertEquals('John Creator', $folders[0]['creator']['name']);
        $this->assertEquals('john@creator.com', $folders[0]['creator']['email']);
    }

    public function test_admin_search_returns_creator_info(): void
    {
        $response = $this->actingAs($this->admin)->getJson(route('drive.search', [
            'drive_scope' => 'admin',
            'q' => 'Admin Test',
            'json' => 1
        ]));

        $response->assertStatus(200);
        $folders = $response->json('folders');
        
        $this->assertNotEmpty($folders);
        $this->assertEquals('John Creator', $folders[0]['creator']['name']);
        $this->assertEquals('john@creator.com', $folders[0]['creator']['email']);
    }

    public function test_admin_recents_returns_creator_info(): void
    {
        $response = $this->actingAs($this->admin)->getJson(route('drive.recents', [
            'drive_scope' => 'admin',
            'json' => 1
        ]));

        $response->assertStatus(200);
        $folders = $response->json('folders');
        
        $this->assertNotEmpty($folders);
        $this->assertEquals('John Creator', $folders[0]['creator']['name']);
        $this->assertEquals('john@creator.com', $folders[0]['creator']['email']);
    }

    public function test_admin_starred_returns_creator_info(): void
    {
        $this->folder->update(['is_starred' => true]);

        $response = $this->actingAs($this->admin)->getJson(route('drive.starred', [
            'drive_scope' => 'admin',
            'json' => 1
        ]));

        $response->assertStatus(200);
        $folders = $response->json('folders');
        
        $this->assertNotEmpty($folders);
        $this->assertEquals('John Creator', $folders[0]['creator']['name']);
        $this->assertEquals('john@creator.com', $folders[0]['creator']['email']);
    }

    public function test_admin_tag_returns_creator_info(): void
    {
        $this->folder->update(['tags' => ['Red']]);

        $response = $this->actingAs($this->admin)->getJson(route('drive.tag', [
            'drive_scope' => 'admin',
            'tag' => 'Red',
            'json' => 1
        ]));

        $response->assertStatus(200);
        $folders = $response->json('folders');
        
        $this->assertNotEmpty($folders);
        $this->assertEquals('John Creator', $folders[0]['creator']['name']);
        $this->assertEquals('john@creator.com', $folders[0]['creator']['email']);
    }
}
