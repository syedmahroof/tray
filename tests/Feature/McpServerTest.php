<?php

use App\Models\Branch;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\Reminder;
use App\Models\Supplier;
use App\Models\User;
use App\Support\McpListings;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

function callTrayTool(string $name, array $arguments = []): TestResponse
{
    return test()->postJson('/api/mcp', [
        'jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call',
        'params' => ['name' => $name, 'arguments' => (object) $arguments],
    ]);
}

function trayToolData(TestResponse $response): array
{
    $response->assertOk()->assertJsonPath('result.isError', false);

    return json_decode($response->json('result.content.0.text'), true, flags: JSON_THROW_ON_ERROR);
}

function trayManager(): User
{
    $user = User::factory()->create(['branch_id' => Branch::factory()->create()->id]);
    $user->assignRole('Manager');
    Sanctum::actingAs($user);

    return $user;
}

test('MCP requires a bearer token and supports protocol initialization', function () {
    $this->postJson('/api/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize'])->assertUnauthorized();
    $user = User::factory()->create();
    $this->withToken($user->createToken('mcp')->plainTextToken)
        ->postJson('/api/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => ['protocolVersion' => '2025-03-26', 'capabilities' => (object) [], 'clientInfo' => ['name' => 'tests', 'version' => '1.0']]])
        ->assertOk()->assertJsonPath('result.serverInfo.name', 'Tray CRM');
});

test('MCP publishes discoverable schemas for all listing and creation tools', function () {
    trayManager();
    $response = $this->postJson('/api/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list', 'params' => ['per_page' => 50]])->assertOk();
    $tools = collect($response->json('result.tools'));
    expect($tools)->toHaveCount(27);
    expect($tools->pluck('name')->all())->toContain('list_quotations', 'list_contacts', 'list_visit_reports', 'create_quotation', 'create_sales_order', 'create_purchase_order');
    expect($tools->firstWhere('name', 'create_purchase_order')['inputSchema']['required'])->toContain('supplier_id', 'items');
});

test('every listing executes with a permitted user', function (string $module) {
    $user = trayManager();
    $user->givePermissionTo($module.'.view');
    $result = trayToolData(callTrayTool('list_'.str_replace('-', '_', $module), ['search' => 'test']));
    expect($result)->toHaveKeys(['data', 'current_page', 'last_page', 'per_page', 'total']);
})->with(array_keys(McpListings::definitions()));

test('every listing denies users without its permission', function (string $module) {
    Sanctum::actingAs(User::factory()->create());
    callTrayTool('list_'.str_replace('-', '_', $module))->assertOk()->assertJsonPath('result.isError', true);
})->with(array_keys(McpListings::definitions()));

test('contact listings search paginate and isolate branches', function () {
    $manager = trayManager();
    Contact::factory()->count(3)->create(['branch_id' => $manager->branch_id, 'name' => 'Matching contact']);
    $other = Contact::factory()->create(['name' => 'Matching hidden']);
    $data = trayToolData(callTrayTool('list_contacts', ['search' => 'Matching', 'per_page' => 2, 'page' => 2]));
    expect($data['total'])->toBe(3)->and($data['data'])->toHaveCount(1)->and($data['current_page'])->toBe(2);
    $hidden = trayToolData(callTrayTool('list_contacts', ['id' => $other->id]));
    expect($hidden['total'])->toBe(0);
    callTrayTool('list_contacts', ['per_page' => 101])->assertJsonPath('result.isError', true);
});

test('creation tools validate and enforce create permissions', function (string $tool) {
    Sanctum::actingAs(User::factory()->create());
    callTrayTool($tool)->assertJsonPath('result.isError', true);
    trayManager();
    callTrayTool($tool)->assertJsonPath('result.isError', true);
    expect(Order::count())->toBe(0)->and(Quotation::count())->toBe(0);
})->with(['create_quotation', 'create_sales_order', 'create_purchase_order']);

test('documents are created with defaults calculated GST and authenticated ownership', function (string $tool, string $type) {
    $manager = trayManager();
    $customer = Customer::factory()->create(['branch_id' => $manager->branch_id]);
    $supplier = Supplier::factory()->create(['is_active' => true]);
    $data = trayToolData(callTrayTool($tool, [
        ($type === 'purchase' ? 'supplier_id' : 'customer_id') => $type === 'purchase' ? $supplier->id : $customer->id,
        'created_by' => 99999, 'number' => 'SPOOF', 'total' => 1,
        'discount' => 100, 'supply_type' => 'inter',
        'items' => [['description' => 'Pipe', 'quantity' => 2, 'unit_price' => 500, 'tax_percentage' => 18]],
    ]));
    $document = $data['document'];
    expect($document['status'])->toBe('draft')->and($document['created_by'])->toBe($manager->id)
        ->and($document['branch_id'])->toBe($manager->branch_id)->and((float) $document['total'])->toBe(1062.0)
        ->and((float) $document['igst_amount'])->toBe(162.0)->and($document['items'])->toHaveCount(1)
        ->and($document['number'])->not->toBe('SPOOF');
    if ($type !== 'quotation') {
        expect($document['type'])->toBe($type);
        $opposite = trayToolData(callTrayTool($type === 'sale' ? 'list_purchase_orders' : 'list_sales_orders'));
        expect($opposite['total'])->toBe(0);
    }
})->with([['create_quotation', 'quotation'], ['create_sales_order', 'sale'], ['create_purchase_order', 'purchase']]);

