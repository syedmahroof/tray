<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Builder;
use App\Models\Contact;
use App\Models\ContactType;
use App\Models\District;
use App\Models\Project;
use App\Models\ProjectCategory;
use Illuminate\Database\Seeder;

class GokulamAndThulaProjectSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $branchId = Branch::first()?->id ?? 1;

        $builder = Builder::first();
        if (! $builder) {
            $builder = Builder::create([
                'branch_id' => $branchId,
                'name' => 'Default Builder',
                'is_active' => true,
            ]);
        }
        $builderId = $builder->id;

        $mallCategory = ProjectCategory::firstOrCreate(
            ['name' => 'Shopping Mall'],
            ['is_active' => true]
        );

        $hospitalCategory = ProjectCategory::firstOrCreate(
            ['name' => 'Hospital'],
            ['is_active' => true]
        );

        // Project 1
        $district1 = District::where('name', 'Kozhikode')->first();
        $project1 = Project::create([
            'branch_id' => $branchId,
            'builder_id' => $builderId,
            'project_category_id' => $mallCategory->id,
            'name' => 'Gokulam Mall Calicut',
            'address' => 'oposit baby hospital, arayidath palam, calicut',
            'country_id' => $district1?->state?->country_id ?? 1,
            'state_id' => $district1?->state_id ?? 12,
            'district_id' => $district1?->id ?? 296,
            'status' => 'ongoing',
            'location' => 'calicut',
            'pincode' => '673002',
            'expected_maturity' => '2026-07-01',
            'preferred_material' => 'manhole, plumbing supports, cable tray, valve',
        ]);

        $contactsData1 = [
            ['name' => 'Sree hari', 'role' => 'Purchase 1', 'phone' => '9846756584', 'type' => 'Project'],
            ['name' => 'manoj', 'role' => 'Purchase 2', 'phone' => '7539657645', 'type' => 'Project'],
            ['name' => 'vaishnavi', 'role' => 'Architect (jb architect)', 'phone' => '7687564534', 'type' => 'Architects'],
            ['name' => 'prabhash', 'role' => 'MEP Consultant (epsilon)', 'phone' => '7676445988', 'type' => 'MEP Consultants'],
            ['name' => 'ramesh', 'role' => 'Plumbing contractor (techno plumbing)', 'phone' => '8798657687', 'type' => 'Plumbing Contractors'],
            ['name' => 'sajith', 'role' => 'HVAC Contractor (Kerala refrigerator)', 'phone' => '7645342354', 'type' => 'HVAC Contractors'],
            ['name' => 'irshad', 'role' => 'Electrical contractor (future Engineering)', 'phone' => '7676768787', 'type' => 'Electrical Contractors'],
            ['name' => 'jamal', 'role' => 'Fire fighting contract (able)', 'phone' => '9895675645', 'type' => 'Fire Fighting Contractors'],
            ['name' => 'anfal', 'role' => 'STP work (pure solution)', 'phone' => '9896754322', 'type' => 'Swimming pool & STP'],
        ];

        foreach ($contactsData1 as $data) {
            // Create ProjectContact (inline site contact)
            $project1->projectContacts()->create([
                'name' => $data['name'],
                'role' => $data['role'],
                'phone' => $data['phone'],
            ]);

            // Resolve contact type by name
            $contactType = ContactType::firstOrCreate(
                ['name' => $data['type']],
                ['is_active' => true]
            );

            // Create or update CRM Contact
            $contact = Contact::updateOrCreate(
                ['phone' => $data['phone']],
                [
                    'name' => $data['name'],
                    'branch_id' => $branchId,
                    'contact_type_id' => $contactType->id,
                ]
            );

            // Link CRM Contact to Project
            $project1->contacts()->attach($contact->id);
        }

        // Project 2
        $district2 = District::where('name', 'Malappuram')->first();
        $project2 = Project::create([
            'branch_id' => $branchId,
            'builder_id' => $builderId,
            'project_category_id' => $hospitalCategory->id,
            'name' => 'Thula chelemra',
            'address' => 'ramanatukara,calicut',
            'country_id' => $district2?->state?->country_id ?? 1,
            'state_id' => $district2?->state_id ?? 12,
            'district_id' => $district2?->id ?? 297,
            'status' => 'ongoing',
            'location' => 'ramanatukara',
            'pincode' => '673525',
            'expected_maturity' => '2026-09-25',
            'preferred_material' => 'manhole, plumbing supports, cable tray, valve',
        ]);

        $contactsData2 = [
            ['name' => 'akhil', 'role' => 'Purchase 1', 'phone' => '9846767676', 'type' => 'Project'],
            ['name' => 'shibu', 'role' => 'Purchase 2', 'phone' => '7539658945', 'type' => 'Project'],
            ['name' => 'javad', 'role' => 'Architect (bca architect)', 'phone' => '8086564395', 'type' => 'Architects'],
            ['name' => 'arun', 'role' => 'MEP Consultant (bhavani)', 'phone' => '7676454548', 'type' => 'MEP Consultants'],
            ['name' => 'pratgeesh', 'role' => 'Plumbing contractor (metrotech)', 'phone' => '8798432157', 'type' => 'Plumbing Contractors'],
            ['name' => 'roshan', 'role' => 'HVAC Contractor (earomech)', 'phone' => '7647878354', 'type' => 'HVAC Contractors'],
            ['name' => 'sajeev', 'role' => 'Electrical contractor (dawn star engineering)', 'phone' => '9846213212', 'type' => 'Electrical Contractors'],
            ['name' => 'vishnu', 'role' => 'Fire fighting contract (calicut steel)', 'phone' => '9895678885', 'type' => 'Fire Fighting Contractors'],
            ['name' => 'razak', 'role' => 'STP work (everday)', 'phone' => '8086564534', 'type' => 'Swimming pool & STP'],
        ];

        foreach ($contactsData2 as $data) {
            // Create ProjectContact (inline site contact)
            $project2->projectContacts()->create([
                'name' => $data['name'],
                'role' => $data['role'],
                'phone' => $data['phone'],
            ]);

            // Resolve contact type by name
            $contactType = ContactType::firstOrCreate(
                ['name' => $data['type']],
                ['is_active' => true]
            );

            // Create or update CRM Contact
            $contact = Contact::updateOrCreate(
                ['phone' => $data['phone']],
                [
                    'name' => $data['name'],
                    'branch_id' => $branchId,
                    'contact_type_id' => $contactType->id,
                ]
            );

            // Link CRM Contact to Project
            $project2->contacts()->attach($contact->id);
        }
    }
}
