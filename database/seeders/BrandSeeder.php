<?php

namespace Database\Seeders;

use App\Models\Brand;
use Illuminate\Database\Seeder;

class BrandSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $brands = [
            'Astral',
            'Supreme',
            'Finolex',
            'Ashirvad',
            'Prince',
        ];

        foreach ($brands as $brand) {
            Brand::updateOrCreate(
                ['name' => $brand],
                ['is_active' => true]
            );
        }
    }
}
