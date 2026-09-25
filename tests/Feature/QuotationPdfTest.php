<?php

use App\Mail\QuotationMail;
use App\Models\Branch;
use App\Models\Brand;
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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

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

test('the printed page follows the quotation letter layout', function () {
    $this->quotation->update(['terms' => "1. GST 18% Extra\nPayment Against Proforma Invoice"]);

    $html = view('quotations.pdf', [
        'quotation' => $this->quotation->load(['customer.state', 'contact.state', 'project', 'creator', 'items.product']),
        'invoice' => new QuotationInvoice($this->quotation),
        'company' => Branding::companyForHost('localhost'),
        'logo' => null,
    ])->render();

    expect($html)
        ->toContain('Quotation')
        ->toContain('M/s EVERFINE ASSOCIATES WAYANAD')
        ->toContain('32AAEFE8477F1ZE')
        ->toContain('Quotation Date')
        ->toContain('02/09/2026')
        ->toContain('QT-000001')
        ->toContain('List Price')
        ->toContain('Price after')
        ->toContain('Pre -Tax Sub-Total')
        ->toContain('₹ 376.00')
        ->toContain('Terms and Conditions')
        ->toContain('1. GST 18% Extra')
        ->toContain('2. Payment Against Proforma Invoice')
        ->toContain('Bank Details')
        ->toContain('BUILD TECH SUPPORTS');
});

test('the quotation discount is spread across the lines as a percentage off list', function () {
    $this->quotation->update(['discount' => 111.56]);

    $invoice = new QuotationInvoice($this->quotation->load('items.product'));
    $line = $invoice->lines()[0];

    expect($invoice->discountPercent())->toBe(35.0);
    expect($line['rate'])->toBe(125.0);
    expect($line['discount_percent'])->toBe(35.0);
    expect($line['net_rate'])->toBe(81.25);
    expect($line['net_amount'])->toBe(81.25);
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
        ->toContain('RTGS/NEFT IFSC')
        ->toContain('FDRL0001234')
        ->toContain('Wayanad')
        ->not->toContain('Axis Bank');
});

test('a branch without its own details prints its name and nothing borrowed from another company', function () {
    $document = QuotationDocument::for($this->quotation, 'localhost');

    expect($document['company']['name'])->toBe('Calicut');
    expect($document['company']['gstin'])->toBeNull();
    expect($document['company']['bank'])->toBeNull();
    expect($document['logo'])->toBeNull();

    $html = QuotationDocument::render($this->quotation, 'localhost')->getDomPDF()->outputHtml();

    expect($html)
        ->toContain('Calicut')
        ->not->toContain('BUILD TECH SUPPORTS')
        ->not->toContain('Axis Bank')
        ->not->toContain('Bank Details');
});

test('the quotation page is handed the same document the print is built from', function () {
    $this->quotation->branch->update(['bank_name' => 'Federal Bank']);

    $this->actingAs($this->admin)
        ->get(route('quotations.show', $this->quotation))
        ->assertInertia(fn ($page) => $page
            ->component('quotations/Show')
            ->where('company.name', 'Calicut')
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

test('a branch letterhead replaces the company details it fills in', function () {
    Storage::fake('public');
    $logo = UploadedFile::fake()->image('logo.png')->store('branch-logos', 'public');

    $this->quotation->branch->update([
        'company_name' => 'PIPELINE PRODUCTS (INDIA)',
        'logo_path' => $logo,
        'email' => 'info@pipelineproductsindia.com',
        'website' => 'www.pipelineproductsindia.com',
        'gstin' => '07AALFP0328D1ZJ',
        'quotation_terms' => "Rate EXGODOWN CHAWRI\nGST 18% Extra",
    ]);

    $document = QuotationDocument::for($this->quotation->fresh(), 'localhost');

    expect($document['company']['name'])->toBe('PIPELINE PRODUCTS (INDIA)');
    expect($document['company']['gstin'])->toBe('07AALFP0328D1ZJ');
    expect($document['company']['phone'])->toBeNull();
    expect($document['logo'])->toBe(Storage::disk('public')->path($logo));

    $html = QuotationDocument::render($this->quotation->fresh(), 'localhost')->getDomPDF()->outputHtml();

    expect($html)
        ->toContain('PIPELINE PRODUCTS (INDIA)')
        ->toContain('www.pipelineproductsindia.com')
        ->toContain('1. Rate EXGODOWN CHAWRI')
        ->toContain('2. GST 18% Extra')
        ->not->toContain('BUILD TECH SUPPORTS');
});

test('a quotation with its own terms prints them over the branch defaults', function () {
    $this->quotation->branch->update(['quotation_terms' => 'Branch default term']);
    $this->quotation->update(['terms' => 'Quotation specific term']);

    expect(QuotationDocument::terms($this->quotation->fresh()))->toBe('Quotation specific term');
});

test('the branch brands are printed under the quotation footer', function () {
    $this->quotation->branch->brands()->attach([
        Brand::factory()->create(['name' => 'Supreme'])->id,
        Brand::factory()->create(['name' => 'Astral'])->id,
        Brand::factory()->create(['name' => 'Retired Brand', 'is_active' => false])->id,
    ]);

    $quotation = $this->quotation->fresh();

    expect(array_column(QuotationDocument::brands($quotation), 'name'))->toBe(['Astral', 'Supreme']);

    $html = QuotationDocument::render($quotation, 'localhost')->getDomPDF()->outputHtml();

    expect($html)
        ->toContain('Astral')
        ->toContain('Supreme')
        ->not->toContain('Retired Brand');
});

test('the quotation page shows the branch default terms when the quotation has none', function () {
    $this->quotation->branch->update(['quotation_terms' => "GST 18% Extra\nPayment Against Proforma Invoice"]);

    $this->actingAs($this->admin)
        ->get(route('quotations.show', $this->quotation))
        ->assertInertia(fn ($page) => $page
            ->where('terms', "GST 18% Extra\nPayment Against Proforma Invoice")
            ->where('invoice.discount_percent', 0));
});