test('documents reject inaccessible branches and relationships without writing', function (string $tool) {
    $manager = trayManager();
    $otherCustomer = Customer::factory()->create();
    $arguments = ['customer_id' => $otherCustomer->id, 'items' => [['description' => 'Pipe', 'quantity' => 1, 'unit_price' => 100]]];
    callTrayTool($tool, $arguments)->assertJsonPath('result.isError', true);
    $arguments['branch_id'] = $otherCustomer->branch_id;
    callTrayTool($tool, $arguments)->assertJsonPath('result.isError', true);
    expect(Order::count())->toBe(0)->and(Quotation::count())->toBe(0);
})->with(['create_quotation', 'create_sales_order']);

test('documents reject products outside accessible brands and inactive products', function () {
    $manager = trayManager();
    $customer = Customer::factory()->create(['branch_id' => $manager->branch_id]);
    $hidden = Product::factory()->create();
    $inactive = Product::factory()->create(['created_by' => $manager->id, 'is_active' => false]);
    foreach ([$hidden, $inactive] as $product) {
        callTrayTool('create_quotation', ['customer_id' => $customer->id, 'items' => [['product_id' => $product->id, 'description' => 'Pipe', 'quantity' => 1, 'unit_price' => 100]]])
            ->assertJsonPath('result.isError', true);
    }
    expect(Quotation::count())->toBe(0);
});

test('user listings never expose authentication fields', function () {
    $manager = trayManager();
    $manager->givePermissionTo('users.view');
    $data = trayToolData(callTrayTool('list_users'));
    foreach ($data['data'] as $user) {
        expect(array_keys($user))->toBe(['id', 'name', 'email', 'branch_id']);
    }
});

test('product listings enforce brand access and price permissions', function () {
    $user = User::factory()->create(['branch_id' => Branch::factory()->create()->id]);
    $user->givePermissionTo('products.view');
    Sanctum::actingAs($user);
    $visible = Product::factory()->create(['created_by' => $user->id]);
    Product::factory()->create();
    $data = trayToolData(callTrayTool('list_products'));
    expect($data['total'])->toBe(1)->and($data['data'][0]['id'])->toBe($visible->id)
        ->and($data['data'][0])->not->toHaveKeys(['price', 'taxable_amount']);
    $user->givePermissionTo('products.price.view');
    $data = trayToolData(callTrayTool('list_products'));
    expect($data['data'][0])->toHaveKeys(['price', 'taxable_amount']);
});

test('reminders and branches are restricted to the signed in user', function () {
    $manager = trayManager();
    $manager->givePermissionTo('branches.view');
    Reminder::factory()->create(['user_id' => $manager->id]);
    Reminder::factory()->create();
    Branch::factory()->create();
    expect(trayToolData(callTrayTool('list_reminders'))['total'])->toBe(1);
    $branches = trayToolData(callTrayTool('list_branches'));
    expect($branches['total'])->toBe(1)->and($branches['data'][0]['id'])->toBe($manager->branch_id);
});

test('purchase orders reject inactive suppliers', function () {
    trayManager();
    $supplier = Supplier::factory()->create(['is_active' => false]);
    callTrayTool('create_purchase_order', ['supplier_id' => $supplier->id, 'items' => [['description' => 'Pipe', 'quantity' => 1, 'unit_price' => 100]]])
        ->assertJsonPath('result.isError', true);
    expect(Order::count())->toBe(0);
});

test('quotation creation applies commission freight and branch numbering', function () {
    $manager = trayManager();
    $manager->branch->update(['quotation_prefix' => 'MCP', 'quotation_next_number' => 75]);
    $contact = Contact::factory()->create(['branch_id' => $manager->branch_id]);
    $data = trayToolData(callTrayTool('create_quotation', [
        'contact_id' => $contact->id, 'agent_commission' => 100, 'freight' => 50, 'freight_tax_percent' => 18,
        'items' => [['description' => 'Pipe', 'quantity' => 1, 'unit_price' => 1000, 'tax_percentage' => 18]],
    ]));
    expect((float) $data['document']['total'])->toBe(1357.0)
        ->and((float) $data['document']['cgst_amount'])->toBe(103.5)
        ->and((float) $data['document']['sgst_amount'])->toBe(103.5)
        ->and($data['document']['number'])->toContain('MCP');
});
