<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthLoginScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_resets_drive_scope_session_to_personal(): void
    {
        // 1. Create a user
        $user = User::factory()->create([
            'email' => 'testuser@example.com',
            'password' => bcrypt('password123'),
        ]);

        // 2. Put something else in session initially
        session(['drive_scope' => 'project']);

        // 3. Post to login
        $response = $this->post(route('login.store'), [
            'email' => 'testuser@example.com',
            'password' => 'password123',
        ]);

        // 4. Assert redirect
        $response->assertRedirect('/');

        // 5. Assert session has 'drive_scope' set to 'personal'
        $this->assertEquals('personal', session('drive_scope'));
    }
}
