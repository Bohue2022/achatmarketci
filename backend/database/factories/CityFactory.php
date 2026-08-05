<?php

namespace Database\Factories;

use App\Models\City;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CityFactory extends Factory
{
    protected $model = City::class;

    public function definition(): array
    {
        $name = fake()->unique()->city();
        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'region' => fake()->randomElement(['Abidjan', 'Vallée du Bandama', 'Bas-Sassandra']),
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
            'is_active' => true,
        ];
    }
}