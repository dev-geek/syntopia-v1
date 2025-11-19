<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

class ProfileValidationTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test user
        $this->user = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);
    }

    public function test_user_can_access_profile_page()
    {
        $response = $this->actingAs($this->user)
            ->get('/profile');

        $response->assertStatus(200);
        $response->assertSee('My Profile');
    }

    public function test_profile_update_requires_name()
    {
        $response = $this->actingAs($this->user)
            ->post('/profile/update', [
                'name' => '',
                'password' => '',
                'password_confirmation' => '',
            ]);

        $response->assertSessionHasErrors(['name']);
        $response->assertSessionHasErrors(['name' => 'Please enter your name.']);
    }

    public function test_profile_update_validates_name_length()
    {
        $response = $this->actingAs($this->user)
            ->post('/profile/update', [
                'name' => 'A',
                'password' => '',
                'password_confirmation' => '',
            ]);

        $response->assertSessionHasErrors(['name']);
        $response->assertSessionHasErrors(['name' => 'Name must be at least 2 characters long.']);
    }

    public function test_profile_update_validates_name_format()
    {
        $response = $this->actingAs($this->user)
            ->post('/profile/update', [
                'name' => 'John123',
                'password' => '',
                'password_confirmation' => '',
            ]);

        $response->assertSessionHasErrors(['name']);
        $response->assertSessionHasErrors(['name' => 'Name can only contain letters and spaces.']);
    }

    public function test_profile_update_validates_password_length()
    {
        $response = $this->actingAs($this->user)
            ->post('/profile/update', [
                'name' => 'John Doe',
                'password' => 'short',
                'password_confirmation' => 'short',
            ]);

        $response->assertSessionHasErrors(['password']);
        $response->assertSessionHasErrors(['password' => 'Password must be at least 8 characters long.']);
    }

    public function test_profile_update_validates_password_max_length()
    {
        $response = $this->actingAs($this->user)
            ->post('/profile/update', [
                'name' => 'John Doe',
                'password' => str_repeat('A', 31),
                'password_confirmation' => str_repeat('A', 31),
            ]);

        $response->assertSessionHasErrors(['password']);
    }

    public function test_profile_update_validates_password_confirmation()
    {
        $response = $this->actingAs($this->user)
            ->post('/profile/update', [
                'name' => 'John Doe',
                'password' => 'ValidPass123!',
                'password_confirmation' => 'DifferentPass123!',
            ]);

        $response->assertSessionHasErrors(['password']);
        $response->assertSessionHasErrors(['password' => 'Password confirmation does not match.']);
    }

    public function test_profile_update_validates_password_complexity()
    {
        $response = $this->actingAs($this->user)
            ->post('/profile/update', [
                'name' => 'John Doe',
                'password' => 'simplepassword',
                'password_confirmation' => 'simplepassword',
            ]);

        $response->assertSessionHasErrors(['password']);
        $response->assertSessionHasErrors(['password' => 'Password must contain at least one number, one uppercase letter, one lowercase letter, and one special character (,.<>{}~!@#$%^&_).']);
    }

    public function test_profile_update_succeeds_with_valid_data()
    {
        $response = $this->actingAs($this->user)
            ->post('/profile/update', [
                'name' => 'Jane Doe',
                'password' => '',
                'password_confirmation' => '',
            ]);

        $response->assertSessionHas('success', 'Profile updated successfully!');

        $this->user->refresh();
        $this->assertEquals('Jane Doe', $this->user->name);
    }

    public function test_profile_update_succeeds_with_valid_password()
    {
        $response = $this->actingAs($this->user)
            ->post('/profile/update', [
                'name' => 'John Doe',
                'password' => 'ValidPass123,',
                'password_confirmation' => 'ValidPass123,',
            ]);

        $response->assertSessionHas('success', 'Profile updated successfully!');
    }

    public function test_profile_update_shows_no_changes_message()
    {
        $response = $this->actingAs($this->user)
            ->post('/profile/update', [
                'name' => 'John Doe', // Same as current name
                'password' => '',
                'password_confirmation' => '',
            ]);

        $response->assertSessionHas('info', 'No changes were made.');
    }
}
