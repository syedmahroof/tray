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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('users without permission cannot view orders', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('sales-orders.index'))->assertForbidden();
    $this->actingAs($user)->get(route('purchase-orders.index'))->assertForbidden();
});

test('a sales order is created with calculated GST totals and product items', function () {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');
    $customer = Customer::factory()->create(['branch_id' => $branch->id]);
    $product = Product::factory()->create();

    $this->actingAs($manager)
        ->post(route('sales-orders.store'), [
            'customer_id' => $customer->id,
            'order_date' => '2026-10-16',
            'status' => 'confirmed',
            'supply_type' => 'intra',
            'discount' => 100,
            'items' => [
                ['product_id' => $product->id, 'description' => 'Pipe', 'quantity' => 2, 'unit_price' => 500, 'tax_percentage' => 18],
                ['description' => 'Fitting', 'quantity' => 1, 'unit_price' => 200, 'tax_percentage' => 18],
            ],
        ])
        ->assertRedirect();

    $order = Order::query()->sole();

    expect($order->type)->toBe('sale')
        ->and($order->number)->toStartWith('SO-')
        ->and($order->customer_id)->toBe($customer->id)
        ->and($order->created_by)->toBe($manager->id)
        ->and((float) $order->subtotal)->toBe(1200.0)
        ->and((float) $order->tax_amount)->toBe(198.0)
        ->and((float) $order->cgst_amount)->toBe(99.0)
        ->and((float) $order->sgst_amount)->toBe(99.0)
        ->and((float) $order->igst_amount)->toBe(0.0)
        ->and((float) $order->total)->toBe(1298.0)
        ->and($order->items)->toHaveCount(2);
});

test('order lines are saved in the order they were entered, typed-in lines included', function () {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');
    $customer = Customer::factory()->create(['branch_id' => $branch->id]);
    $product = Product::factory()->create();

    $this->actingAs($manager)
        ->post(route('sales-orders.store'), [
            'customer_id' => $customer->id,
            'order_date' => '2026-10-16',
            'status' => 'confirmed',
            'supply_type' => 'intra',
            'items' => [
                ['description' => 'Fitting', 'quantity' => 1, 'unit_price' => 200, 'tax_percentage' => 18],
                ['product_id' => $product->id, 'description' => 'Pipe', 'quantity' => 2, 'unit_price' => 500, 'tax_percentage' => 18],
            ],
        ])
        ->assertRedirect();

    expect(Order::query()->sole()->items()->orderBy('id')->pluck('description')->all())
        ->toBe(['Fitting', 'Pipe']);
});

test('a purchase order requires a supplier and applies inter-state IGST', function () {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');
    $supplier = Supplier::factory()->create(['name' => 'Acme Supplies']);

    $this->actingAs($manager)
        ->post(route('purchase-orders.store'), [
            'order_date' => '2026-10-16',
            'status' => 'draft',
            'supply_type' => 'inter',
            'items' => [['description' => 'Valve', 'quantity' => 1, 'unit_price' => 1000, 'tax_percentage' => 18]],
        ])
        ->assertSessionHasErrors('supplier_id');

    $this->actingAs($manager)
        ->post(route('purchase-orders.store'), [
            'supplier_id' => $supplier->id,
            'gstin' => '29ABCDE1234F1Z5',
            'order_date' => '2026-10-16',
            'status' => 'draft',
            'supply_type' => 'inter',
            'items' => [['description' => 'Valve', 'quantity' => 1, 'unit_price' => 1000, 'tax_percentage' => 18]],
        ])
        ->assertRedirect();

    $order = Order::query()->sole();

    expect($order->type)->toBe('purchase')
        ->and($order->number)->toStartWith('PO-')
        ->and($order->supplier_id)->toBe($supplier->id)
        ->and($order->gstin)->toBe('29ABCDE1234F1Z5')
        ->and((float) $order->igst_amount)->toBe(180.0)
        ->and((float) $order->cgst_amount)->toBe(0.0)
        ->and((float) $order->total)->toBe(1180.0);
});

test('a sales order customer must belong to the active branch', function () {
    $firstBranch = Branch::factory()->create();
    $secondBranch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $firstBranch->id]);
    $manager->assignRole('Manager');
    $customer = Customer::factory()->create(['branch_id' => $secondBranch->id]);

    $this->actingAs($manager)
        ->post(route('sales-orders.store'), [
            'customer_id' => $customer->id,
            'order_date' => '2026-10-16',
            'status' => 'draft',
            'supply_type' => 'intra',
            'items' => [['description' => 'Pipe', 'quantity' => 1, 'unit_price' => 100]],
        ])
        ->assertSessionHasErrors('customer_id');
});

