<?php

namespace Database\Factories;

use App\Enums\AnnouncementStatus;
use App\Enums\Condition;
use App\Enums\FuelType;
use App\Enums\Transmission;
use App\Models\Announcement;
use App\Models\Brand;
use App\Models\City;
use App\Models\User;
use App\Models\VehicleModel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class AnnouncementFactory extends Factory
{
    protected $model = Announcement::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'brand_id' => Brand::factory(),
            'model_id' => VehicleModel::factory(),
            'city_id' => City::factory(),
            'title' => fake()->sentence(4),
            'slug' => fn (array $a) => Str::slug($a['title']),
            'description' => fake()->paragraph(4),
            'price' => fake()->numberBetween(3000000, 40000000),
            'currency' => 'XOF',
            'year' => fake()->numberBetween(2008, 2024),
            'mileage' => fake()->numberBetween(10000, 200000),
            'fuel_type' => fake()->randomElement(FuelType::cases())->value,
            'transmission' => fake()->randomElement(Transmission::cases())->value,
            'condition' => fake()->randomElement(Condition::cases())->value,
            'body_type' => 'suv',
            'is_dedouane' => fake()->boolean(),
            'has_grise' => fake()->boolean(),
            'origin' => fake()->randomElement(['local', 'importe_ue', 'importe_asia']),
            'status' => AnnouncementStatus::Pending->value,
            'views_count' => 0,
            'contacts_count' => 0,
            'featured' => false,
            'boosted' => false,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'status' => AnnouncementStatus::Published->value,
            'published_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);
    }
}