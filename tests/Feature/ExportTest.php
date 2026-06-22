<?php

use App\Models\Builder;
use App\Models\Contact;
use App\Models\Product;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\User;
use App\Models\VisitReport;
use Database\Seeders\RolePermissionSeeder;
use Maatwebsite\Excel\Facades\Excel;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('list pages can be exported to excel', function (string $routeName, string $filename, string $factory) {
    Excel::fake();

    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $factory::factory()->count(2)->create();

    $this->actingAs($admin)->get(route($routeName))->assertOk();

    Excel::assertDownloaded($filename);
})->with([
    'projects' => ['projects.export', 'projects.xlsx', Project::class],
    'builders' => ['builders.export', 'builders.xlsx', Builder::class],
    'products' => ['products.export', 'products.xlsx', Product::class],
    'contacts' => ['contacts.export', 'contacts.xlsx', Contact::class],
    'visit-reports' => ['visit-reports.export', 'visit-reports.xlsx', VisitReport::class],
    'quotations' => ['quotations.export', 'quotations.xlsx', Quotation::class],
]);

test('users without permission cannot export', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('projects.export'))->assertForbidden();
});
