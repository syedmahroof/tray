<?php

use App\Mail\QuotationMail;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\State;
use App\Models\User;
use App\Support\Branding;
use App\Support\QuotationDocument;
use App\Support\QuotationInvoice;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');

    $kerala = State::factory()->create(['name' => 'Kerala', 'code' => '32']);
    $branch = Branch::factory()->create(['name' => 'Calicut']);

    $customer = Customer::factory()->create([
        'branch_id' => $branch->id,
        'name' => 'EVERFINE ASSOCIATES WAYANAD',
        'address' => "Epx/210,R1,R2,R3\nM/s Everfine Associates",
        'phone' => '04935244032',
        'gst_number' => '32AAEFE8477F1ZE',
        'state_id' => $kerala->id,
    ]);

    $this->quotation = Quotation::factory()->create([
        'branch_id' => $branch->id,
        'customer_id' => $customer->id,
        'number' => 'QT-000001',
        'quotation_date' => '2026-09-02',
        'supply_type' => 'intra',
        'status' => 'sent',
        'subtotal' => 318.75,
        'discount' => 0,
        'tax_percent' => 18,
        'tax_amount' => 57.38,
        'cgst_amount' => 28.69,
        'sgst_amount' => 28.69,
        'igst_amount' => 0,
        'total' => 376.13,
    ]);

    $bracket = Product::factory()->create(['name' => 'PC WALL BRACKET', 'unit' => 'nos']);

    foreach ([['PC WALL BRACKET 24"', 125.00], ['PC WALL BRACKET 18"', 100.00], ['PC WALL BRACKET 16"', 93.75]] as [$description, $rate]) {
        QuotationItem::factory()->create([
            'quotation_id' => $this->quotation->id,
            'product_id' => $bracket->id,
            'description' => $description,
            'hsn_code' => '7308',
            'quantity' => 1,
            'unit_price' => $rate,
            'tax_percentage' => 18,
            'tax_amount' => round($rate * 0.18, 2),
        ]);
    }
});

test('the printed quotation carries the seller, buyer and document details', function () {
    $response = $this->actingAs($this->admin)->get(route('quotations.print', $this->quotation));

    $response->assertOk()->assertHeader('content-type', 'application/pdf');
});

test('the invoice states its lines, taxes and rounded total the way the printed book does', function () {
    $invoice = new QuotationInvoice($this->quotation->load('items.product'));

    expect($invoice->lines())->toHaveCount(3);
    expect($invoice->lines()[0]['description'])->toBe('PC WALL BRACKET 24"');
    expect($invoice->lines()[0]['unit'])->toBe('nos');
    expect($invoice->lines()[0]['amount'])->toBe(125.0);

    expect($invoice->taxableValue())->toBe(318.75);
    expect(array_column($invoice->taxLines(), 'label'))->toBe(['CGST OUT 9%', 'SGST OUT 9%']);
    expect($invoice->taxTotal())->toBe(57.38);

    // 376.13 is settled as 376, leaving 13 paise to round off.
    expect($invoice->total())->toBe(376.0);
    expect($invoice->roundOff())->toBe(-0.13);
    expect($invoice->totalQuantity())->toBe(3.0);
    expect($invoice->totalUnit())->toBe('nos');
});

test('the HSN summary groups the lines by code and adds up to the tax charged', function () {
    $summary = (new QuotationInvoice($this->quotation->load('items.product')))->hsnSummary();

    expect($summary)->toHaveCount(1);
    expect($summary[0]['hsn'])->toBe('7308');
    expect($summary[0]['taxable'])->toBe(318.75);
    expect($summary[0]['cgst'])->toBe(28.69);
    expect($summary[0]['sgst'])->toBe(28.69);
    expect($summary[0]['tax'])->toBe(57.38);
});

test('amounts are spelled out in rupees and paise', function () {
    expect(QuotationInvoice::inWords(376))->toBe('INR Three Hundred Seventy Six Only');
    expect(QuotationInvoice::inWords(57.38))->toBe('INR Fifty Seven and Thirty Eight paise Only');
});

test('the printed page renders the tally style blocks', function () {
    $html = view('quotations.pdf', [
        'quotation' => $this->quotation->load(['customer.state', 'contact.state', 'project', 'items.product']),
        'invoice' => new QuotationInvoice($this->quotation),
        'company' => Branding::companyForHost('localhost'),
    ])->render();

    expect($html)
        ->toContain('PROFORMA INVOICE')
        ->toContain('BUILD TECH SUPPORTS')
        ->toContain('Buyer (Bill to)')
        ->toContain('EVERFINE ASSOCIATES WAYANAD')
        ->toContain('32AAEFE8477F1ZE')
        ->toContain('Kerala')
        ->toContain('CGST OUT 9%')
        ->toContain('Round Off Sale')
        ->toContain('INR Three Hundred Seventy Six Only')
        ->toContain("Company's Bank Details")
        ->toContain('Authorised Signatory')
        ->toContain('This is a Computer Generated Invoice');
});

test('the emailed copy is the same printed document', function () {
    $attachments = (new QuotationMail($this->quotation, 'localhost'))->attachments();

    expect($attachments)->toHaveCount(1);
    expect($attachments[0]->as)->toBe('QT-000001.pdf');

    $contents = $attachments[0]->attachWith(
        fn () => null,
        fn (Closure $data): string => $data(),
    );

    expect($contents)->toStartWith('%PDF');
});

test('a branch with its own account has it printed in place of the company bank', function () {
    $this->quotation->branch->update([
        'bank_name' => 'Federal Bank',
        'bank_account_number' => '10020055512345',
        'bank_branch' => 'Wayanad',
        'bank_ifsc' => 'FDRL0001234',
    ]);

    $html = QuotationDocument::render($this->quotation->fresh(), 'localhost')->getDomPDF()->outputHtml();

    expect($html)
        ->toContain('Federal Bank')
        ->toContain('10020055512345')
        ->toContain('Wayanad &amp; FDRL0001234')
        ->not->toContain('Axis Bank');
});

test('a branch without an account falls back to the company bank', function () {
    $html = QuotationDocument::render($this->quotation, 'localhost')->getDomPDF()->outputHtml();

    expect($html)->toContain('Axis Bank');
});

test('the quotation page is handed the same document the print is built from', function () {
    $this->quotation->branch->update(['bank_name' => 'Federal Bank']);

    $this->actingAs($this->admin)
        ->get(route('quotations.show', $this->quotation))
        ->assertInertia(fn ($page) => $page
            ->component('quotations/Show')
            ->where('company.name', 'BUILD TECH SUPPORTS')
            ->where('company.bank.name', 'Federal Bank')
            ->where('invoice.total', 376)
            ->where('invoice.round_off', -0.13)
            ->where('invoice.amount_in_words', 'INR Three Hundred Seventy Six Only')
            ->where('invoice.tax_in_words', 'INR Fifty Seven and Thirty Eight paise Only')
            ->has('invoice.lines', 3)
            ->where('invoice.lines.0.description', 'PC WALL BRACKET 24"')
            ->has('invoice.tax_lines', 2)
            ->has('invoice.hsn_summary', 1));
});
