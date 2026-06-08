<?php

namespace Tests\Feature;

use App\Models\File;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schedule;
use Tests\TestCase;

class TrashAndRecentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_creation_sets_accessed_at(): void
    {
        $user = User::factory()->create();

        // Create a folder
        $folder = File::create([
            'name' => 'Test Folder',
            'type' => 'folder',
            'is_folder' => true,
            'created_by' => $user->id,
        ]);

        $this->assertNotNull($folder->accessed_at);
        $this->assertTrue(now()->diffInSeconds($folder->accessed_at) < 5);
    }

    public function test_navigation_updates_accessed_at(): void
    {
        $user = User::factory()->create();

        $folder = File::create([
            'name' => 'Test Folder',
            'type' => 'folder',
            'is_folder' => true,
            'created_by' => $user->id,
            'accessed_at' => now()->subHours(5),
        ]);

        $originalAccessedAt = $folder->accessed_at;

        // Access the folder in index
        $this->actingAs($user)->get(route('drive.index', ['folder_id' => $folder->id]));

        $folder->refresh();
        $this->assertNotEquals($originalAccessedAt->toIso8601String(), $folder->accessed_at->toIso8601String());
        $this->assertTrue(now()->diffInSeconds($folder->accessed_at) < 5);
    }

    public function test_download_and_inline_updates_accessed_at(): void
    {
        $user = User::factory()->create();

        $file = File::create([
            'name' => 'document.docx',
            'type' => 'file',
            'is_folder' => false,
            'created_by' => $user->id,
            'storage_path' => 'uploads/doc.docx',
            'accessed_at' => now()->subHours(5),
        ]);

        Storage::disk('public')->put('uploads/doc.docx', 'dummy content');

        $originalAccessedAt = $file->accessed_at;

        // Download
        $this->actingAs($user)->get(route('drive.files.download', ['file' => $file->id]));
        $file->refresh();
        $this->assertNotEquals($originalAccessedAt->toIso8601String(), $file->accessed_at->toIso8601String());
        $this->assertTrue(now()->diffInSeconds($file->accessed_at) < 5);

        // Reset accessed_at
        $file->update(['accessed_at' => now()->subHours(5)]);
        $originalAccessedAt = $file->accessed_at;

        // Inline preview
        $this->actingAs($user)->get(route('drive.files.inline', ['file' => $file->id]));
        $file->refresh();
        $this->assertNotEquals($originalAccessedAt->toIso8601String(), $file->accessed_at->toIso8601String());
        $this->assertTrue(now()->diffInSeconds($file->accessed_at) < 5);
    }

    public function test_preview_updates_accessed_at(): void
    {
        $user = User::factory()->create();

        $file = File::create([
            'name' => 'document.docx',
            'type' => 'file',
            'is_folder' => false,
            'created_by' => $user->id,
            'storage_path' => 'uploads/doc.docx',
            'accessed_at' => now()->subHours(5),
        ]);

        $originalAccessedAt = $file->accessed_at;

        $this->actingAs($user)->get(route('preview.show', ['file' => $file->id]));
        $file->refresh();
        $this->assertNotEquals($originalAccessedAt->toIso8601String(), $file->accessed_at->toIso8601String());
        $this->assertTrue(now()->diffInSeconds($file->accessed_at) < 5);

        // Reset and test api/preview
        $file->update(['accessed_at' => now()->subHours(5)]);
        $originalAccessedAt = $file->accessed_at;

        $this->actingAs($user)->get(route('api.preview.data', ['file' => $file->id]));
        $file->refresh();
        $this->assertNotEquals($originalAccessedAt->toIso8601String(), $file->accessed_at->toIso8601String());
    }

    public function test_onlyoffice_config_updates_accessed_at(): void
    {
        $user = User::factory()->create();

        $file = File::create([
            'name' => 'document.docx',
            'type' => 'file',
            'is_folder' => false,
            'created_by' => $user->id,
            'storage_path' => 'uploads/doc.docx',
            'accessed_at' => now()->subHours(5),
        ]);

        $originalAccessedAt = $file->accessed_at;

        $this->actingAs($user)->get(route('onlyoffice.config', ['file' => $file->id]));
        $file->refresh();
        $this->assertNotEquals($originalAccessedAt->toIso8601String(), $file->accessed_at->toIso8601String());
        $this->assertTrue(now()->diffInSeconds($file->accessed_at) < 5);
    }

    public function test_recents_sorted_by_accessed_at(): void
    {
        $user = User::factory()->create();

        $file1 = File::create([
            'name' => 'old_doc.docx',
            'type' => 'file',
            'is_folder' => false,
            'created_by' => $user->id,
            'accessed_at' => now()->subHours(2),
        ]);

        $file2 = File::create([
            'name' => 'new_doc.docx',
            'type' => 'file',
            'is_folder' => false,
            'created_by' => $user->id,
            'accessed_at' => now()->subMinutes(10),
        ]);

        $response = $this->actingAs($user)->get(route('drive.recents', ['json' => 1]));
        $response->assertStatus(200);

        $files = $response->json('files');
        $this->assertCount(2, $files);
        $this->assertEquals($file2->id, $files[0]['id']);
        $this->assertEquals($file1->id, $files[1]['id']);
    }

    public function test_trash_purging(): void
    {
        $user = User::factory()->create();

        // Trashed 41 days ago
        $oldTrashedFile = File::create([
            'name' => 'old_trashed.docx',
            'type' => 'file',
            'is_folder' => false,
            'created_by' => $user->id,
        ]);
        $oldTrashedFile->deleted_at = now()->subDays(41);
        $oldTrashedFile->save();

        // Trashed 10 days ago
        $newTrashedFile = File::create([
            'name' => 'new_trashed.docx',
            'type' => 'file',
            'is_folder' => false,
            'created_by' => $user->id,
        ]);
        $newTrashedFile->deleted_at = now()->subDays(10);
        $newTrashedFile->save();

        $this->assertDatabaseHas('files', ['id' => $oldTrashedFile->id]);
        $this->assertDatabaseHas('files', ['id' => $newTrashedFile->id]);

        // Trigger trash view load which runs purge
        $response = $this->actingAs($user)->get(route('drive.trash', ['json' => 1]));
        $response->assertStatus(200);

        // Old trashed file should be gone permanently
        $this->assertDatabaseMissing('files', ['id' => $oldTrashedFile->id]);
        // New trashed file should still exist (trashed)
        $this->assertDatabaseHas('files', ['id' => $newTrashedFile->id]);
    }

    public function test_scheduled_purge_task(): void
    {
        $user = User::factory()->create();

        // Trashed 41 days ago
        $oldTrashedFile = File::create([
            'name' => 'old_trashed_scheduled.docx',
            'type' => 'file',
            'is_folder' => false,
            'created_by' => $user->id,
        ]);
        $oldTrashedFile->deleted_at = now()->subDays(41);
        $oldTrashedFile->save();

        // Trashed 10 days ago
        $newTrashedFile = File::create([
            'name' => 'new_trashed_scheduled.docx',
            'type' => 'file',
            'is_folder' => false,
            'created_by' => $user->id,
        ]);
        $newTrashedFile->deleted_at = now()->subDays(10);
        $newTrashedFile->save();

        $this->assertDatabaseHas('files', ['id' => $oldTrashedFile->id]);
        $this->assertDatabaseHas('files', ['id' => $newTrashedFile->id]);

        // Travel to start of day (00:00) so daily scheduled task runs
        $this->travelTo(now()->startOfDay());

        // Trigger scheduled command
        $this->artisan('schedule:run');

        // Old trashed file should be gone permanently
        $this->assertDatabaseMissing('files', ['id' => $oldTrashedFile->id]);
        // New trashed file should still exist
        $this->assertDatabaseHas('files', ['id' => $newTrashedFile->id]);
    }
}
