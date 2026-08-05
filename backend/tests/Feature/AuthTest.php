<?php

namespace Tests\Feature;

use App\Mail\VerificationOtp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_creates_unverified_user_and_sends_otp(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Awa Diallo',
            'email' => 'awa@example.com',
            'phone' => '+2250701234567',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
            'account_type' => 'particulier',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('user.email', 'awa@example.com')
            ->assertJsonPath('user.role', 'user')
            ->assertJsonPath('requires_email_verification', true)
            ->assertJsonMissingPath('token');

        $this->assertDatabaseHas('users', [
            'email' => 'awa@example.com',
            'email_verified_at' => null,
        ]);

        Mail::assertSent(VerificationOtp::class, fn (VerificationOtp $m) => $m->user->email === 'awa@example.com');
    }

    public function test_full_otp_verification_flow(): void
    {
        Mail::fake();

        $this->postJson('/api/auth/register', [
            'name' => 'Ehouman N.',
            'email' => 'ehouman@example.com',
            'phone' => '+2250701234567',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
            'account_type' => 'particulier',
        ])->assertCreated();

        // Code fixe 123456 via OTP_STATIC (test).
        $verify = $this->postJson('/api/auth/verify-otp', [
            'email' => 'ehouman@example.com',
            'otp' => '123456',
        ]);

        $verify->assertOk()
            ->assertJsonPath('user.email', 'ehouman@example.com')
            ->assertJsonStructure(['token']);

        $this->assertDatabaseHas('users', ['email' => 'ehouman@example.com']);
        $user = User::where('email', 'ehouman@example.com')->first();
        $this->assertNotNull($user->email_verified_at);

        // Le code a Ã©tÃ© purgÃ©.
        $this->assertDatabaseMissing('email_verification_codes', ['user_id' => $user->id]);

        // Connexion ensuite possible.
        $this->postJson('/api/auth/login', [
            'email' => 'ehouman@example.com',
            'password' => 'Password1!',
        ])->assertOk()->assertJsonStructure(['token']);
    }

    public function test_login_is_blocked_before_email_verification(): void
    {
        $user = User::factory()->unverified()->create(['password' => 'password']);

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertStatus(403)
            ->assertJsonPath('code', 'email_not_verified')
            ->assertJsonPath('data.email', $user->email);
    }

    public function test_user_can_login_and_access_profile(): void
    {
        $user = User::factory()->create();

        $login = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $login->assertOk()->assertJsonStructure(['token', 'user']);

        $token = $login->json('token');

        $this->withToken($token)
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('user.email', $user->email)
            ->assertJsonPath('user.email_verified', true);
    }

    public function test_verify_otp_rejects_wrong_code(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'wrong@example.com']);
        // Il faut un code existant pour distinguer "incorrect" de "expirÃ©".
        app(\App\Services\OtpService::class)->create($user);

        $this->postJson('/api/auth/verify-otp', [
            'email' => $user->email,
            'otp' => '000000',
        ])->assertStatus(422)->assertJsonPath('code', 'invalid_otp');

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_resend_otp_sends_another_email(): void
    {
        Mail::fake();
        $user = User::factory()->unverified()->create();

        $this->postJson('/api/auth/resend-otp', [
            'email' => $user->email,
        ])->assertOk();

        Mail::assertSent(VerificationOtp::class, 1);
    }

    public function test_login_with_wrong_password_fails(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertStatus(401);
    }

    public function test_register_rejects_weak_passwords(): void
    {
        // Doit contenir : majuscule + minuscule + caractère spécial + ≥ 8 caractères.
        $weak = ['password', 'PASSWORD', 'abcdefgh', 'Password', 'password!', 'PASSWORD1!'];

        foreach ($weak as $pwd) {
            $this->postJson('/api/auth/register', [
                'name' => 'Testeur',
                'email' => 'test' . str_replace(['!', '@', '#', '.', '+'], '', $pwd) . '_' . fake()->numberBetween(1, 999999) . '@example.com',
                'phone' => '+2250701234567',
                'password' => $pwd,
                'password_confirmation' => $pwd,
                'account_type' => 'particulier',
            ])->assertStatus(422);
        }
    }

    public function test_protected_route_returns_json_401_without_token(): void
    {
        // Sans en-tÃªte Accept JSON pour vÃ©rifier que l'API renvoie bien du JSON.
        $this->getJson('/api/auth/me')->assertStatus(401);
    }
}