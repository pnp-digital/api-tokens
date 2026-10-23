<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\DatabaseTestCase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Auth\Notifications\ResetPassword;

class AuthenticationTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware();

        $user = new User();
        $user->id = 1;
        $user->email = 'test@example.com';

        $this->actingAs($user);
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('app.key', 'base64:' . base64_encode(str_repeat('a', 32)));
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        User::query()->create([
            'name' => 'Test User',
            'email' => 'login@example.com',
            'password' => Hash::make('correct-password'),
        ]);

        $this->postJson('/api/v1/login', [
            'email' => 'login@example.com',
            'password' => 'wrong-password',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_change_password_rejects_missing_current_password(): void
    {
        $user = User::query()->create([
            'name' => 'Test User',
            'email' => 'password@example.com',
            'password' => Hash::make('old-password'),
        ]);

        $this->actingAs($user);

        $this->postJson('/api/v1/changepassword', [
            'new_password' => 'new-password',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['old_password']);
    }

    public function test_change_password_rejects_missing_new_password(): void
    {
        $user = User::query()->create([
            'name' => 'Test User',
            'email' => 'password@example.com',
            'password' => Hash::make('old-password'),
        ]);

        $this->actingAs($user);

        $this->postJson('/api/v1/changepassword', [
            'old_password' => 'old-password',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['new_password']);
    }

    public function test_change_password_rejects_incorrect_current_password(): void
    {
        $user = User::query()->create([
            'name' => 'Test User',
            'email' => 'password@example.com',
            'password' => Hash::make('old-password'),
        ]);

        $this->actingAs($user);

        $this->postJson('/api/v1/changepassword', [
            'old_password' => 'wrong-password',
            'new_password' => 'new-password',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['old_password']);

        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
    }

    public function test_change_password_updates_password(): void
    {
        $user = User::query()->create([
            'name' => 'Test User',
            'email' => 'password@example.com',
            'password' => Hash::make('old-password'),
        ]);

        $this->actingAs($user);

        $this->postJson('/api/v1/changepassword', [
            'old_password' => 'old-password',
            'new_password' => 'new-password',
        ])
            ->assertSuccessful()
            ->assertJsonPath('data.status', true);

        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
    }

    public function test_logout_logs_out_current_user(): void
    {
        $user = User::query()->create([
            'name' => 'Test User',
            'email' => 'logout@example.com',
            'password' => Hash::make('password'),
        ]);

        $this->actingAs($user);

        $this->postJson('/api/v1/logout')->assertSuccessful();

        $this->assertGuest();
    }

    public function test_user_can_login(): void
    {
        $user = User::query()->create([
            'email' => 'login@example.com',
            'password' => Hash::make('password123'),
            'is_enabled' => true,
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email' => 'login@example.com',
            'password' => 'password123',
            'device_name' => 'PHPUnit',
        ]);

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.0.id', $user->id)
            ->assertJsonPath('data.0.email', 'login@example.com');

        $this->assertIsString($response->json('data.0.token'));
        $this->assertNotSame('', $response->json('data.0.token'));
    }

    public function test_login_rejects_incorrect_two_factor_code(): void
    {
        User::query()->create([
            'email' => '2fa-wrong@example.com',
            'password' => Hash::make('password123'),
            'two_factor_recovery_codes' => '*',
            'two_factor_secret' => '123456',
            'updated_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email' => '2fa-wrong@example.com',
            'password' => 'password123',
            'two_factor_code' => '654321',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->assertSame('Two factor authentication code is incorrect', $response->json('errors.email.0'));
    }

    public function test_login_accepts_correct_two_factor_code_and_clears_it(): void
    {
        $user = User::query()->create([
            'email' => '2fa-correct@example.com',
            'password' => Hash::make('password123'),
            'two_factor_recovery_codes' => '*',
            'two_factor_secret' => '123456',
            'updated_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email' => '2fa-correct@example.com',
            'password' => 'password123',
            'two_factor_code' => '123456',
            'device_name' => 'PHPUnit',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.0.id', $user->id)
            ->assertJsonPath('data.0.email', '2fa-correct@example.com')
            ->assertJsonPath('data.0.token', 'test-token');

        $user->refresh();

        $this->assertSame('', $user->two_factor_secret);
    }

    public function test_registration_can_be_disabled(): void
    {
        putenv('API_USER_REGISTRATION_DISABLED=1');

        try {
            $this->postJson('/api/v1/register', [
                'name' => 'Test User',
                'email' => 'register@example.com',
                'password' => 'password123',
            ])
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['email']);
        } finally {
            putenv('API_USER_REGISTRATION_DISABLED');
        }
    }

    public function test_registration_rejects_existing_user(): void
    {
        User::query()->create([
            'name' => 'Existing User',
            'email' => 'existing@example.com',
            'password' => Hash::make('password123'),
        ]);

        $this->postJson('/api/v1/register', [
            'name' => 'Test User',
            'email' => 'existing@example.com',
            'password' => 'password123',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_registration_rejects_soft_deleted_existing_user(): void
    {
        $user = User::query()->create([
            'name' => 'Existing User',
            'email' => 'deleted@example.com',
            'password' => Hash::make('password123'),
        ]);

        $user->delete();

        $this->postJson('/api/v1/register', [
            'name' => 'Test User',
            'email' => 'deleted@example.com',
            'password' => 'password123',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_password_reset_can_be_disabled(): void
    {
        putenv('API_USER_PASSWORD_RESET_DISABLED=1');

        try {
            $this->postJson('/api/v1/resetpassword', [
                'email' => 'reset@example.com',
            ])
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['email']);
        } finally {
            putenv('API_USER_PASSWORD_RESET_DISABLED');
        }
    }

    public function test_login_generates_two_factor_code_when_required(): void
    {
        $user = User::query()->create([
            'email' => '2fa-send@example.com',
            'password' => Hash::make('password123'),
            'two_factor_recovery_codes' => '*',
            'two_factor_secret' => null,
            'updated_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email' => '2fa-send@example.com',
            'password' => 'password123',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->assertSame(
            'Two factor authentication is enabled for this user; email could not be sent. Please try again later: The "' . app_path('Mail') . '" directory does not exist.',
            $response->json('errors.email.0')
        );

        $user->refresh();

        $this->assertMatchesRegularExpression('/^\d{6}$/', $user->two_factor_secret);
        $this->assertGuest();
    }

    public function test_login_regenerates_two_factor_code_when_resend_requested(): void
    {
        $user = User::query()->create([
            'email' => '2fa-resend@example.com',
            'password' => Hash::make('password123'),
            'two_factor_recovery_codes' => '*',
            'two_factor_secret' => '123456',
            'updated_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email' => '2fa-resend@example.com',
            'password' => 'password123',
            'resend' => true,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $user->refresh();

        $this->assertMatchesRegularExpression('/^\d{6}$/', $user->two_factor_secret);
        $this->assertGuest();
    }

    public function test_password_reset_sends_reset_notification(): void
    {
        Notification::fake();

        $user = User::query()->create([
            'name' => 'Reset User',
            'email' => 'reset@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/v1/resetpassword', [
            'email' => 'reset@example.com',
        ]);

        $response->assertSuccessful()
            ->assertJsonPath('data.reset_status', 'passwords.sent');

        Notification::assertSentTo(
            $user,
            ResetPassword::class,
            function (ResetPassword $notification): bool {
                return !empty($notification->token);
            }
        );
    }

    public function test_user_can_register(): void
    {
        $response = $this->postJson('/api/v1/register', [
            'name' => 'Registered User',
            'email' => 'register@example.com',
            'password' => 'hunter2',
            'device_name' => 'PHPUnit',
        ]);

        $response->assertSuccessful()
            ->assertJsonPath('data.0.email', 'register@example.com')
            ->assertJsonPath('data.0.token', 'test-token')
            ->assertJsonPath('data.0.registration_status', true);

        $user = User::query()->where('email', 'register@example.com')->first();

        $this->assertNotNull($user);
        $this->assertSame('Registered User', $user->name);
        $this->assertTrue(Hash::check('hunter2', $user->password));
        $this->assertTrue((bool)$user->is_enabled);
    }
}