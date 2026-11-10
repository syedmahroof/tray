<?php

use App\Actions\Products\SaveProductBranchPrice;
use App\Mail\QuotationMail;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Builder;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\Enquiry;
use App\Models\Product;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('users without permission cannot view quotations', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('quotations.index'))->assertForbidden();
});

test('a quotation is created with computed totals, generated number, and items', function () {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');
    $customer = Customer::factory()->create(['branch_id' => $branch->id]);
    $contact = Contact::factory()->create(['branch_id' => $branch->id]);
    $product = Product::factory()->create();

    $this->actingAs($manager)
        ->post(route('quotations.store'), [
            'customer_id' => $customer->id,
            'contact_id' => $contact->id,
            'quotation_date' => '2026-06-22',
            'status' => 'draft',
            'supply_type' => 'intra',
            'discount' => 100,
            'items' => [
                ['product_id' => $product->id, 'description' => 'Item A', 'quantity' => 2, 'unit_price' => 500, 'tax_percentage' => 18],
                ['product_id' => null, 'description' => 'Item B', 'quantity' => 1, 'unit_price' => 200, 'tax_percentage' => 18],
            ],
        ])
        ->assertRedirect();

    $quotation = Quotation::first();
    expect($quotation)->not->toBeNull();
    expect($quotation->number)->toStartWith('QT-');
    expect($quotation->created_by)->toBe($manager->id);
    // subtotal = 2*500 + 1*200 = 1200
    expect((float) $quotation->subtotal)->toBe(1200.0);
    // discount 100 allocated proportionally; 18% GST on the taxable 1100 = 198
    expect((float) $quotation->tax_amount)->toBe(198.0);
    // intra-state splits into CGST + SGST
    expect((float) $quotation->cgst_amount)->toBe(99.0);
    expect((float) $quotation->sgst_amount)->toBe(99.0);
    expect((float) $quotation->igst_amount)->toBe(0.0);
    // total = 1200 - 100 + 198 = 1298
    expect((float) $quotation->total)->toBe(1298.0);
    expect($quotation->items)->toHaveCount(2);
    expect((float) $quotation->items->firstWhere('description', 'Item A')->tax_amount)->toBe(165.0);
});

test('an inter-state quotation applies IGST instead of CGST and SGST', function () {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');
    $customer = Customer::factory()->create(['branch_id' => $branch->id]);
    $contact = Contact::factory()->create(['branch_id' => $branch->id]);

    $this->actingAs($manager)
        ->post(route('quotations.store'), [
            'customer_id' => $customer->id,
            'contact_id' => $contact->id,
            'quotation_date' => '2026-06-22',
            'status' => 'draft',
            'supply_type' => 'inter',
            'items' => [
                ['product_id' => null, 'description' => 'Item', 'quantity' => 1, 'unit_price' => 1000, 'tax_percentage' => 18],
            ],
        ])
        ->assertRedirect();

    $quotation = Quotation::first();
    expect((float) $quotation->igst_amount)->toBe(180.0);
    expect((float) $quotation->cgst_amount)->toBe(0.0);
    expect((float) $quotation->sgst_amount)->toBe(0.0);
    expect((float) $quotation->total)->toBe(1180.0);
});

test('the quotation form loads no products until they are searched for', function () {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');
    Product::factory()->create(['brand_id' => Brand::factory()]);
    Customer::factory()->create(['branch_id' => $branch->id, 'rate_tier' => 'CR']);

    $this->actingAs($manager)
        ->get(route('quotations.create'))
        ->assertInertia(fn ($page) => $page
            ->component('quotations/Create')
            ->where('customers.0.rate_tier', 'CR')
            ->where('rateTiers.PR', 'Project Rate')
            ->where('lineRateTiers.custom', 'Custom Price')
            ->where('defaultBranchId', $branch->id)
            ->has('products', 0));
});

