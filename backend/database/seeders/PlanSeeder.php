<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Gratuit',
                'slug' => 'free',
                'is_free' => true,
                'price_monthly' => 0,
                'price_yearly' => 0,
                'active_announcement_limit' => 2,
                'listing_duration_days' => 30,
                'features' => ['2 annonces actives', 'Durée de 30 jours', 'Sans mise en avant'],
                'sort' => 1,
            ],
            [
                'name' => 'Starter',
                'slug' => 'starter',
                'is_free' => false,
                'price_monthly' => 15000,
                'price_yearly' => 150000,
                'active_announcement_limit' => 10,
                'listing_duration_days' => 45,
                'features' => ['10 annonces actives', 'Badge professionnel', 'Statistiques de base'],
                'sort' => 2,
            ],
            [
                'name' => 'Business',
                'slug' => 'business',
                'is_free' => false,
                'price_monthly' => 35000,
                'price_yearly' => 360000,
                'active_announcement_limit' => 100,
                'listing_duration_days' => 60,
                'features' => ['100 annonces actives', 'Page vitrine', 'Statistiques détaillées', 'Badge vérifié'],
                'sort' => 3,
            ],
            [
                'name' => 'Premium',
                'slug' => 'premium',
                'is_free' => false,
                'price_monthly' => 75000,
                'price_yearly' => 780000,
                'active_announcement_limit' => null, // illimité
                'listing_duration_days' => 90,
                'features' => ['Annonces illimitées', 'Mise en avant homepage', 'Support prioritaire', 'API d\'import', 'Badge vérifié'],
                'sort' => 4,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(['slug' => $plan['slug']], $plan);
        }
    }
}