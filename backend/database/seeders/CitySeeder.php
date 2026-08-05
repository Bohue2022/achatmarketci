<?php

namespace Database\Seeders;

use App\Models\City;
use Illuminate\Database\Seeder;

class CitySeeder extends Seeder
{
    public function run(): void
    {
        // Abidjan + ses communes (parent)
        $abidjan = City::create([
            'name' => 'Abidjan',
            'slug' => 'abidjan',
            'region' => 'Abidjan',
            'latitude' => 5.360, 'longitude' => -4.008,
        ]);

        $communes = [
            ['Cocody', 5.349, -3.987], ['Yopougon', 5.331, -4.068],
            ['Marcory', 5.304, -3.995], ['Plateau', 5.323, -4.018],
            ['Treichville', 5.298, -4.010], ['Adjamé', 5.364, -4.024],
            ['Koumassi', 5.292, -3.937], ['Abobo', 5.416, -4.016],
            ['Port-Bouët', 5.261, -3.955], ['Attécoubé', 5.326, -4.051],
            ['Bingerville', 5.351, -3.885], ['Anyama', 5.495, -4.055],
            ['Songon', 5.301, -4.267],
        ];

        foreach ($communes as [$name, $lat, $lng]) {
            City::create([
                'name' => $name,
                'slug' => \Illuminate\Support\Str::slug($name),
                'parent_id' => $abidjan->id,
                'region' => 'Abidjan',
                'latitude' => $lat, 'longitude' => $lng,
            ]);
        }

        $cities = [
            ['Bouaké', 'Vallée du Bandama', 7.693, -5.031],
            ['San-Pédro', 'Bas-Sassandra', 4.748, -6.636],
            ['Yamoussoukro', 'District autonome', 6.827, -5.289],
            ['Daloa', 'Haut-Sassandra', 6.877, -6.450],
            ['Korhogo', 'Poro', 9.458, -5.629],
            ['Man', 'Montagnes', 7.412, -7.554],
            ['Gagnoa', 'Gôh', 6.132, -5.951],
            ['Abengourou', 'Indénié-Djuablin', 6.729, -3.496],
            ['Divo', 'Lôh-Djiboua', 5.837, -5.358],
            ['Séguéla', 'Woroba', 7.961, -6.673],
            ['Odienné', 'Kabadougou', 9.507, -7.564],
            ['Toumodi', 'Bélier', 6.551, -5.017],
            ['Soubré', 'Nawa', 5.784, -6.594],
            ['Bondoukou', 'Gontougo', 8.037, -2.798],
            ['Agnibilékrou', 'Indénié-Djuablin', 7.132, -3.208],
            ['Grand-Bassam', 'Sud-Comoé', 5.212, -3.742],
            ['Assinie', 'Sud-Comoé', 5.138, -3.288],
        ];

        foreach ($cities as [$name, $region, $lat, $lng]) {
            City::create([
                'name' => $name,
                'slug' => \Illuminate\Support\Str::slug($name),
                'region' => $region,
                'latitude' => $lat, 'longitude' => $lng,
            ]);
        }
    }
}