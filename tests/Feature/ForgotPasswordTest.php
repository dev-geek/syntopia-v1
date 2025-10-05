<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\AdminResetPasswordNotification;
use Illuminate\Auth\Notifications\ResetPassword as UserResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ForgotPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_request_password_reset_link(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'user@example.com',
        ]);

        $response = $this->post(route('password.email'), [
            'email' => 'user@example.com',
        ]);

        $response->assertSessionHas('status');

        Notification::assertSentTo($user, UserResetPasswordNotification::class);

        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => 'user@example.com',
        ]);
    }

    public function test_sub_admin_can_request_admin_reset_link_without_security_questions(): void
    {
        Notification::fake();

        $subAdmin = User::factory()->create([
            'email' => 'subadmin@example.com',
        ]);
        Role::firstOrCreate(['name' => 'Sub Admin', 'guard_name' => 'web']);
        $subAdmin->assignRole('Sub Admin');

        $response = $this->post(route('admin.password.email'), [
            'email' => 'subadmin@example.com',
        ]);

        $response->assertSessionHas('status');

        Notification::assertSentTo($subAdmin, AdminResetPasswordNotification::class);

        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => 'subadmin@example.com',
        ]);
    }

    public function test_super_admin_requires_security_answers_and_sends_link_when_correct(): void
    {
        Notification::fake();

        $superAdmin = User::factory()->create([
            'email' => 'superadmin@example.com',
            'city' => 'Lahore',
            'pet' => 'Dog',
        ]);
        Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $superAdmin->assignRole('Super Admin');

        // Wrong answers should fail
        $failResponse = $this->post(route('admin.password.email'), [
            'email' => 'superadmin@example.com',
            'city' => 'WrongCity',
            'pet' => 'WrongPet',
        ]);
        $failResponse->assertSessionHasErrors();

        Notification::assertNothingSent();

        // Correct answers should pass and send link
        $okResponse = $this->post(route('admin.password.email'), [
            'email' => 'superadmin@example.com',
            'city' => 'Lahore',
            'pet' => 'Dog',
        ]);

        $okResponse->assertSessionHas('status');

        Notification::assertSentTo($superAdmin, AdminResetPasswordNotification::class);

        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => 'superadmin@example.com',
        ]);
    }
}