test('the product search returns matching products a page at a time, with their rates per branch', function () {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');
    $brand = Brand::factory()->create();
    $manager->brands()->sync([$brand->id]);

    $tray = Product::factory()->create(['name' => 'Cable Tray 100 MM', 'tax_percentage' => 18, 'brand_id' => $brand->id]);
    Product::factory()->count(35)->sequence(fn ($sequence) => ['name' => sprintf('Cable Tray Bend %02d', $sequence->index)])->create(['brand_id' => $brand->id]);
    Product::factory()->create(['name' => 'Slotted Channel', 'brand_id' => $brand->id]);
    Product::factory()->create(['name' => 'Cable Tray Retired', 'brand_id' => $brand->id, 'is_active' => false]);

    app(SaveProductBranchPrice::class)->handle($tray, $branch, [
        'mrp' => 200, 'cost' => 80, 'sr_rate' => 100, 'pr_rate' => 110, 'cr_rate' => 120,
    ]);

    $this->actingAs($manager)
        ->getJson(route('quotations.products', ['search' => 'cable tray']))
        ->assertOk()
        ->assertJsonCount(30, 'data')
        ->assertJsonPath('next_page', 2)
        ->assertJsonPath('data.0.name', 'Cable Tray 100 MM')
        ->assertJsonPath("data.0.rates.{$branch->id}.PR", '110.00')
        ->assertJsonPath("data.0.rates.{$branch->id}.mrp", '200.00')
        ->assertJsonPath("data.0.rates.{$branch->id}.cost", '80.0000')
        ->assertJsonPath("data.0.rates.{$branch->id}.rate_basis", 'mrp');

    $this->actingAs($manager)
        ->getJson(route('quotations.products', ['search' => 'cable tray', 'page' => 2]))
        ->assertJsonCount(6, 'data')
        ->assertJsonPath('next_page', null)
        ->assertJsonMissing(['name' => 'Cable Tray Retired'])
        ->assertJsonMissing(['name' => 'Slotted Channel']);
});

test('the product search also matches product codes', function () {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');
    $brand = Brand::factory()->create();
    $manager->brands()->sync([$brand->id]);

    Product::factory()->create(['name' => 'Brass Ball Valve 15 MM', 'code' => 'GM20015', 'brand_id' => $brand->id]);
    Product::factory()->create(['name' => 'Brass Ball Valve 20 MM', 'code' => 'GM20020', 'brand_id' => $brand->id]);

    $this->actingAs($manager)
        ->getJson(route('quotations.products', ['search' => 'gm20015']))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.code', 'GM20015');

    $this->actingAs($manager)
        ->getJson(route('quotations.products', ['search' => 'GM200 valve']))
        ->assertJsonCount(2, 'data');
});

test('the edit form preloads only the products its lines use', function () {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');
    $brand = Brand::factory()->create();
    $manager->brands()->sync([$brand->id]);
    [$used] = Product::factory()->count(3)->create(['brand_id' => $brand->id])->all();
    $quotation = Quotation::factory()->create(['branch_id' => $branch->id]);
    $quotation->items()->create(['product_id' => $used->id, 'description' => 'Item', 'quantity' => 1, 'unit_price' => 10]);

    $this->actingAs($manager)
        ->get(route('quotations.edit', $quotation))
        ->assertInertia(fn ($page) => $page
            ->has('products', 1)
            ->where('products.0.id', $used->id));
});

test('each line keeps its own price type, and a custom line is its MRP less, or its cost plus, its percentage', function () {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');
    $customer = Customer::factory()->create(['branch_id' => $branch->id]);

    $this->actingAs($manager)->post(route('quotations.store'), [
        'customer_id' => $customer->id,
        'quotation_date' => '2026-11-07',
        'status' => 'draft',
        'supply_type' => 'intra',
        'rate_tier' => 'PR',
        'items' => [
            ['description' => 'Off MRP', 'quantity' => 2, 'rate_tier' => 'custom', 'price_basis' => 'mrp', 'list_price' => 200, 'price_percent' => 36, 'unit_price' => 1, 'tax_percentage' => 18],
            ['description' => 'On cost', 'quantity' => 1, 'rate_tier' => 'custom', 'price_basis' => 'cost', 'list_price' => 100, 'price_percent' => 20, 'unit_price' => 1, 'tax_percentage' => 18],
            ['description' => 'Tier line', 'quantity' => 1, 'rate_tier' => 'SR', 'price_basis' => 'mrp', 'list_price' => 200, 'price_percent' => 10, 'unit_price' => 50, 'tax_percentage' => 18],
            ['description' => 'Custom, no basis', 'quantity' => 1, 'rate_tier' => 'custom', 'price_basis' => null, 'list_price' => null, 'price_percent' => null, 'unit_price' => 70, 'tax_percentage' => 18],
        ],
    ])->assertRedirect();

    $quotation = Quotation::with('items')->first();
    [$offMrp, $onCost, $tier, $manual] = $quotation->items->all();

    expect($quotation->rate_tier)->toBe('PR')
        ->and($offMrp->rate_tier)->toBe('custom')
        ->and($offMrp->price_basis)->toBe('mrp')
        ->and($offMrp->list_price)->toBe('200.00')
        ->and($offMrp->price_percent)->toBe('36.00')
        ->and($offMrp->unit_price)->toBe('128.00')
        ->and($onCost->price_basis)->toBe('cost')
        ->and($onCost->unit_price)->toBe('120.00')
        // A tier line keeps its own price; its basis is kept for reference.
        ->and($tier->rate_tier)->toBe('SR')
        ->and($tier->list_price)->toBe('200.00')
        ->and($tier->unit_price)->toBe('50.00')
        ->and($manual->rate_tier)->toBe('custom')
        ->and($manual->price_basis)->toBeNull()
        ->and($manual->unit_price)->toBe('70.00')
        ->and($quotation->subtotal)->toBe('496.00');

    $this->actingAs($manager)
        ->post(route('quotations.store'), [
            'customer_id' => $customer->id,
            'quotation_date' => '2026-11-07',
            'status' => 'draft',
            'supply_type' => 'intra',
            'items' => [['description' => 'Item', 'quantity' => 1, 'rate_tier' => 'XX', 'unit_price' => 1]],
        ])
        ->assertSessionHasErrors('items.0.rate_tier');
});

