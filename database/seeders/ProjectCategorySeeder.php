<?php

namespace Database\Seeders;

use App\Models\ProjectCategory;
use Illuminate\Database\Seeder;

class ProjectCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            'Apartment',
            'Residential',
            'Villas',
            'Commercial',
            'Industrial',
            'Infrastructure',
            'Hospital',
            'Hotel',
            'Educational',
            'Retail',
            'Office',
            'Mixed Use',
            'Other',
        ];

        foreach ($categories as $category) {
            ProjectCategory::updateOrCreate(
                ['name' => $category],
                ['is_active' => true]
            );
        }
    }
}
