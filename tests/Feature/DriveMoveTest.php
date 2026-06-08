<?php

namespace Tests\Feature;

use App\Models\File;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DriveMoveTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test fetching all folders of the authenticated user.
     */
    public function test_can_fetch_all_folders_of_authenticated_user(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        // Create folders for $user
        $folder1 = File::create([
            'name' => 'Folder 1',
            'type' => 'folder',
            'is_folder' => true,
            'created_by' => $user->id,
        ]);
        $folder2 = File::create([
            'name' => 'Folder 2',
            'type' => 'folder',
            'is_folder' => true,
            'created_by' => $user->id,
        ]);

        // Create folder for $otherUser
        File::create([
            'name' => 'Other Folder',
            'type' => 'folder',
            'is_folder' => true,
            'created_by' => $otherUser->id,
        ]);

        $response = $this->actingAs($user)->get(route('drive.folders.all'));
        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
            ]);

        $folders = $response->json('folders');
        $this->assertCount(2, $folders);
        $this->assertEquals('Folder 1', $folders[0]['name']);
        $this->assertEquals('Folder 2', $folders[1]['name']);
    }

    /**
     * Test moving a file to a folder.
     */
    public function test_can_move_file_to_folder(): void
    {
        $user = User::factory()->create();

        $folder = File::create([
            'name' => 'Folder A',
            'type' => 'folder',
            'is_folder' => true,
            'created_by' => $user->id,
        ]);

        $file = File::create([
            'name' => 'Document.docx',
            'type' => 'file',
            'is_folder' => false,
            'created_by' => $user->id,
            'parent_id' => null,
        ]);

        $response = $this->actingAs($user)->post(route('drive.files.move', ['file' => $file->id]), [
            'parent_id' => $folder->id,
        ]);

        $response->assertStatus(200)
            ->assertJson(['status' => 'success']);

        $this->assertEquals($folder->id, $file->refresh()->parent_id);
    }

    /**
     * Test moving a folder inside another folder.
     */
    public function test_can_move_folder_to_another_folder(): void
    {
        $user = User::factory()->create();

        $folderA = File::create([
            'name' => 'Folder A',
            'type' => 'folder',
            'is_folder' => true,
            'created_by' => $user->id,
        ]);

        $folderB = File::create([
            'name' => 'Folder B',
            'type' => 'folder',
            'is_folder' => true,
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->post(route('drive.files.move', ['file' => $folderB->id]), [
            'parent_id' => $folderA->id,
        ]);

        $response->assertStatus(200)
            ->assertJson(['status' => 'success']);

        $this->assertEquals($folderA->id, $folderB->refresh()->parent_id);
    }

    /**
     * Test that a folder cannot be moved inside itself.
     */
    public function test_cannot_move_folder_inside_itself(): void
    {
        $user = User::factory()->create();

        $folder = File::create([
            'name' => 'Folder A',
            'type' => 'folder',
            'is_folder' => true,
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->post(route('drive.files.move', ['file' => $folder->id]), [
            'parent_id' => $folder->id,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'status' => 'error',
                'message' => 'Cannot move a folder inside itself.',
            ]);
    }

    /**
     * Test that a folder cannot be moved inside its descendants.
     */
    public function test_cannot_move_folder_inside_its_descendants(): void
    {
        $user = User::factory()->create();

        // Structure: A -> B -> C
        $folderA = File::create([
            'name' => 'Folder A',
            'type' => 'folder',
            'is_folder' => true,
            'created_by' => $user->id,
        ]);

        $folderB = File::create([
            'name' => 'Folder B',
            'type' => 'folder',
            'is_folder' => true,
            'parent_id' => $folderA->id,
            'created_by' => $user->id,
        ]);

        $folderC = File::create([
            'name' => 'Folder C',
            'type' => 'folder',
            'is_folder' => true,
            'parent_id' => $folderB->id,
            'created_by' => $user->id,
        ]);

        // Try to move A into C
        $response = $this->actingAs($user)->post(route('drive.files.move', ['file' => $folderA->id]), [
            'parent_id' => $folderC->id,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'status' => 'error',
                'message' => 'Cannot move a folder inside its subfolders.',
            ]);
    }

    /**
     * Test unauthorized move prevention.
     */
    public function test_cannot_move_other_users_files(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $fileOfUser2 = File::create([
            'name' => 'Private.docx',
            'type' => 'file',
            'is_folder' => false,
            'created_by' => $user2->id,
        ]);

        $response = $this->actingAs($user1)->post(route('drive.files.move', ['file' => $fileOfUser2->id]), [
            'parent_id' => null,
        ]);

        $response->assertStatus(403);
    }
}
