<?php

namespace Tests\Feature;

use App\Enums\AnnouncementStatus;
use App\Models\Announcement;
use App\Models\Brand;
use App\Models\City;
use App\Models\User;
use App\Models\VehicleModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnnouncementTest extends TestCase
{
    use RefreshDatabase;

    private function setupReferential(): array
    {
        $brand = Brand::create(['name' => 'Toyota', 'slug' => 'toyota']);
        $model = VehicleModel::create(['brand_id' => $brand->id, 'name' => 'RAV4', 'slug' => 'rav4']);
        $city = City::create(['name' => 'Abidjan', 'slug' => 'abidjan']);
        return [$brand, $model, $city];
    }

    private function validPayload(Brand $brand, VehicleModel $model, City $city): array
    {
        return [
            'brand_id' => $brand->id,
            'model_id' => $model->id,
            'city_id' => $city->id,
            'title' => 'Toyota RAV4 2020 à vendre',
            'description' => 'Véhicule en très bon état, entretien suivi, disponible immédiatement.',
            'price' => 15000000,
            'year' => 2020,
            'mileage' => 60000,
            'fuel_type' => 'essence',
            'transmission' => 'automatique',
            'condition' => 'occasion',
            'body_type' => 'suv',
            'is_dedouane' => true,
            'has_grise' => true,
            'origin' => 'importe_ue',
        ];
    }

    public function test_user_can_create_announcement_that_goes_to_pending(): void
    {
        [$brand, $model, $city] = $this->setupReferential();
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/announcements', $this->validPayload($brand, $model, $city));

        $response->assertStatus(201)
            ->assertJsonPath('data.status', AnnouncementStatus::Pending->value)
            ->assertJsonStructure(['data' => ['id', 'slug', 'price']]);
    }

    public function test_particulier_can_publish_without_limit_during_free_launch(): void
    {
        [$brand, $model, $city] = $this->setupReferential();
        $user = User::factory()->create();

        // Lancement gratuit : aucun quota n'est appliqué aux particuliers.
        Announcement::factory()->count(5)->create([
            'user_id' => $user->id,
            'brand_id' => $brand->id,
            'model_id' => $model->id,
            'city_id' => $city->id,
        ]);

        $this->actingAs($user)
            ->postJson('/api/announcements', $this->validPayload($brand, $model, $city))
            ->assertStatus(201)
            ->assertJsonPath('data.status', AnnouncementStatus::Pending->value);
    }

    public function test_listing_exposes_active_announcements_only(): void
    {
        [$brand, $model, $city] = $this->setupReferential();
        $user = User::factory()->create();

        // 2 publiées + 1 en attente (doit être masquée)
        Announcement::factory()->count(2)->published()->create([
            'user_id' => $user->id, 'brand_id' => $brand->id, 'model_id' => $model->id, 'city_id' => $city->id,
        ]);
        Announcement::factory()->create([
            'user_id' => $user->id, 'brand_id' => $brand->id, 'model_id' => $model->id, 'city_id' => $city->id,
        ]);

        $this->getJson('/api/announcements')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_detail_increments_views(): void
    {
        [$brand, $model, $city] = $this->setupReferential();
        $announcement = Announcement::factory()->published()->create([
            'brand_id' => $brand->id, 'model_id' => $model->id, 'city_id' => $city->id,
        ]);

        $this->getJson('/api/announcements/' . $announcement->slug)->assertOk();

        $this->assertDatabaseHas('announcements', ['id' => $announcement->id, 'views_count' => 1]);
    }

    public function test_filters_are_applied(): void
    {
        [$brand, $model, $city] = $this->setupReferential();
        $user = User::factory()->create();

        Announcement::factory()->published()->create([
            'user_id' => $user->id, 'brand_id' => $brand->id, 'model_id' => $model->id, 'city_id' => $city->id,
            'fuel_type' => 'diesel', 'price' => 20000000,
        ]);
        Announcement::factory()->published()->create([
            'user_id' => $user->id, 'brand_id' => $brand->id, 'model_id' => $model->id, 'city_id' => $city->id,
            'fuel_type' => 'essence', 'price' => 8000000,
        ]);

        $this->getJson('/api/announcements?fuel_type=diesel')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson('/api/announcements?price_max=10000000')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_owner_can_delete_own_announcement(): void
    {
        [$brand, $model, $city] = $this->setupReferential();
        $user = User::factory()->create();

        $announcement = Announcement::factory()->published()->create([
            'user_id' => $user->id, 'brand_id' => $brand->id, 'model_id' => $model->id, 'city_id' => $city->id,
        ]);

        $this->actingAs($user)
            ->deleteJson('/api/announcements/' . $announcement->id)
            ->assertOk();

        $this->assertDatabaseMissing('announcements', ['id' => $announcement->id]);
    }
}