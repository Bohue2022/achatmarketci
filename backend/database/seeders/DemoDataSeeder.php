<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Announcement;
use App\Models\Brand;
use App\Models\City;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        // --- Comptes de test ---
        $admin = User::create([
            'name' => 'Admin Plateforme',
            'email' => 'admin@rr.ci',
            'phone' => '+2250700000001',
            'password' => 'password',
            'role' => Role::Admin,
            'email_verified_at' => now(),
        ]);

        $moderator = User::create([
            'name' => 'Modérateur Test',
            'email' => 'modo@rr.ci',
            'phone' => '+2250700000002',
            'password' => 'password',
            'role' => Role::Moderator,
            'email_verified_at' => now(),
        ]);

        $pro = User::create([
            'name' => 'Auto Import Abidjan',
            'email' => 'pro@rr.ci',
            'phone' => '+2250700000003',
            'password' => 'password',
            'role' => Role::Pro,
            'company_name' => 'Auto Import Abidjan',
            'is_verified_pro' => true,
            'rccm_number' => 'CI-ABJ-2023-12345',
            'whatsapp' => '+2250700000003',
            'bio' => 'Importateur de véhicules d\'Europe et d\'Asie. Dédouanement inclus.',
            'kyc_verified_at' => now(),
            'email_verified_at' => now(),
        ]);

        $particulier = User::create([
            'name' => 'Jean Kouassi',
            'email' => 'particulier@rr.ci',
            'phone' => '+2250700000004',
            'password' => 'password',
            'role' => Role::User,
            'email_verified_at' => now(),
        ]);

        // --- Villes pour les annonces ---
        $cocody = City::where('slug', 'cocody')->first();
        $yopougon = City::where('slug', 'yopougon')->first();
        $abidjan = City::where('slug', 'abidjan')->first();
        $bouake = City::where('slug', 'bouake')->first();

        // --- Annonces de démonstration ---
        $toyota = Brand::where('name', 'Toyota')->first();
        $rav4 = $toyota?->models()->where('name', 'RAV4')->first();
        $hilux = $toyota?->models()->where('name', 'Hilux')->first();
        $mercedes = Brand::where('name', 'Mercedes-Benz')->first();
        $gle = $mercedes?->models()->where('name', 'GLE')->first();
        $hyundai = Brand::where('name', 'Hyundai')->first();
        $tucson = $hyundai?->models()->where('name', 'Tucson')->first();
        $peugeot = Brand::where('name', 'Peugeot')->first();
        $p3008 = $peugeot?->models()->where('name', '3008')->first();

        $this->createAnnouncement([
            'user' => $pro, 'brand' => $toyota, 'model' => $rav4,
            'city' => $abidjan, 'commune' => $cocody,
            'title' => 'Toyota RAV4 2021 - Dédouané, carte grise disponible',
            'price' => 18500000, 'year' => 2021, 'mileage' => 48000,
            'fuel_type' => 'essence', 'transmission' => 'automatique',
            'condition' => 'occasion', 'body_type' => 'suv',
            'is_dedouane' => true, 'has_grise' => true, 'origin' => 'importe_ue',
            'status' => 'published',
        ]);

        $this->createAnnouncement([
            'user' => $pro, 'brand' => $toyota, 'model' => $hilux,
            'city' => $abidjan, 'commune' => $yopougon,
            'title' => 'Toyota Hilux Double Cabine 2022',
            'price' => 24500000, 'year' => 2022, 'mileage' => 30000,
            'fuel_type' => 'diesel', 'transmission' => 'manuelle',
            'condition' => 'occasion', 'body_type' => 'pickup',
            'is_dedouane' => true, 'has_grise' => true, 'origin' => 'importe_ue',
            'status' => 'published',
        ]);

        $this->createAnnouncement([
            'user' => $pro, 'brand' => $mercedes, 'model' => $gle,
            'city' => $abidjan, 'commune' => $cocody,
            'title' => 'Mercedes GLE 300d 2020 - Garantie 6 mois',
            'price' => 42000000, 'year' => 2020, 'mileage' => 55000,
            'fuel_type' => 'diesel', 'transmission' => 'automatique',
            'condition' => 'occasion', 'body_type' => 'suv',
            'is_dedouane' => true, 'has_grise' => true, 'origin' => 'importe_ue',
            'status' => 'published', 'featured' => true,
        ]);

        $this->createAnnouncement([
            'user' => $pro, 'brand' => $hyundai, 'model' => $tucson,
            'city' => $bouake,
            'title' => 'Hyundai Tucson 2019 Essence',
            'price' => 12500000, 'year' => 2019, 'mileage' => 70000,
            'fuel_type' => 'essence', 'transmission' => 'automatique',
            'condition' => 'occasion', 'body_type' => 'suv',
            'is_dedouane' => false, 'has_grise' => false, 'origin' => 'importe_asia',
            'status' => 'published',
        ]);

        $this->createAnnouncement([
            'user' => $particulier, 'brand' => $peugeot, 'model' => $p3008,
            'city' => $abidjan, 'commune' => $cocody,
            'title' => 'Peugeot 3008 2018 - Non dédouané (papiers UE)',
            'price' => 9800000, 'year' => 2018, 'mileage' => 92000,
            'fuel_type' => 'diesel', 'transmission' => 'manuelle',
            'condition' => 'occasion', 'body_type' => 'suv',
            'is_dedouane' => false, 'has_grise' => false, 'origin' => 'importe_ue',
            'status' => 'pending',
        ]);

        $this->createAnnouncement([
            'user' => $particulier, 'brand' => $peugeot, 'model' => $p3008,
            'city' => $abidjan, 'commune' => $cocody,
            'title' => 'Peugeot 3008 GT Line 2019 — prix suspect (test anomalie)',
            'price' => 2500000, 'year' => 2019, 'mileage' => 60000,
            'fuel_type' => 'diesel', 'transmission' => 'automatique',
            'condition' => 'occasion', 'body_type' => 'suv',
            'is_dedouane' => true, 'has_grise' => false, 'origin' => 'local',
            'status' => 'pending',
        ]);

        $this->createAnnouncement([
            'user' => $pro, 'brand' => $toyota, 'model' => $rav4,
            'city' => $abidjan, 'commune' => $cocody,
            'title' => 'Toyota RAV4 Hybride 2023 — neuf, boîte automatique',
            'price' => 32000000, 'year' => 2023, 'mileage' => 0,
            'fuel_type' => 'hybride', 'transmission' => 'automatique',
            'condition' => 'neuf', 'body_type' => 'suv',
            'is_dedouane' => true, 'has_grise' => true, 'origin' => 'local',
            'status' => 'published', 'featured' => true,
        ]);

        echo "Demo seeded. Admin: admin@rr.ci / password\n";
    }

    protected function createAnnouncement(array $data): void
    {
        $user = $data['user'];
        $brand = $data['brand'];
        $model = $data['model'];

        if (! $brand || ! $model) {
            return;
        }

        $announcement = $user->announcements()->create([
            'brand_id' => $brand->id,
            'model_id' => $model->id,
            'city_id' => $data['city']->id,
            'commune_id' => $data['commune']->id ?? null,
            'title' => $data['title'],
            'slug' => \Illuminate\Support\Str::slug($data['title']),
            'description' => $data['title'] . ". Véhicule disponible à la vente, état " . $data['condition'] . ".\n\nContactez le vendeur pour plus d'informations et une visite.",
            'price' => $data['price'],
            'year' => $data['year'],
            'mileage' => $data['mileage'],
            'fuel_type' => $data['fuel_type'],
            'transmission' => $data['transmission'],
            'condition' => $data['condition'],
            'body_type' => $data['body_type'],
            'is_dedouane' => $data['is_dedouane'],
            'has_grise' => $data['has_grise'],
            'origin' => $data['origin'],
            'status' => $data['status'],
            'featured' => $data['featured'] ?? false,
            'published_at' => $data['status'] === 'published' ? now()->subDays(rand(1, 20)) : null,
            'expires_at' => now()->addDays(30),
            'views_count' => rand(50, 2000),
        ]);
    }
}