<?php

use App\Mail\QuotationMail;
use App\Models\Branch;
use App\Models\Builder;
use App\Models\Contact;
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
    $contact = Contact::factory()->create(['branch_id' => $branch->id]);
    $product = Product::factory()->create(['branch_id' => $branch->id]);

    $this->actingAs($manager)
        ->post(route('quotations.store'), [
            'contact_id' => $contact->id,
            'quotation_date' => '2026-06-22',
            'status' => 'draft',
            'discount' => 100,
            'tax_percent' => 10,
            'items' => [
                ['product_id' => $product->id, 'description' => 'Item A', 'quantity' => 2, 'unit_price' => 500],
                ['product_id' => null, 'description' => 'Item B', 'quantity' => 1, 'unit_price' => 200],
            ],
        ])
        ->assertRedirect();

    $quotation = Quotation::first();
    expect($quotation)->not->toBeNull();
    expect($quotation->number)->toStartWith('QT-');
    expect($quotation->created_by)->toBe($manager->id);
    // subtotal = 2*500 + 1*200 = 1200
    expect((float) $quotation->subtotal)->toBe(1200.0);
    // taxable = 1200 - 100 = 1100; tax = 10% = 110
    expect((float) $quotation->tax_amount)->toBe(110.0);
    // total = 1100 + 110 = 1210
    expect((float) $quotation->total)->toBe(1210.0);
    expect($quotation->items)->toHaveCount(2);
});

test('a quotation requires at least one item', function () {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');
    $contact = Contact::factory()->create(['branch_id' => $branch->id]);

    $this->actingAs($manager)
        ->post(route('quotations.store'), [
            'contact_id' => $contact->id,
            'quotation_date' => '2026-06-22',
            'status' => 'draft',
            'items' => [],
        ])
        ->assertSessionHasErrors('items');
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
            'contact_id' => $contact->id,
            'quotation_date' => '2026-06-22',
            'status' => 'sent',
            'items' => [
                ['product_id' => null, 'description' => 'New', 'quantity' => 3, 'unit_price' => 100],
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
    $contact = Contact::factory()->create(['branch_id' => $branch->id, 'email' => 'buyer@example.com']);
    $quotation = Quotation::factory()->create([
        'branch_id' => $branch->id,
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

test('emailing a quotation without a contact email does not send', function () {
    Mail::fake();

    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');
    $contact = Contact::factory()->create(['branch_id' => $branch->id, 'email' => null]);
    $quotation = Quotation::factory()->create(['branch_id' => $branch->id, 'contact_id' => $contact->id]);

    $this->actingAs($manager)
        ->post(route('quotations.email', $quotation))
        ->assertRedirect();

    Mail::assertNothingQueued();
});

test('a quotation can be linked to an enquiry and a builder', function () {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');
    $contact = Contact::factory()->create(['branch_id' => $branch->id]);
    $builder = Builder::factory()->create(['branch_id' => $branch->id]);
    $enquiry = Enquiry::factory()->create(['branch_id' => $branch->id, 'contact_id' => $contact->id]);

    $this->actingAs($manager)
        ->post(route('quotations.store'), [
            'contact_id' => $contact->id,
            'enquiry_id' => $enquiry->id,
            'builder_id' => $builder->id,
            'quotation_date' => '2026-06-22',
            'status' => 'draft',
            'items' => [
                ['product_id' => null, 'description' => 'Item', 'quantity' => 1, 'unit_price' => 500],
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
        ->assertInertia(fn ($page) => $page
            ->component('quotations/Analytics')
            ->where('stats.total', 2)
            ->where('stats.winRate', fn ($value): bool => (float) $value === 50.0)
            ->has('statusBreakdown')
            ->has('trend'));
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
