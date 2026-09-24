<?php

use App\Models\Branch;
use App\Models\Brand;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('guests cannot view branches', function () {
    $this->get(route('branches.index'))->assertRedirect(route('login'));
});

test('users without permission cannot view branches', function () {
    $user = User::factory()->create();
    $user->assignRole('Telecaller');

    $this->actingAs($user)->get(route('branches.index'))->assertForbidden();
});

test('admins can view the branches index', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    Branch::factory()->create(['name' => 'Head Office']);

    $this->actingAs($admin)
        ->get(route('branches.index'))
        ->assertSuccessful();
});

test('admins can create a branch', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $this->actingAs($admin)
        ->post(route('branches.store'), [
            'name' => 'Downtown',
            'code' => 'DT-01',
            'city' => 'Metropolis',
            'is_active' => true,
        ])
        ->assertRedirect(route('branches.index'));

    $this->assertDatabaseHas('branches', [
        'name' => 'Downtown',
        'code' => 'DT-01',
        'is_active' => true,
    ]);
});

test('branch codes must be unique', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    Branch::factory()->create(['code' => 'DT-01']);

    $this->actingAs($admin)
        ->post(route('branches.store'), [
            'name' => 'Another Branch',
            'code' => 'DT-01',
        ])
        ->assertInvalid(['code']);
});

test('admins can update a branch', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $branch = Branch::factory()->create(['is_active' => true]);

    $this->actingAs($admin)
        ->patch(route('branches.update', $branch), [
            'name' => 'Renamed Branch',
            'code' => $branch->code,
            'is_active' => false,
        ])
        ->assertRedirect(route('branches.index'));

    expect($branch->refresh())
        ->name->toBe('Renamed Branch')
        ->is_active->toBeFalse();
});

test('admins can delete a branch with no users', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $branch = Branch::factory()->create();

    $this->actingAs($admin)
        ->delete(route('branches.destroy', $branch))
        ->assertRedirect(route('branches.index'));

    $this->assertModelMissing($branch);
});

test('a branch cannot be deleted while it still has users', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $branch = Branch::factory()->create();
    User::factory()->create(['branch_id' => $branch->id]);

    $this->actingAs($admin)
        ->delete(route('branches.destroy', $branch))
        ->assertRedirect();

    $this->assertModelExists($branch);
});

test('the branch index can be filtered by a search term', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    Branch::factory()->create(['name' => 'Northgate Branch']);
    Branch::factory()->create(['name' => 'Southside Branch']);

    $this->actingAs($admin)
        ->get(route('branches.index', ['search' => 'Northgate']))
        ->assertInertia(fn ($page) => $page
            ->has('branches.data', 1)
            ->where('branches.data.0.name', 'Northgate Branch')
            ->where('filters.search', 'Northgate'));
});

test('a branch keeps its own bank account details', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $this->actingAs($admin)
        ->post(route('branches.store'), [
            'name' => 'Calicut',
            'code' => 'CAL',
            'bank_name' => 'Axis Bank',
            'bank_account_number' => '921020054975428',
            'bank_branch' => 'Kaliai Road,Kozhikode',
            'bank_ifsc' => 'UTIB0001908',
            'is_active' => true,
        ])
        ->assertRedirect(route('branches.index'));

    $branch = Branch::where('code', 'CAL')->sole();

    expect($branch->bank_name)->toBe('Axis Bank');
    expect($branch->bankDetails())->toBe([
        'name' => 'Axis Bank',
        'account' => '921020054975428',
        'branch' => 'Kaliai Road,Kozhikode',
        'ifsc' => 'UTIB0001908',
        'branch_ifsc' => 'Kaliai Road,Kozhikode & UTIB0001908',
    ]);
});

test('a branch without a bank name banks under the company account', function () {
    $branch = Branch::factory()->create(['bank_name' => null]);

    expect($branch->bankDetails())->toBeNull();
});

test('a branch keeps its own quotation letterhead and logo', function () {
    Storage::fake('public');

    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $branch = Branch::factory()->create(['code' => 'CAL']);

    $this->actingAs($admin)
        ->put(route('branches.update', $branch), [
            'name' => $branch->name,
            'code' => 'CAL',
            'address' => "3374, Hauz Qazi\nDelhi - 110006",
            'company_name' => 'PIPELINE PRODUCTS (INDIA)',
            'logo' => UploadedFile::fake()->image('logo.png'),
            'phone' => '+91-11-43211999',
            'email' => 'info@pipelineproductsindia.com',
            'website' => 'www.pipelineproductsindia.com',
            'gstin' => '07AALFP0328D1ZJ',
            'quotation_terms' => "GST 18% Extra\nCartage and Freight Extra",
            'is_active' => true,
        ])
        ->assertRedirect(route('branches.index'));

    $branch->refresh();

    Storage::disk('public')->assertExists($branch->logo_path);
    expect($branch->letterhead())->toMatchArray([
        'name' => 'PIPELINE PRODUCTS (INDIA)',
        'address' => ['3374, Hauz Qazi', 'Delhi - 110006', $branch->city],
        'phone' => '+91-11-43211999',
        'email' => 'info@pipelineproductsindia.com',
        'website' => 'www.pipelineproductsindia.com',
        'gstin' => '07AALFP0328D1ZJ',
    ]);
    expect($branch->letterhead()['mobile'])->toBeNull();
    expect($branch->letterhead()['bank'])->toBeNull();

    $branch->update(['company_name' => null]);

    expect($branch->letterhead()['name'])->toBe($branch->name);
});

test('removing a branch logo deletes the stored file', function () {
    Storage::fake('public');

    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $path = UploadedFile::fake()->image('logo.png')->store('branch-logos', 'public');
    $branch = Branch::factory()->create(['code' => 'CAL', 'logo_path' => $path]);

    $this->actingAs($admin)
        ->put(route('branches.update', $branch), [
            'name' => $branch->name,
            'code' => 'CAL',
            'remove_logo' => true,
        ])
        ->assertRedirect(route('branches.index'));

    Storage::disk('public')->assertMissing($path);
    expect($branch->fresh()->logo_path)->toBeNull();
});

test('a branch keeps the brands it deals in', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    [$astral, $supreme, $finolex] = Brand::factory()->count(3)->create();
    $branch = Branch::factory()->create(['code' => 'CAL']);
    $branch->brands()->attach($finolex);

    $this->actingAs($admin)
        ->get(route('branches.edit', $branch))
        ->assertInertia(fn ($page) => $page
            ->has('brands', 3)
            ->where('branch.brand_ids', [$finolex->id]));

    $this->actingAs($admin)
        ->put(route('branches.update', $branch), [
            'name' => $branch->name,
            'code' => 'CAL',
            'brands' => [$astral->id, $supreme->id],
        ])
        ->assertRedirect(route('branches.index'));

    expect($branch->brands()->pluck('brands.id')->sort()->values()->all())
        ->toBe([$astral->id, $supreme->id]);
});
