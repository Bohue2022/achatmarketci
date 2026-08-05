<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Brand;
use App\Models\City;
use App\Models\Conversation;
use App\Models\User;
use App\Models\VehicleModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    private function publishedAnnouncement(User $seller): Announcement
    {
        $brand = Brand::create(['name' => fake()->unique()->word(), 'slug' => Str::slug(fake()->unique()->word())]);
        $model = VehicleModel::create(['brand_id' => $brand->id, 'name' => $brand->name . ' M', 'slug' => Str::slug($brand->name . '-m')]);
        $city = City::create(['name' => fake()->unique()->city(), 'slug' => Str::slug(fake()->unique()->city())]);

        return Announcement::factory()->published()->create([
            'user_id' => $seller->id,
            'brand_id' => $brand->id,
            'model_id' => $model->id,
            'city_id' => $city->id,
        ]);
    }

    public function test_public_profile_exposes_member_since_and_active_announcements_count(): void
    {
        $seller = User::factory()->pro()->create();
        $this->publishedAnnouncement($seller);

        $this->getJson("/api/users/{$seller->id}/profile")
            ->assertOk()
            ->assertJsonPath('data.name', $seller->name)
            ->assertJsonPath('data.role', 'pro')
            ->assertJsonPath('data.published_announcements_count', 1)
            ->assertJsonPath('data.member_since', $seller->created_at->toIso8601String())
            ->assertJsonPath('data.contact', null);
    }

    public function test_only_active_published_announcements_are_counted(): void
    {
        $seller = User::factory()->create();
        $this->publishedAnnouncement($seller);

        // Une annonce en attente ne doit pas compter
        Announcement::factory()->create(['user_id' => $seller->id, 'brand_id' => Brand::factory(), 'model_id' => VehicleModel::factory(), 'city_id' => City::factory()]);

        $this->getJson("/api/users/{$seller->id}/profile")
            ->assertJsonPath('data.published_announcements_count', 1);
    }

    public function test_contact_is_hidden_without_shared_conversation(): void
    {
        $seller = User::factory()->pro()->create();
        $stranger = User::factory()->create();

        $this->actingAs($stranger, 'sanctum')
            ->getJson("/api/users/{$seller->id}/profile")
            ->assertOk()
            ->assertJsonPath('data.contact', null);
    }

    public function test_contact_is_revealed_when_they_share_a_conversation(): void
    {
        $seller = User::factory()->pro()->create();
        $buyer = User::factory()->create();
        $announcement = $this->publishedAnnouncement($seller);
        Conversation::create(['announcement_id' => $announcement->id, 'buyer_id' => $buyer->id, 'last_message_at' => now()]);

        $this->actingAs($buyer, 'sanctum')
            ->getJson("/api/users/{$seller->id}/profile")
            ->assertOk()
            ->assertJsonPath('data.contact.phone', $seller->phone);
    }
}