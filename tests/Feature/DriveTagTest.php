<?php

namespace Tests\Feature;

use App\Models\File;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DriveTagTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test updating tags for a file.
     */
    public function test_can_update_tags_for_file_or_folder(): void
    {
        $user = User::factory()->create();

        $file = File::create([
            'name' => 'TaggedDocument.docx',
            'type' => 'file',
            'is_folder' => false,
            'created_by' => $user->id,
            'parent_id' => null,
        ]);

        $tags = ['Red', 'Important', 'Work'];

        $response = $this->actingAs($user)->post(route('drive.files.tags.update', ['file' => $file->id]), [
            'tags' => $tags,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Tags updated successfully.',
            ]);

        $this->assertEquals($tags, $file->refresh()->tags);
    }

    /**
     * Test filtering files/folders by tag.
     */
    public function test_can_fetch_items_by_tag(): void
    {
        $user = User::factory()->create();

        // Target file
        $file1 = File::create([
            'name' => 'TargetDoc.docx',
            'type' => 'file',
            'is_folder' => false,
            'created_by' => $user->id,
            'tags' => ['Red', 'Important'],
        ]);

        // Other file
        $file2 = File::create([
            'name' => 'OtherDoc.docx',
            'type' => 'file',
            'is_folder' => false,
            'created_by' => $user->id,
            'tags' => ['Blue'],
        ]);

        $response = $this->actingAs($user)->get(route('drive.tag', ['tag' => 'Red', 'json' => 1]));

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'tag' => 'Red',
            ]);

        $files = $response->json('files');
        $this->assertCount(1, $files);
        $this->assertEquals('TargetDoc.docx', $files[0]['name']);
    }

    /**
     * Test compiling list of all tags.
     */
    public function test_can_fetch_all_tags_list(): void
    {
        $user = User::factory()->create();

        File::create([
            'name' => 'DocA.docx',
            'type' => 'file',
            'is_folder' => false,
            'created_by' => $user->id,
            'tags' => ['Home'],
        ]);

        File::create([
            'name' => 'DocB.docx',
            'type' => 'file',
            'is_folder' => false,
            'created_by' => $user->id,
            'tags' => ['Work', 'Important'],
        ]);

        $response = $this->actingAs($user)->get(route('drive.tags.all'));

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
            ]);

        $tags = $response->json('tags');
        // Standard tags: Red, Orange, Yellow, Green, Blue, Purple, Grey
        // Plus custom tags: Home, Important, Work
        $this->assertContains('Home', $tags);
        $this->assertContains('Important', $tags);
        $this->assertContains('Work', $tags);
        $this->assertContains('Red', $tags);
    }
}
