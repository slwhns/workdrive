<?php

namespace Tests\Feature;

use App\Models\File;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DriveNamingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    /**
     * Test that creating new office files yields uniquely incremented names.
     */
    public function test_create_office_files_increments_names_on_conflict(): void
    {
        $user = User::factory()->create();

        // 1. Create first document
        $response1 = $this->actingAs($user)->post(route('drive.office.create', ['kind' => 'document']));
        $response1->assertRedirect();
        
        $this->assertDatabaseHas('files', [
            'name' => 'New Document.docx',
            'created_by' => $user->id,
            'is_folder' => false,
        ]);

        // 2. Create second document (name conflict expected)
        $response2 = $this->actingAs($user)->post(route('drive.office.create', ['kind' => 'document']));
        $response2->assertRedirect();

        $this->assertDatabaseHas('files', [
            'name' => 'New Document2.docx',
            'created_by' => $user->id,
            'is_folder' => false,
        ]);

        // 3. Create third document (name conflict expected)
        $response3 = $this->actingAs($user)->post(route('drive.office.create', ['kind' => 'document']));
        $response3->assertRedirect();

        $this->assertDatabaseHas('files', [
            'name' => 'New Document3.docx',
            'created_by' => $user->id,
            'is_folder' => false,
        ]);

        // 4. Create first spreadsheet
        $responseSpreadsheet = $this->actingAs($user)->post(route('drive.office.create', ['kind' => 'spreadsheet']));
        $responseSpreadsheet->assertRedirect();

        $this->assertDatabaseHas('files', [
            'name' => 'New Spreadsheet.xlsx',
            'created_by' => $user->id,
            'is_folder' => false,
        ]);

        // 5. Create second spreadsheet (name conflict expected)
        $responseSpreadsheet2 = $this->actingAs($user)->post(route('drive.office.create', ['kind' => 'spreadsheet']));
        $responseSpreadsheet2->assertRedirect();

        $this->assertDatabaseHas('files', [
            'name' => 'New Spreadsheet2.xlsx',
            'created_by' => $user->id,
            'is_folder' => false,
        ]);
    }

    /**
     * Test that renaming a file preserves its extension under all conditions.
     */
    public function test_rename_preserves_file_extension(): void
    {
        $user = User::factory()->create();

        // Create initial file
        $file = File::create([
            'name' => 'New Document.docx',
            'path' => 'drive/onlyoffice/document/new-document.docx',
            'type' => 'file',
            'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'size' => 0,
            'storage_path' => 'drive/onlyoffice/document/new-document.docx',
            'created_by' => $user->id,
            'is_folder' => false,
        ]);

        // 1. Rename to name without extension
        $response = $this->actingAs($user)->post(route('drive.files.rename', ['file' => $file->id]), [
            'name' => 'My Report',
        ]);
        $response->assertJson(['status' => 'success']);
        $this->assertEquals('My Report.docx', $file->refresh()->name);

        // 2. Rename to name with matching extension (case-insensitive)
        $response = $this->actingAs($user)->post(route('drive.files.rename', ['file' => $file->id]), [
            'name' => 'Project Draft.DOCX',
        ]);
        $response->assertJson(['status' => 'success']);
        $this->assertEquals('Project Draft.DOCX', $file->refresh()->name);

        // 3. Rename to name with wrong extension
        $response = $this->actingAs($user)->post(route('drive.files.rename', ['file' => $file->id]), [
            'name' => 'Final Presentation.pptx',
        ]);
        $response->assertJson(['status' => 'success']);
        $this->assertEquals('Final Presentation.DOCX', $file->refresh()->name);

        // 4. Rename to name with multiple dots (simulating sub-extensions)
        $response = $this->actingAs($user)->post(route('drive.files.rename', ['file' => $file->id]), [
            'name' => 'Report.v2.0',
        ]);
        $response->assertJson(['status' => 'success']);
        $this->assertEquals('Report.v2.DOCX', $file->refresh()->name);
    }

    /**
     * Test that folders do not enforce extensions during rename.
     */
    public function test_rename_does_not_affect_folders(): void
    {
        $user = User::factory()->create();

        $folder = File::create([
            'name' => 'My Folder',
            'type' => 'folder',
            'created_by' => $user->id,
            'is_folder' => true,
        ]);

        $response = $this->actingAs($user)->post(route('drive.files.rename', ['file' => $folder->id]), [
            'name' => 'Archive.zip',
        ]);
        $response->assertJson(['status' => 'success']);
        $this->assertEquals('Archive.zip', $folder->refresh()->name);
    }
}
