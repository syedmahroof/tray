<?php

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Support\OrderDocument;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->branch = Branch::factory()->create();
    $this->manager = User::factory()->create(['branch_id' => $this->branch->id]);
    $this->manager->assignRole('Manager');
});

/**
 * Raise an order of the given kind through the web form and return its number.
 */
function raiseOrder(User $user, string $resource = 'purchase-orders'): string
{
    $party = $resource === 'purchase-orders'
        ? ['supplier_id' => Supplier::factory()->create()->id]
        : ['customer_id' => Customer::factory()->create(['branch_id' => $user->branch_id])->id];

    test()->actingAs($user)
        ->post(route("{$resource}.store"), [
            ...$party,
            'order_date' => '2026-11-24',
            'status' => 'draft',
            'supply_type' => 'intra',
            'items' => [['description' => 'GI Clamp', 'quantity' => 1, 'unit_price' => 100, 'tax_percentage' => 18]],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    return Order::query()->withoutGlobalScopes()->latest('id')->value('number');
}

test('a branch with a purchase order series numbers its purchase orders from it', function () {
    $this->branch->update(['purchase_order_prefix' => 'BTCLT/PO/', 'purchase_order_next_number' => 41]);

    expect(raiseOrder($this->manager))->toBe('BTCLT/PO/041')
        ->and(raiseOrder($this->manager))->toBe('BTCLT/PO/042')
        ->and($this->branch->refresh()->purchase_order_next_number)->toBe(43);
});

test('a purchase order series skips numbers already taken', function () {
    $this->branch->update(['purchase_order_prefix' => 'BTCLT/PO/', 'purchase_order_next_number' => 5]);
    Order::factory()->purchase()->create(['branch_id' => $this->branch->id, 'number' => 'BTCLT/PO/005']);

    expect(raiseOrder($this->manager))->toBe('BTCLT/PO/006');
});

test('without a series, and for sales orders, numbering stays company-wide', function () {
    expect(raiseOrder($this->manager))->toStartWith('PO-');

    $this->branch->update(['purchase_order_prefix' => 'BTCLT/PO/']);

    expect(raiseOrder($this->manager, 'sales-orders'))->toStartWith('SO-')
        ->and($this->branch->refresh()->purchase_order_next_number)->toBe(1);
});

test('admins set a branch purchase order series from the branch settings', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $fields = ['name' => $this->branch->name, 'code' => $this->branch->code];

    $this->actingAs($admin)
        ->put(route('branches.update', $this->branch), [...$fields, 'purchase_order_prefix' => 'BTCLT/PO/26-27/', 'purchase_order_next_number' => 20])
        ->assertRedirect(route('branches.index'));

    expect($this->branch->refresh())
        ->purchase_order_prefix->toBe('BTCLT/PO/26-27/')
        ->purchase_order_next_number->toBe(20);

    $this->actingAs($admin)
        ->put(route('branches.update', $this->branch), [...$fields, 'purchase_order_prefix' => 'BTCLT/PO/26-27/', 'purchase_order_next_number' => ''])
        ->assertRedirect(route('branches.index'));

    expect($this->branch->refresh()->purchase_order_next_number)->toBe(20);

    $this->actingAs($admin)
        ->put(route('branches.update', $this->branch), [...$fields, 'purchase_order_next_number' => 0])
        ->assertSessionHasErrors('purchase_order_next_number');
});

test('the order forms send only the products already on the order, and the picker searches a page at a time', function () {
    $mine = ['created_by' => $this->manager->id];
    $used = Product::factory()->create(['name' => 'Zeta Valve', 'code' => 'ZV-1', ...$mine]);
    Product::factory()->count(35)->create($mine);
    Product::factory()->create(['name' => 'Retired Valve', 'is_active' => false, ...$mine]);

    $this->actingAs($this->manager)
        ->get(route('purchase-orders.create'))
        ->assertInertia(fn ($page) => $page->has('products', 0));

    $order = Order::factory()->purchase()->create(['branch_id' => $this->branch->id]);
    OrderItem::factory()->create(['order_id' => $order->id, 'product_id' => $used->id]);

    $this->get(route('purchase-orders.edit', $order))
        ->assertInertia(fn ($page) => $page->has('products', 1)->where('products.0.id', $used->id));

    $first = $this->getJson(route('purchase-orders.products'))->assertOk();
    expect($first->json('data'))->toHaveCount(30)
        ->and($first->json('next_page'))->toBe(2)
        ->and($first->json('data.0'))->toHaveKeys(['id', 'name', 'code', 'brand', 'image_url', 'unit', 'hsn_code', 'price', 'taxable_amount', 'tax_percentage']);

    expect($this->getJson(route('purchase-orders.products', ['page' => 2]))->json('next_page'))->toBeNull();

    $this->getJson(route('purchase-orders.products', ['search' => 'zv-1']))
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Zeta Valve');

    $this->getJson(route('purchase-orders.products', ['search' => 'Retired']))->assertJsonCount(0, 'data');
    $this->getJson(route('sales-orders.products'))->assertOk();
});

test('the product search is only open to users who can raise or edit orders', function () {
    $telecaller = User::factory()->create(['branch_id' => $this->branch->id]);
    $telecaller->assignRole('Telecaller');

    $this->actingAs($telecaller)->getJson(route('purchase-orders.products'))->assertForbidden();
});

test('a purchase order prints in the quotation layout with discounted line prices and a rounded total', function () {
    $supplier = Supplier::factory()->create(['name' => 'Acme Pipes', 'contact_person' => 'Ravi']);
    $order = Order::factory()->purchase()->create([
        'branch_id' => $this->branch->id,
        'supplier_id' => $supplier->id,
        'number' => 'BTCLT/PO/007',
        'supply_type' => 'intra',
        'subtotal' => 1000, 'discount' => 100, 'cgst_amount' => 81.25, 'sgst_amount' => 81.25, 'igst_amount' => 0,
        'total' => 1062.5,
        'terms' => "Payment : 30 days\nDelivery within a week",
    ]);
    OrderItem::factory()->create(['order_id' => $order->id, 'description' => 'GI Pipe', 'quantity' => 2, 'unit_price' => 500, 'tax_percentage' => 18]);

    $data = OrderDocument::for($order->refresh(), 'localhost');
    expect($data['lines'][0])->toMatchArray(['description' => 'GI Pipe', 'net_rate' => 450.0, 'net_amount' => 900.0]);

    $html = view('orders.pdf', $data)->render();
    expect($html)
        ->toContain('PURCHASE ORDER')
        ->toContain('BTCLT/PO/007')
        ->toContain('M/s Acme Pipes')
        ->toContain('Kind Attn:')
        ->toContain('Product Name')
        ->toContain('class="filler"')
        ->toContain('CGST @ 9.03%')
        ->toContain('Round Off')
        ->toContain('Terms and Conditions')
        ->toContain('Authorised Signatory')
        ->not->toContain('Bank Details');

    expect(OrderDocument::render($order, 'localhost')->output())->toStartWith('%PDF');
});
