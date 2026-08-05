<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use \Illuminate\Database\Console\Seeds\WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            CitySeeder::class,
            BrandSeeder::class,
            PlanSeeder::class,
            DemoDataSeeder::class,
        ]);
    }
}