<?php

namespace Database\Seeders;

use App\Models\ContactType;
use Illuminate\Database\Seeder;

class ContactTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $types = [
            'Builders and Developers',
            'Hospitals',
            'MEP Consultants',
            'Architects',
            'Project',
            'Plumbing Contractors',
            'Electrical Contractors',
            'HVAC Contractors',
            'Petrol Pump Contractors',
            'Civil Eng. Contractors',
            'Fire Fighting Contractors',
            'Interior Designers',
            'Swimming pool & STP',
            'Biomedicals',
            'Shop & Retail',
        ];

        foreach ($types as $type) {
            ContactType::updateOrCreate(
                ['name' => $type],
                ['is_active' => true]
            );
        }
    }
}