test('an order cannot be accessed through the other order type', function () {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');
    $order = Order::factory()->purchase()->create(['branch_id' => $branch->id]);

    $this->actingAs($manager)
        ->get(route('sales-orders.show', $order))
        ->assertNotFound();
});

test('the order index lists only orders of the requested type', function () {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');
    Order::factory()->create(['branch_id' => $branch->id, 'status' => 'confirmed']);
    Order::factory()->purchase()->create(['branch_id' => $branch->id]);

    $this->actingAs($manager)
        ->get(route('sales-orders.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('orders/Index')
            ->where('orderType', 'sale')
            ->has('orders.data', 1)
            ->where('stats.total', 1)
            ->where('stats.confirmed', 1));
});

test('updating an order replaces its items and recalculates totals', function () {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');
    $order = Order::factory()->purchase()->create(['branch_id' => $branch->id]);
    $newSupplier = Supplier::factory()->create();
    OrderItem::factory()->count(2)->create(['order_id' => $order->id]);

    $this->actingAs($manager)
        ->put(route('purchase-orders.update', $order), [
            'supplier_id' => $newSupplier->id,
            'order_date' => '2026-10-16',
            'status' => 'send',
            'supply_type' => 'intra',
            'branch_id' => $branch->id,
            'items' => [['description' => 'Valve', 'quantity' => 2, 'unit_price' => 250, 'tax_percentage' => 12]],
        ])
        ->assertRedirect(route('purchase-orders.show', $order));

    $order->refresh();

    expect($order->supplier_id)->toBe($newSupplier->id)
        ->and($order->status)->toBe('send')
        ->and($order->items)->toHaveCount(1)
        ->and((float) $order->total)->toBe(560.0);
});

test('an order cannot be moved to a branch the user cannot access', function () {
    $branch = Branch::factory()->create();
    $otherBranch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');
    $order = Order::factory()->purchase()->create(['branch_id' => $branch->id]);

    $this->actingAs($manager)
        ->put(route('purchase-orders.update', $order), [
            'supplier_id' => $order->supplier_id,
            'order_date' => '2026-10-16',
            'status' => 'draft',
            'supply_type' => 'intra',
            'branch_id' => $otherBranch->id,
            'items' => [['description' => 'Valve', 'quantity' => 1, 'unit_price' => 100]],
        ])
        ->assertSessionHasErrors('branch_id');

    expect($order->refresh()->branch_id)->toBe($branch->id);
});

test('an order status can be changed and the order deleted', function () {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');
    $order = Order::factory()->create(['branch_id' => $branch->id]);

    $this->actingAs($manager)
        ->patch(route('sales-orders.status', $order), ['status' => 'fulfilled'])
        ->assertRedirect();

    expect($order->refresh()->status)->toBe('fulfilled');

    $this->actingAs($manager)
        ->delete(route('sales-orders.destroy', $order))
        ->assertRedirect(route('sales-orders.index'));

    expect(Order::query()->count())->toBe(0);
});

test('a sales executive manages sales orders but not purchase orders', function () {
    $branch = Branch::factory()->create();
    $executive = User::factory()->create(['branch_id' => $branch->id]);
    $executive->assignRole('Sales Executive');
    $order = Order::factory()->create(['branch_id' => $branch->id]);

    $this->actingAs($executive)->get(route('sales-orders.show', $order))->assertOk();
    $this->actingAs($executive)->patch(route('sales-orders.status', $order), ['status' => 'confirmed'])->assertRedirect();
    $this->actingAs($executive)->get(route('purchase-orders.index'))->assertForbidden();
    $this->actingAs($executive)->get(route('purchase-orders.create'))->assertForbidden();
});

test('a sales order ignores a supplier and a purchase order ignores a customer', function () {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');
    $customer = Customer::factory()->create(['branch_id' => $branch->id]);
    $supplier = Supplier::factory()->create();
    $items = [['description' => 'Pipe', 'quantity' => 1, 'unit_price' => 100]];
    $shared = ['order_date' => '2026-10-16', 'status' => 'draft', 'supply_type' => 'intra', 'items' => $items];

    $this->actingAs($manager)->post(route('sales-orders.store'), [...$shared, 'customer_id' => $customer->id, 'supplier_id' => $supplier->id]);
    $this->actingAs($manager)->post(route('purchase-orders.store'), [...$shared, 'customer_id' => $customer->id, 'supplier_id' => $supplier->id]);

    $sale = Order::query()->where('type', 'sale')->sole();
    $purchase = Order::query()->where('type', 'purchase')->sole();

    expect($sale->supplier_id)->toBeNull()
        ->and($sale->customer_id)->toBe($customer->id)
        ->and($purchase->customer_id)->toBeNull()
        ->and($purchase->supplier_id)->toBe($supplier->id);
});

test('the create form prefills the customer passed in the query', function () {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');
    $customer = Customer::factory()->create(['branch_id' => $branch->id]);

    $this->actingAs($manager)
        ->get(route('sales-orders.create', ['customer_id' => $customer->id]))
        ->assertInertia(fn ($page) => $page->where('prefill.customer_id', $customer->id));
});

test('the purchase order form offers active suppliers and keeps an inactive one already on the order', function () {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');
    $active = Supplier::factory()->create();
    $inactive = Supplier::factory()->inactive()->create();
    $otherInactive = Supplier::factory()->inactive()->create();
    $order = Order::factory()->purchase()->create(['branch_id' => $branch->id, 'supplier_id' => $inactive->id]);

    $this->actingAs($manager)
        ->get(route('purchase-orders.create'))
        ->assertInertia(fn ($page) => $page->has('suppliers', 1)->where('suppliers.0.id', $active->id));

    $this->actingAs($manager)
        ->get(route('purchase-orders.edit', $order))
        ->assertInertia(fn ($page) => $page
            ->has('suppliers', 2)
            ->where('suppliers', fn ($suppliers) => ! collect($suppliers)->pluck('id')->contains($otherInactive->id)));
});

test('an order can be downloaded as a PDF and streamed for printing', function (string $type) {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');
    $factory = $type === 'purchase-orders' ? Order::factory()->purchase() : Order::factory();
    $order = $factory->create(['branch_id' => $branch->id, 'total' => 1180.5]);
    OrderItem::factory()->create(['order_id' => $order->id]);

    $download = $this->actingAs($manager)->get(route("{$type}.pdf", $order));
    $download->assertOk();
    expect($download->headers->get('content-type'))->toContain('application/pdf')
        ->and($download->headers->get('content-disposition'))->toContain('attachment');

    $print = $this->actingAs($manager)->get(route("{$type}.print", $order));
    $print->assertOk();
    expect($print->headers->get('content-disposition'))->toContain('inline');
})->with(['sales-orders', 'purchase-orders']);

test('an order PDF cannot be fetched through the other order type', function () {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');
    $order = Order::factory()->purchase()->create(['branch_id' => $branch->id]);

    $this->actingAs($manager)->get(route('sales-orders.pdf', $order))->assertNotFound();
});

test('the amount in words spells out rupees and paise', function () {
    expect(OrderDocument::amountInWords(1298.0))->toBe('Rupees One Thousand Two Hundred Ninety Eight Only')
        ->and(OrderDocument::amountInWords(10.5))->toBe('Rupees Ten and Fifty Paise Only');
});

test('a customer page lists their sales orders for users who may view them', function () {
    $branch = Branch::factory()->create();
    $customer = Customer::factory()->create(['branch_id' => $branch->id]);
    Order::factory()->count(2)->create(['branch_id' => $branch->id, 'customer_id' => $customer->id]);
    Order::factory()->create(['branch_id' => $branch->id]);

    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');
    $this->actingAs($manager)
        ->get(route('customers.show', $customer))
        ->assertInertia(fn ($page) => $page->has('salesOrders', 2));

    $telecaller = User::factory()->create(['branch_id' => $branch->id]);
    $telecaller->assignRole('Telecaller');
    $this->actingAs($telecaller)
        ->get(route('customers.show', $customer))
        ->assertInertia(fn ($page) => $page->has('salesOrders', 0));
});

test('global search finds orders by number and party, only for viewable types', function () {
    $branch = Branch::factory()->create();
    $customer = Customer::factory()->create(['branch_id' => $branch->id, 'name' => 'Zenith Traders']);
    $supplier = Supplier::factory()->create(['name' => 'Zenith Steel']);
    Order::factory()->create(['branch_id' => $branch->id, 'customer_id' => $customer->id]);
    Order::factory()->purchase()->create(['branch_id' => $branch->id, 'supplier_id' => $supplier->id]);

    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');
    $this->actingAs($manager)
        ->getJson(route('global-search', ['q' => 'Zenith']))
        ->assertJsonCount(2, 'orders');

    $executive = User::factory()->create(['branch_id' => $branch->id]);
    $executive->assignRole('Sales Executive');
    $this->actingAs($executive)
        ->getJson(route('global-search', ['q' => 'Zenith']))
        ->assertJsonCount(1, 'orders')
        ->assertJsonPath('orders.0.type', 'sale')
        ->assertJsonPath('orders.0.party_name', 'Zenith Traders');
});

test('order analytics report value and leave out cancelled orders', function () {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');
    Order::factory()->create(['branch_id' => $branch->id, 'order_date' => now()->toDateString(), 'status' => 'fulfilled', 'total' => 1000]);
    Order::factory()->create(['branch_id' => $branch->id, 'order_date' => now()->toDateString(), 'status' => 'cancelled', 'total' => 500]);
    Order::factory()->purchase()->create(['branch_id' => $branch->id, 'order_date' => now()->toDateString(), 'total' => 700]);

    $this->actingAs($manager)
        ->get(route('analytics.show', ['section' => 'sales-orders', 'period' => 'this_month']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('report.stats.0.value', 2)
            ->where('report.stats.1.value', 1000)
            ->where('report.stats.2.value', 1000));

    $this->actingAs($manager)
        ->get(route('analytics.show', ['section' => 'purchase-orders', 'period' => 'this_month']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('report.stats.1.value', 700));
});

test('purchase order statuses are available on every form and accepted when saving', function (string $status) {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');
    $supplier = Supplier::factory()->create();
    $this->actingAs($manager)->post(route('purchase-orders.store'), [
        'supplier_id' => $supplier->id,
        'order_date' => now()->toDateString(),
        'status' => $status,
        'supply_type' => 'intra',
        'items' => [['description' => 'Pipe', 'quantity' => 1, 'unit_price' => 100]],
    ])->assertSessionHasNoErrors()->assertRedirect();
    $order = Order::query()->sole();
    expect($order->status)->toBe($status);
    foreach (['index', 'create', 'edit', 'show'] as $action) {
        $this->get(route("purchase-orders.{$action}", in_array($action, ['edit', 'show']) ? $order : []))
            ->assertInertia(fn ($page) => $page->where('statuses', ['draft', 'send', 'payment_done', 'dispatched', 'goods_received']));
    }
    $this->patch(route('purchase-orders.status', $order), ['status' => $status])->assertSessionHasNoErrors();
    $this->patch(route('purchase-orders.status', $order), ['status' => 'confirmed'])->assertSessionHasErrors('status');
})->with(['draft', 'send', 'payment_done', 'dispatched', 'goods_received']);

test('purchase statuses are rejected for sales orders', function () {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');
    $order = Order::factory()->create(['branch_id' => $branch->id]);
    $this->actingAs($manager)->patch(route('sales-orders.status', $order), ['status' => 'goods_received'])->assertSessionHasErrors('status');
});

test('purchase order backgrounds are independent and render on purchase documents only', function () {
    Storage::fake('public');
    $disk = Storage::disk('public');
    $background = UploadedFile::fake()->image('purchase.png')->store('branch-purchase-order-backgrounds', 'public');
    $quotation = UploadedFile::fake()->image('quotation.png')->store('branch-quotation-backgrounds', 'public');
    $branch = Branch::factory()->create(['purchase_order_background_path' => $background, 'quotation_background_path' => $quotation]);
    $purchase = Order::factory()->purchase()->create(['branch_id' => $branch->id]);
    $sale = Order::factory()->create(['branch_id' => $branch->id]);
    $data = OrderDocument::for($purchase, 'localhost');
    expect($data['background'])->toBe($disk->path($background))
        ->and(OrderDocument::for($sale, 'localhost')['background'])->toBeNull();
    $html = view('orders.pdf', $data)->render();
    expect($html)->toContain('class="background"')->not->toContain('class="header"', $disk->path($quotation));
    expect(OrderDocument::render($purchase, 'localhost')->output())->toStartWith('%PDF');
    $disk->delete($background);
    expect(OrderDocument::for($purchase, 'localhost')['background'])->toBeNull();
});

test('purchase analytics count received goods as fulfilled and payments and dispatches as open', function () {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');
    foreach (['goods_received', 'send', 'payment_done', 'dispatched'] as $status) {
        Order::factory()->purchase()->create(['branch_id' => $branch->id, 'status' => $status, 'total' => 100, 'order_date' => now()->toDateString()]);
    }
    $this->actingAs($manager)->get(route('analytics.show', ['section' => 'purchase-orders', 'period' => 'this_month']))
        ->assertInertia(fn ($page) => $page->where('report.stats.2.value', 100)->where('report.stats.3.value', 300));
});
