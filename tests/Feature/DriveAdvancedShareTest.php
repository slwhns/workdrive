<?php

namespace Tests\Feature;

use App\Models\File;
use App\Models\User;
use App\Models\Share;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DriveAdvancedShareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    /**
     * Test fetching share settings.
     */
    public function test_can_fetch_share_settings(): void
    {
        $user = User::factory()->create();
        $collaborator = User::factory()->create();

        $file = File::create([
            'name' => 'Report.pdf',
            'type' => 'file',
            'is_folder' => false,
            'created_by' => $user->id,
            'share_token' => 'test_token_123',
            'share_expires_at' => now()->addDays(5),
            'share_password' => bcrypt('secret123'),
        ]);

        Share::create([
            'file_id' => $file->id,
            'shared_by' => $user->id,
            'shared_with' => $collaborator->id,
            'permission' => 'view',
        ]);

        $response = $this->actingAs($user)->get(route('drive.shares.get', ['file' => $file->id]));

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'owner' => ['email' => $user->email],
                'public_link' => [
                    'active' => true,
                    'share_token' => 'test_token_123',
                    'has_password' => true,
                ]
            ]);

        $collaborators = $response->json('collaborators');
        $this->assertCount(1, $collaborators);
        $this->assertEquals($collaborator->email, $collaborators[0]['user']['email']);
    }

    /**
     * Test toggling public link.
     */
    public function test_can_toggle_public_link(): void
    {
        $user = User::factory()->create();
        $file = File::create([
            'name' => 'Design.png',
            'type' => 'file',
            'created_by' => $user->id,
        ]);

        // Enable public link
        $response = $this->actingAs($user)->post(route('drive.shares.public-link', ['file' => $file->id]), [
            'active' => true,
        ]);

        $response->assertStatus(200)->assertJson(['status' => 'success', 'active' => true]);
        $this->assertNotEmpty($file->refresh()->share_token);

        // Disable public link
        $response = $this->actingAs($user)->post(route('drive.shares.public-link', ['file' => $file->id]), [
            'active' => false,
        ]);

        $response->assertStatus(200)->assertJson(['status' => 'success', 'active' => false]);
        $this->assertNull($file->refresh()->share_token);
    }

    /**
     * Test updating public link settings.
     */
    public function test_can_update_public_link_settings(): void
    {
        $user = User::factory()->create();
        $file = File::create([
            'name' => 'Outline.txt',
            'type' => 'file',
            'created_by' => $user->id,
            'share_token' => 'some_token',
        ]);

        $expiry = now()->addDays(2)->format('Y-m-d\TH:i');

        $response = $this->actingAs($user)->put(route('drive.shares.public-link-settings', ['file' => $file->id]), [
            'expires_at' => $expiry,
            'password_enabled' => true,
            'password' => 'password123',
            'allow_download' => false,
            'allow_import' => false,
            'allow_direct_access' => false,
        ]);

        $response->assertStatus(200)->assertJson(['status' => 'success']);
        
        $file->refresh();
        $this->assertNotNull($file->share_expires_at);
        $this->assertTrue(Hash::check('password123', $file->share_password));
        $this->assertFalse($file->share_allow_download);
        $this->assertFalse($file->share_allow_import);
        $this->assertFalse($file->share_allow_direct_access);
    }

    /**
     * Test public share validation: expired links.
     */
    public function test_expired_public_link_shows_expired_view(): void
    {
        $user = User::factory()->create();
        $file = File::create([
            'name' => 'Expired.txt',
            'type' => 'file',
            'created_by' => $user->id,
            'share_token' => 'expired_token',
            'share_expires_at' => now()->subDay(),
        ]);

        $response = $this->get(route('drive.public.share', ['token' => 'expired_token']));
        $response->assertStatus(200);
        $response->assertSee('Link Expired');
    }

    /**
     * Test public share validation: password gates.
     */
    public function test_password_protected_public_link_gates_access(): void
    {
        $user = User::factory()->create();
        $file = File::create([
            'name' => 'Secret.txt',
            'type' => 'file',
            'created_by' => $user->id,
            'share_token' => 'secret_token',
            'share_password' => bcrypt('pass123'),
        ]);

        // Accessing without entering password should show password page
        $response = $this->get(route('drive.public.share', ['token' => 'secret_token']));
        $response->assertStatus(200);
        $response->assertSee('Password Protected');

        // Verify with wrong password
        $response = $this->post(route('drive.public.share.password', ['token' => 'secret_token']), [
            'password' => 'wrong',
        ]);
        $response->assertRedirect();
        $response->assertSessionHasErrors('password');

        // Verify with correct password
        $response = $this->post(route('drive.public.share.password', ['token' => 'secret_token']), [
            'password' => 'pass123',
        ]);
        $response->assertRedirect(route('drive.public.share', ['token' => 'secret_token']));
        
        // Re-accessing now should work as password is saved in session
        $response = $this->withSession(['shared_auth_' . $file->id => true])
            ->get(route('drive.public.share', ['token' => 'secret_token']));
        $response->assertStatus(200);
        $response->assertSee('Secret.txt');
    }

    /**
     * Test downloading public shared files.
     */
    public function test_can_download_public_shared_file(): void
    {
        $user = User::factory()->create();
        
        // Setup mock file storage
        $fakePath = 'test_path/MockFile.txt';
        Storage::disk('public')->put($fakePath, 'Hello Public World');

        $file = File::create([
            'name' => 'MockFile.txt',
            'type' => 'file',
            'created_by' => $user->id,
            'share_token' => 'dl_token',
            'storage_path' => $fakePath,
            'share_allow_download' => true,
        ]);

        $response = $this->get(route('drive.public.share.download', ['token' => 'dl_token']));
        $response->assertStatus(200);
        $this->assertEquals('Hello Public World', $response->streamedContent());
    }

    /**
     * Test downloading nested subfiles.
     */
    public function test_can_download_subfile_of_public_folder(): void
    {
        $user = User::factory()->create();

        $folder = File::create([
            'name' => 'SharedFolder',
            'type' => 'folder',
            'is_folder' => true,
            'created_by' => $user->id,
            'share_token' => 'folder_token',
            'share_allow_download' => true,
        ]);

        $fakePath = 'test_path/SubFile.txt';
        Storage::disk('public')->put($fakePath, 'Sub File Content');

        $subfile = File::create([
            'name' => 'SubFile.txt',
            'type' => 'file',
            'created_by' => $user->id,
            'parent_id' => $folder->id,
            'storage_path' => $fakePath,
        ]);

        $response = $this->get(route('drive.public.share.subfile.download', [
            'token' => 'folder_token',
            'subfile' => $subfile->id
        ]));

        $response->assertStatus(200);
        $this->assertEquals('Sub File Content', $response->streamedContent());
    }

    /**
     * Test importing public shared folder to current user's drive.
     */
    public function test_can_import_public_shared_item(): void
    {
        $user = User::factory()->create();
        $owner = User::factory()->create();

        $folder = File::create([
            'name' => 'SourceFolder',
            'type' => 'folder',
            'is_folder' => true,
            'created_by' => $owner->id,
            'share_token' => 'import_token',
            'share_allow_import' => true,
        ]);

        $subfile = File::create([
            'name' => 'ChildFile.txt',
            'type' => 'file',
            'created_by' => $owner->id,
            'parent_id' => $folder->id,
            'storage_path' => 'source/ChildFile.txt',
        ]);

        $response = $this->actingAs($user)->post(route('drive.public.share.import', ['token' => 'import_token']));

        $response->assertRedirect(route('drive.index'));
        
        // Assert items cloned successfully for importing user
        $clonedFolder = File::where('created_by', $user->id)->where('is_folder', true)->first();
        $this->assertNotNull($clonedFolder);
        $this->assertEquals('SourceFolder', $clonedFolder->name);

        $clonedChild = File::where('created_by', $user->id)->where('parent_id', $clonedFolder->id)->first();
        $this->assertNotNull($clonedChild);
        $this->assertEquals('ChildFile.txt', $clonedChild->name);
        $this->assertEquals('source/ChildFile.txt', $clonedChild->storage_path);
    }
}
