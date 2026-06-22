<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            ContactTypeSeeder::class,
            ProjectCategorySeeder::class,
            BrandSeeder::class,
            CountryStateDistrictSeeder::class,
        ]);

        $branch = Branch::create([
            'name' => 'Head Office',
            'code' => 'HO',
            'is_active' => true,
        ]);

        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'branch_id' => $branch->id,
        ]);

        $user->assignRole('Super Admin');
        $user->branches()->sync([$branch->id]);

        $superAdmin = User::create([
            'name' => 'Rashid Super Admin',
            'email' => 'rashi818@gmail.com',
            'password' => bcrypt('rashi818@#'),
            'branch_id' => $branch->id,
        ]);

        $superAdmin->assignRole('Super Admin');
        $superAdmin->branches()->sync([$branch->id]);

        $this->call(GokulamAndThulaProjectSeeder::class);
    }
}