test('an agent commission is spread into the line prices and kept off the printed quotation', function () {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');
    $customer = Customer::factory()->create(['branch_id' => $branch->id]);

    $this->actingAs($manager)->post(route('quotations.store'), [
        'customer_id' => $customer->id,
        'quotation_date' => '2026-11-07',
        'status' => 'draft',
        'supply_type' => 'intra',
        'rate_tier' => 'PR',
        'agent_commission' => 1000,
        'items' => [
            ['description' => 'Tray', 'quantity' => 10, 'rate_tier' => 'PR', 'unit_price' => 100, 'tax_percentage' => 18],
            ['description' => 'Channel', 'quantity' => 1, 'rate_tier' => 'custom', 'price_basis' => 'mrp', 'list_price' => 5000, 'price_percent' => 40, 'unit_price' => 0, 'tax_percentage' => 18],
        ],
    ])->assertRedirect();

    $quotation = Quotation::with('items')->first();
    [$tray, $channel] = $quotation->items->all();

    // The lines come to 1,000 + 3,000, so the 1,000 commission is a 25% markup.
    expect($quotation->agent_commission)->toBe('1000.00')
        ->and($tray->base_price)->toBe('100.00')
        ->and($tray->unit_price)->toBe('125.00')
        ->and($channel->base_price)->toBe('3000.00')
        ->and($channel->unit_price)->toBe('3750.00')
        ->and($quotation->subtotal)->toBe('5000.00')
        ->and($quotation->total)->toBe('5900.00');

    $this->actingAs($manager)
        ->get(route('quotations.show', $quotation))
        ->assertInertia(fn ($page) => $page
            ->where('quotation.agent_commission', '1000.00')
            ->where('invoice.lines.0.net_rate', 125)
            ->missing('invoice.agent_commission'));

    $this->actingAs($manager)
        ->get(route('quotations.edit', $quotation))
        ->assertInertia(fn ($page) => $page
            ->where('quotation.items.0.base_price', '100.00')
            ->where('quotation.items.1.price_percent', '40.00'));
});

