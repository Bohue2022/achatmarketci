<?php

namespace Database\Seeders;

use App\Models\Brand;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BrandSeeder extends Seeder
{
    public function run(): void
    {
        $brands = [
            'Toyota' => ['Corolla', 'RAV4', 'Land Cruiser', 'Hilux', 'Yaris', 'Camry', 'Prado', 'Prado TX', 'Fortuner', 'Prado VX'],
            'Mercedes-Benz' => ['Classe C', 'Classe E', 'GLE', 'GLC', 'GLS', 'Vito', 'Sprinter', 'Classe S', 'G-Class', 'Classe A'],
            'BMW' => ['Série 3', 'Série 5', 'X3', 'X5', 'X6', 'Série 1', 'Série 7', 'X1', 'i8'],
            'Audi' => ['A3', 'A4', 'A6', 'Q5', 'Q7', 'Q3', 'A5', 'A7'],
            'Peugeot' => ['208', '308', '3008', '5008', '2008', 'Rifter', 'Partner', '508', 'Expert'],
            'Renault' => ['Clio', 'Captur', 'Duster', 'Kadjar', 'Trafic', 'Master', 'Megane', 'Kwid'],
            'Volkswagen' => ['Golf', 'Passat', 'Polo', 'Touareg', 'Tiguan', 'Amarok', 'Multivan', 'Jetta'],
            'Hyundai' => ['Tucson', 'Santa Fe', 'Elantra', 'i10', 'i20', 'Creta', 'Accent', 'Palisade'],
            'Kia' => ['Sportage', 'Sorento', 'Rio', 'Picanto', 'Carnival', 'Seltos', 'K5'],
            'Nissan' => ['X-Trail', 'Qashqai', 'Patrol', 'Navara', 'Sunny', 'Micra', 'Pathfinder', 'Almera'],
            'Honda' => ['Civic', 'Accord', 'CR-V', 'HR-V', 'Fit', 'Pilot', 'Odyssey'],
            'Mitsubishi' => ['Pajero', 'L200', 'Outlander', 'ASX', 'Lancer', 'Montero Sport'],
            'Ford' => ['Ranger', 'Everest', 'Focus', 'Fiesta', 'Kuga', 'Edge', 'Escape'],
            'Chevrolet' => ['Aveo', 'Cruze', 'Camaro', 'Silverado', 'Trailblazer', 'Equinox'],
            'Range Rover' => ['Evoque', 'Sport', 'Vogue', 'Velar', 'Discovery'],
            'Suzuki' => ['Swift', 'Vitara', 'Jimny', 'S-Cross', 'Baleno', 'Ertiga'],
            'Mazda' => ['Mazda 3', 'Mazda 6', 'CX-5', 'CX-3', 'CX-9', 'BT-50'],
            'Lexus' => ['RX', 'NX', 'LX', 'GX', 'ES', 'IS'],
            'Infiniti' => ['QX50', 'QX60', 'QX80', 'Q50'],
            'Jeep' => ['Grand Cherokee', 'Wrangler', 'Cherokee', 'Compass', 'Renegade'],
            'Dacia' => ['Sandero', 'Duster', 'Logan', 'Lodgy', 'Dokker'],
            'BYD' => ['Seal', 'Atto 3', 'Han', 'Tang', 'Dolphin'],
            'Koenigsegg' => [],
            'Volvo' => ['XC60', 'XC90', 'S60', 'V60', 'XC40'],
        ];

        foreach ($brands as $name => $models) {
            $brand = Brand::create(['name' => $name, 'slug' => Str::slug($name)]);

            foreach ($models as $modelName) {
                $brand->models()->create([
                    'name' => $modelName,
                    'slug' => Str::slug($modelName),
                ]);
            }
        }
    }
}