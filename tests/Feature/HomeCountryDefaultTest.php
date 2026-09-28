<?php

use App\Models\Branch;
use App\Models\Builder;
use App\Models\Contact;
use App\Models\Country;
use App\Models\Customer;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->nepal = Country::factory()->create(['name' => 'Nepal', 'code' => 'NP']);
    $this->india = Country::factory()->create(['name' => 'India', 'code' => 'IN']);
});

test('records saved without a country default to india', function (string $model) {
    $record = $model::factory()->create(['country_id' => null]);

    expect($record->country_id)->toBe($this->india->id);
})->with([Customer::class, Contact::class, Builder::class, Project::class]);

test('a country chosen explicitly is kept', function () {
    $contact = Contact::factory()->create(['country_id' => $this->nepal->id]);

    expect($contact->country_id)->toBe($this->nepal->id);
});

test('a project created from the form, which hides the country, is placed in india', function () {
    $this->seed(RolePermissionSeeder::class);

    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');

    $this->actingAs($manager)
        ->post(route('projects.store'), [
            'project_category_id' => ProjectCategory::factory()->create()->id,
            'name' => 'Riverside Towers',
            'status' => 'planning',
        ])
        ->assertRedirect(route('projects.index'));

    expect(Project::where('name', 'Riverside Towers')->sole()->country_id)->toBe($this->india->id);
});