test('the quotation details start from the branch, can be edited per quotation and carry into a revision', function () {
    $branch = Branch::factory()->create(['gstin' => '32ABCDE1234F1Z5', 'quotation_details' => "Sales Desk: Calicut\nMobile: 98470 00000"]);
    $manager = User::factory()->create(['branch_id' => $branch->id, 'name' => 'Anu Sales']);
    $manager->assignRole('Manager');
    $customer = Customer::factory()->create(['branch_id' => $branch->id]);

    $this->actingAs($manager)
        ->get(route('quotations.create'))
        ->assertInertia(fn ($page) => $page
            ->where("branchDefaults.{$branch->id}.details", "Sales Desk: Calicut\nMobile: 98470 00000"));

    $this->actingAs($manager)
        ->post(route('quotations.store'), [
            'customer_id' => $customer->id,
            'quotation_date' => '2026-11-08',
            'status' => 'draft',
            'supply_type' => 'intra',
            'details' => "Sales Contact: Anu Sales\nMobile: 90000 11111",
            'items' => [['description' => 'Item A', 'quantity' => 1, 'unit_price' => 100, 'tax_percentage' => 18]],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $quotation = Quotation::firstOrFail();

    expect($quotation->details)->toBe("Sales Contact: Anu Sales\nMobile: 90000 11111");

    $this->actingAs($manager)
        ->get(route('quotations.show', $quotation))
        ->assertInertia(fn ($page) => $page
            ->where('printedDetails.1', ['label' => 'Mobile', 'value' => '90000 11111']));

    $this->actingAs($manager)->post(route('quotations.revise', $quotation))->assertRedirect();

    expect(Quotation::where('version', 2)->firstOrFail()->details)->toBe($quotation->details);
});

test('a quotation raised before details were editable opens with what it printed', function () {
    $branch = Branch::factory()->create(['gstin' => '32ABCDE1234F1Z5']);
    $manager = User::factory()->create(['branch_id' => $branch->id, 'name' => 'Anu Sales', 'email' => 'anu@example.com']);
    $manager->assignRole('Manager');
    $quotation = Quotation::factory()->create(['branch_id' => $branch->id, 'created_by' => $manager->id, 'details' => null]);

    $this->actingAs($manager)
        ->get(route('quotations.edit', $quotation))
        ->assertInertia(fn ($page) => $page
            ->where('quotation.details', "Sales Contact: Anu Sales\nEmail: anu@example.com\nGSTIN: 32ABCDE1234F1Z5"));
});

test('freight is added on top of the lines and taxed at its own rate, and a site address is kept', function () {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');
    $customer = Customer::factory()->create(['branch_id' => $branch->id]);

    $this->actingAs($manager)
        ->post(route('quotations.store'), [
            'customer_id' => $customer->id,
            'quotation_date' => '2026-11-08',
            'status' => 'draft',
            'supply_type' => 'intra',
            'site_address' => "Plot 4, Infopark Phase 2\nKakkanad",
            'freight' => 500,
            'freight_tax_percent' => 18,
            'items' => [['description' => 'Item A', 'quantity' => 2, 'unit_price' => 1000, 'tax_percentage' => 12]],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $quotation = Quotation::firstOrFail();

    expect($quotation->site_address)->toBe("Plot 4, Infopark Phase 2\nKakkanad");
    expect((float) $quotation->subtotal)->toBe(2000.0);
    expect((float) $quotation->freight)->toBe(500.0);
    // 12% on the 2000 of goods (240) plus 18% on the 500 freight (90).
    expect((float) $quotation->tax_amount)->toBe(330.0);
    expect((float) $quotation->cgst_amount)->toBe(165.0);
    expect((float) $quotation->sgst_amount)->toBe(165.0);
    expect((float) $quotation->total)->toBe(2830.0);

    $this->actingAs($manager)
        ->get(route('quotations.show', $quotation))
        ->assertInertia(fn ($page) => $page
            ->where('invoice.freight', 500)
            ->where('invoice.taxable_total', 2500));
});

test('a new quotation starts with its branch terms, which can be edited, and old ones open with what they printed', function () {
    $branch = Branch::factory()->create(['quotation_terms' => "Payment: 30 days\nDelivery: 2 weeks"]);
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');

    $this->actingAs($manager)
        ->get(route('quotations.create'))
        ->assertInertia(fn ($page) => $page
            ->where("branchDefaults.{$branch->id}.terms", "Payment: 30 days\nDelivery: 2 weeks"));

    $quotation = Quotation::factory()->create(['branch_id' => $branch->id, 'terms' => null]);

    $this->actingAs($manager)
        ->get(route('quotations.edit', $quotation))
        ->assertInertia(fn ($page) => $page
            ->where('quotation.terms', "Payment: 30 days\nDelivery: 2 weeks"));
});

test('a quotation requires at least one item', function () {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');
    $customer = Customer::factory()->create(['branch_id' => $branch->id]);
    $contact = Contact::factory()->create(['branch_id' => $branch->id]);

    $this->actingAs($manager)
        ->post(route('quotations.store'), [
            'customer_id' => $customer->id,
            'contact_id' => $contact->id,
            'quotation_date' => '2026-06-22',
            'status' => 'draft',
            'supply_type' => 'intra',
            'items' => [],
        ])
        ->assertSessionHasErrors('items');
});

test('a quotation can be raised on any single entity without a customer', function (string $field) {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');

    $entityId = match ($field) {
        'contact_id' => Contact::factory()->create(['branch_id' => $branch->id])->id,
        'project_id' => Project::factory()->create(['branch_id' => $branch->id])->id,
        'builder_id' => Builder::factory()->create(['branch_id' => $branch->id])->id,
        'enquiry_id' => Enquiry::factory()->create(['branch_id' => $branch->id])->id,
    };

    $this->actingAs($manager)
        ->post(route('quotations.store'), [
            $field => $entityId,
            'quotation_date' => '2026-06-22',
            'status' => 'draft',
            'supply_type' => 'intra',
            'items' => [['description' => 'Pipe', 'quantity' => 1, 'unit_price' => 100]],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $quotation = Quotation::query()->latest('id')->firstOrFail();
    expect($quotation->customer_id)->toBeNull()
        ->and($quotation->{$field})->toBe($entityId);
})->with(['contact_id', 'project_id', 'builder_id', 'enquiry_id']);

test('a quotation must be linked to at least one entity', function () {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');

    $this->actingAs($manager)
        ->post(route('quotations.store'), [
            'quotation_date' => '2026-06-22',
            'status' => 'draft',
            'supply_type' => 'intra',
            'items' => [['description' => 'Pipe', 'quantity' => 1, 'unit_price' => 100]],
        ])
        ->assertSessionHasErrors(['customer_id' => 'Link at least one customer, contact, project, builder, or enquiry.']);
});

test('updating a quotation re-syncs items and recomputes totals', function () {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');
    $contact = Contact::factory()->create(['branch_id' => $branch->id]);
    $quotation = Quotation::factory()->create(['branch_id' => $branch->id, 'contact_id' => $contact->id]);
    $quotation->items()->create(['description' => 'Old', 'quantity' => 1, 'unit_price' => 50]);

    $this->actingAs($manager)
        ->put(route('quotations.update', $quotation), [
            'customer_id' => $quotation->customer_id,
            'contact_id' => $contact->id,
            'quotation_date' => '2026-06-22',
            'status' => 'sent',
            'supply_type' => 'intra',
            'items' => [
                ['product_id' => null, 'description' => 'New', 'quantity' => 3, 'unit_price' => 100, 'tax_percentage' => 0],
            ],
        ])
        ->assertRedirect(route('quotations.show', $quotation));

    $quotation->refresh();
    expect($quotation->status)->toBe('sent');
    expect($quotation->items)->toHaveCount(1);
    expect($quotation->items->first()->description)->toBe('New');
    expect((float) $quotation->total)->toBe(300.0);
});

test('a quotation can be downloaded as a PDF', function () {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');
    $quotation = Quotation::factory()->create(['branch_id' => $branch->id]);
    $quotation->items()->create(['description' => 'Line', 'quantity' => 1, 'unit_price' => 100]);

    $response = $this->actingAs($manager)->get(route('quotations.pdf', $quotation));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
});

test('a quotation can be streamed inline for printing', function () {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');
    $quotation = Quotation::factory()->create(['branch_id' => $branch->id]);
    $quotation->items()->create(['description' => 'Line', 'quantity' => 1, 'unit_price' => 100]);

    $response = $this->actingAs($manager)->get(route('quotations.print', $quotation));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
    expect($response->headers->get('content-disposition'))->toContain('inline');
});

test('the show page exposes a signed share url', function () {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');
    $quotation = Quotation::factory()->create(['branch_id' => $branch->id]);

    $this->actingAs($manager)
        ->get(route('quotations.show', $quotation))
        ->assertInertia(fn ($page) => $page
            ->component('quotations/Show')
            ->where('shareUrl', fn (string $url) => str_contains($url, 'signature=')));
});

test('a signed share link streams the quotation PDF without authentication', function () {
    $quotation = Quotation::factory()->create();
    $quotation->items()->create(['description' => 'Line', 'quantity' => 1, 'unit_price' => 100]);

    $url = URL::signedRoute('quotations.shared', $quotation);

    $response = $this->get($url);

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
});

test('an unsigned share link is rejected', function () {
    $quotation = Quotation::factory()->create();

    $this->get(route('quotations.shared', $quotation))->assertForbidden();
});

test('a quotation status can be updated via the quick action and is audited', function () {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');
    $quotation = Quotation::factory()->create(['branch_id' => $branch->id, 'status' => 'draft']);

    $this->actingAs($manager)
        ->patch(route('quotations.status', $quotation), ['status' => 'accepted'])
        ->assertRedirect();

    expect($quotation->fresh()->status)->toBe('accepted');
    expect($quotation->auditLogs()->where('action', 'status_changed')->exists())->toBeTrue();
});

test('an invalid status is rejected', function () {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');
    $quotation = Quotation::factory()->create(['branch_id' => $branch->id]);

    $this->actingAs($manager)
        ->patch(route('quotations.status', $quotation), ['status' => 'nonsense'])
        ->assertSessionHasErrors('status');
});

test('a quotation is emailed to its contact and marked as sent', function () {
    Mail::fake();

    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');
    $customer = Customer::factory()->create(['branch_id' => $branch->id, 'email' => 'buyer@example.com']);
    $contact = Contact::factory()->create(['branch_id' => $branch->id]);
    $quotation = Quotation::factory()->create([
        'branch_id' => $branch->id,
        'customer_id' => $customer->id,
        'contact_id' => $contact->id,
        'status' => 'draft',
    ]);

    $this->actingAs($manager)
        ->post(route('quotations.email', $quotation))
        ->assertRedirect();

    Mail::assertQueued(QuotationMail::class, fn (QuotationMail $mail) => $mail->hasTo('buyer@example.com'));
    expect($quotation->fresh()->status)->toBe('sent');
    expect($quotation->auditLogs()->where('action', 'emailed')->exists())->toBeTrue();
});

test('emailing a quotation without a customer email does not send', function () {
    Mail::fake();

    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');
    $customer = Customer::factory()->create(['branch_id' => $branch->id, 'email' => null]);
    $contact = Contact::factory()->create(['branch_id' => $branch->id]);
    $quotation = Quotation::factory()->create([
        'branch_id' => $branch->id,
        'customer_id' => $customer->id,
        'contact_id' => $contact->id,
    ]);

    $this->actingAs($manager)
        ->post(route('quotations.email', $quotation))
        ->assertRedirect();

    Mail::assertNothingQueued();
});

test('a quotation can be linked to an enquiry and a builder', function () {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');
    $customer = Customer::factory()->create(['branch_id' => $branch->id]);
    $contact = Contact::factory()->create(['branch_id' => $branch->id]);
    $builder = Builder::factory()->create(['branch_id' => $branch->id]);
    $enquiry = Enquiry::factory()->create(['branch_id' => $branch->id, 'contact_id' => $contact->id]);

    $this->actingAs($manager)
        ->post(route('quotations.store'), [
            'customer_id' => $customer->id,
            'contact_id' => $contact->id,
            'enquiry_id' => $enquiry->id,
            'builder_id' => $builder->id,
            'quotation_date' => '2026-06-22',
            'status' => 'draft',
            'supply_type' => 'intra',
            'items' => [
                ['product_id' => null, 'description' => 'Item', 'quantity' => 1, 'unit_price' => 500, 'tax_percentage' => 0],
            ],
        ])
        ->assertRedirect();

    $quotation = Quotation::first();
    expect($quotation->enquiry_id)->toBe($enquiry->id);
    expect($quotation->builder_id)->toBe($builder->id);
});

test('the create form prefills contact and project from an enquiry', function () {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');
    $contact = Contact::factory()->create(['branch_id' => $branch->id]);
    $project = Project::factory()->create(['branch_id' => $branch->id]);
    $enquiry = Enquiry::factory()->create([
        'branch_id' => $branch->id,
        'contact_id' => $contact->id,
        'project_id' => $project->id,
    ]);

    $this->actingAs($manager)
        ->get(route('quotations.create', ['enquiry_id' => $enquiry->id]))
        ->assertInertia(fn ($page) => $page
            ->component('quotations/Create')
            ->where('defaults.enquiry_id', $enquiry->id)
            ->where('defaults.contact_id', $contact->id)
            ->where('defaults.project_id', $project->id));
});

test('a contact profile lists its linked quotations', function () {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');
    $contact = Contact::factory()->create(['branch_id' => $branch->id]);
    Quotation::factory()->create(['branch_id' => $branch->id, 'contact_id' => $contact->id]);

    $this->actingAs($manager)
        ->get(route('contacts.show', $contact))
        ->assertInertia(fn ($page) => $page->has('quotations', 1));
});

test('an enquiry profile lists its linked quotations', function () {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');
    $enquiry = Enquiry::factory()->create(['branch_id' => $branch->id]);
    Quotation::factory()->create(['branch_id' => $branch->id, 'enquiry_id' => $enquiry->id]);

    $this->actingAs($manager)
        ->get(route('enquiries.show', $enquiry))
        ->assertInertia(fn ($page) => $page->has('quotations', 1));
});

test('revising a quotation clones it as a new version and supersedes the source', function () {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');
    $quotation = Quotation::factory()->create([
        'branch_id' => $branch->id,
        'number' => 'QT-2026-00007',
        'status' => 'sent',
        'rate_tier' => 'PR',
        'total' => 1500,
    ]);
    $quotation->items()->create(['description' => 'Line', 'quantity' => 3, 'unit_price' => 500]);

    $this->actingAs($manager)
        ->post(route('quotations.revise', $quotation))
        ->assertRedirect();

    $revision = Quotation::where('parent_id', $quotation->id)->first();
    expect($revision)->not->toBeNull();
    expect($revision->version)->toBe(2);
    expect($revision->number)->toBe('QT-2026-00007-R2');
    expect($revision->status)->toBe('draft');
    expect($revision->rate_tier)->toBe('PR');
    expect($revision->items)->toHaveCount(1);
    expect($quotation->fresh()->status)->toBe('revised');
});

test('revising a revision increments the version within the same root group', function () {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');
    $root = Quotation::factory()->create(['branch_id' => $branch->id, 'number' => 'QT-2026-00008']);

    $this->actingAs($manager)->post(route('quotations.revise', $root))->assertRedirect();
    $v2 = Quotation::where('parent_id', $root->id)->first();

    $this->actingAs($manager)->post(route('quotations.revise', $v2))->assertRedirect();
    $v3 = Quotation::where('parent_id', $root->id)->where('version', 3)->first();

    expect($v3)->not->toBeNull();
    expect($v3->number)->toBe('QT-2026-00008-R3');
    expect($v3->parent_id)->toBe($root->id);
});

test('the show page exposes the full version group', function () {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');
    $root = Quotation::factory()->create(['branch_id' => $branch->id]);
    $this->actingAs($manager)->post(route('quotations.revise', $root));

    $this->actingAs($manager)
        ->get(route('quotations.show', $root))
        ->assertInertia(fn ($page) => $page
            ->component('quotations/Show')
            ->has('versions', 2));
});

test('a user without the send permission cannot email a quotation', function () {
    $branch = Branch::factory()->create();
    $user = User::factory()->create(['branch_id' => $branch->id]);
    $user->givePermissionTo('quotations.view');
    $contact = Contact::factory()->create(['branch_id' => $branch->id, 'email' => 'buyer@example.com']);
    $quotation = Quotation::factory()->create(['branch_id' => $branch->id, 'contact_id' => $contact->id]);

    $this->actingAs($user)
        ->post(route('quotations.email', $quotation))
        ->assertForbidden();
});

test('the analytics page renders quotation metrics', function () {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');
    Quotation::factory()->create(['branch_id' => $branch->id, 'status' => 'accepted', 'total' => 1000, 'quotation_date' => now()->toDateString()]);
    Quotation::factory()->create(['branch_id' => $branch->id, 'status' => 'rejected', 'total' => 500, 'quotation_date' => now()->toDateString()]);

    $this->actingAs($manager)
        ->get(route('quotations.analytics'))
        ->assertRedirect(route('analytics.show', 'quotations'));

    $this->actingAs($manager)
        ->get(route('analytics.show', 'quotations'))
        ->assertInertia(fn ($page) => $page
            ->component('analytics/Show')
            ->where('report.stats.0.value', 2)
            ->where('report.stats.3.label', 'Win rate')
            ->where('report.stats.3.value', fn ($value): bool => (float) $value === 50.0)
            ->has('report.charts'));
});

test('the quotation index can be filtered by status', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    Quotation::factory()->create(['status' => 'accepted']);
    Quotation::factory()->create(['status' => 'draft']);

    $this->actingAs($admin)
        ->get(route('quotations.index', ['status' => 'accepted']))
        ->assertInertia(fn ($page) => $page
            ->has('quotations.data', 1)
            ->where('quotations.data.0.status', 'accepted')
            ->where('filters.status', 'accepted'));
});
