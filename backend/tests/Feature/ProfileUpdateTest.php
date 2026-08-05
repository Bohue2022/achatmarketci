<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\City;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_update_profile(): void
    {
        $this->postJson('/api/auth/profile', ['name' => 'X', 'phone' => '+2250712345678'])
            ->assertStatus(401);
    }

    public function test_user_can_update_profile_fields(): void
    {
        $user = User::factory()->create();
        $city = City::create(['name' => 'Bouaké', 'slug' => 'bouake']);

        $this->actingAs($user)
            ->postJson('/api/auth/profile', [
                'name' => 'Nouveau Nom',
                'phone' => '+2250123456789',
                'whatsapp' => '+2250123456789',
                'bio' => 'Je suis passionné de voitures.',
                'city_id' => $city->id,
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Profil mis à jour.')
            ->assertJsonPath('user.name', 'Nouveau Nom')
            ->assertJsonPath('user.city', 'Bouaké');

        $this->assertDatabaseHas('users', [
            'id' => $user->id, 'name' => 'Nouveau Nom', 'bio' => 'Je suis passionné de voitures.', 'city_id' => $city->id,
        ]);
    }

    public function test_phone_is_validated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/auth/profile', ['name' => 'X', 'phone' => 'abc'])
            ->assertStatus(422);
    }

    public function test_pro_can_update_company_info(): void
    {
        $user = User::factory()->pro()->create();

        $this->actingAs($user)
            ->postJson('/api/auth/profile', [
                'name' => $user->name,
                'phone' => $user->phone,
                'company_name' => 'Garage Central Abidjan',
                'rccm_number' => 'CI-ABJ-2023-001',
            ])
            ->assertOk()
            ->assertJsonPath('user.company_name', 'Garage Central Abidjan');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'rccm_number' => 'CI-ABJ-2023-001']);
    }

    public function test_avatar_upload_stores_and_replaces_previous(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['avatar_path' => 'avatars/old.png']);

        $this->actingAs($user)
            ->post('/api/auth/profile', [
                'name' => $user->name,
                'phone' => $user->phone,
                'avatar' => UploadedFile::fake()->image('photo.png', 60, 60),
            ])
            ->assertOk();

        $this->assertNotEquals('avatars/old.png', $user->fresh()->avatar_path);
        $this->assertTrue(Storage::disk('public')->exists($user->fresh()->avatar_path));
        $this->assertFalse(Storage::disk('public')->exists('avatars/old.png'));
    }
}
