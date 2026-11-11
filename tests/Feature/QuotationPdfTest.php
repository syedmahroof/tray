<?php

use App\Mail\QuotationMail;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Builder;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Project;
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
    $this->quotation->update(['terms' => "1. GST 18% Extra\nPayment Against Proforma Invoice\n3. Delivery Shedule : Within 7 days\n4. Freight Charge :", 'project_id' => Project::factory()->create(['name' => 'ILLAM'])->id]);

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
        ->toContain('02 Sep 2026')
        ->toContain('QT-000001')
        ->toContain('>Unit</th>')
        ->toContain('>Price</th>')
        ->not->toContain('List Price')
        ->not->toContain('Price after')
        ->not->toContain('Disc (%)')
        ->not->toContain('UoM')
        ->toContain('Sub Total')
        ->toContain('GST @ 9%')
        ->toContain('Rupees Three Hundred Seventy Six Only')
        ->toContain('₹ 376.00')
        ->toContain('Terms and Conditions')
        ->toContain('GST 18% Extra')
        ->toContain('Payment Against Proforma Invoice')
        ->toContain('<td class="k">Delivery Shedule</td>')
        ->toContain('<td>Within 7 days</td>')
        ->toContain('<td class="k">Freight Charge</td>')
        ->toMatch('/<div class="name">M\/s [^<]*<\/div>((?!<td).)*Project:<\/span> <span class="strong">ILLAM</s')
        ->not->toContain('Our GSTIN')
        ->toContain('Bank Details')
        ->toContain('BUILD TECH SUPPORTS')
        ->toContain('Authorised Signatory')
        ->not->toContain('Prepared by');
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

test('a discounted line prints its price after discount as the price', function () {
    $this->quotation->update(['discount' => 111.56]);

    $html = view('quotations.pdf', [
        'quotation' => $this->quotation->load(['customer.state', 'contact.state', 'project', 'creator', 'items.product']),
        'invoice' => new QuotationInvoice($this->quotation),
        'company' => Branding::companyForHost('localhost'),
        'logo' => null,
    ])->render();

    expect($html)
        ->toContain('<td class="right">81.25</td>')
        ->not->toContain('<td class="right">125.00</td>')
        ->not->toContain('Sub-Total')
        ->not->toContain('>Discount<');
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
        ->toContain('Rate EXGODOWN CHAWRI')
        ->toContain('GST 18% Extra')
        ->not->toContain('BUILD TECH SUPPORTS');
});

test('a branch background is printed under every page in place of the logo, contact lines and brand strip', function () {
    Storage::fake('public');
    $logo = UploadedFile::fake()->image('logo.png')->store('branch-logos', 'public');
    $background = UploadedFile::fake()->image('background.png', 1414, 2000)->store('branch-quotation-backgrounds', 'public');
    $brand = Brand::factory()->create(['name' => 'Zoloto Valves']);

    $this->quotation->branch->brands()->attach($brand);
    $this->quotation->branch->update([
        'logo_path' => $logo,
        'quotation_background_path' => $background,
        'gstin' => '32ABCDE1234F1Z5',
        'address' => "12/345 Mavoor Road\nKozhikode",
        'phone' => '0495 2700000',
    ]);

    $document = QuotationDocument::for($this->quotation->fresh(), 'localhost');

    expect($document['background'])->toBe(Storage::disk('public')->path($background));

    $html = view('quotations.pdf', $document)->render();

    expect($html)
        ->toContain('class="background"')
        ->toContain(Storage::disk('public')->path($background))
        ->not->toContain(Storage::disk('public')->path($logo))
        ->not->toContain('class="contact"')
        ->not->toContain('Brands we deal in')
        // The company address sits in the top right corner of the header.
        ->toContain('class="address"')
        ->toContain('<div>12/345 Mavoor Road</div>')
        ->toContain('<div>Kozhikode</div>')
        ->toContain('<div>Ph: 0495 2700000</div>')
        ->not->toContain('<div class="company">')
        // The branch GSTIN moves into the details box, as the contact lines are not printed.
        ->toContain('32ABCDE1234F1Z5');

    QuotationDocument::render($this->quotation->fresh(), 'localhost')->output();
});

test('the old letterhead banner is no longer printed on quotations', function () {
    Storage::fake('public');
    $letterhead = UploadedFile::fake()->image('letterhead.png', 1200, 200)->store('branch-letterheads', 'public');

    $this->quotation->branch->update(['letterhead_path' => $letterhead]);

    $document = QuotationDocument::for($this->quotation->fresh(), 'localhost');

    expect($document['background'])->toBeNull();
    expect(view('quotations.pdf', $document)->render())
        ->not->toContain(Storage::disk('public')->path($letterhead))
        ->not->toContain('class="background"')
        ->toContain('class="header"');
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

test('a long quotation runs over several pages with the sign-off only once, at the end', function () {
    $product = Product::factory()->create(['name' => 'FIBROCAST MHC', 'unit' => 'nos']);

    foreach (range(4, 50) as $line) {
        QuotationItem::factory()->create([
            'quotation_id' => $this->quotation->id,
            'product_id' => $product->id,
            'description' => "Line item number {$line}",
            'quantity' => 1,
            'unit_price' => 10,
        ]);
    }

    $pdf = QuotationDocument::render($this->quotation->fresh(), 'localhost');
    $html = $pdf->getDomPDF()->outputHtml();
    $pdf->output();

    expect($pdf->getDomPDF()->getCanvas()->get_page_count())->toBeGreaterThan(2);
    expect(substr_count($html, 'Authorised Signatory'))->toBe(1);
    expect($html)
        ->toContain('Line item number 4')
        ->toContain('Line item number 50');
});

test('a short quotation keeps its items box open down the page and still prints on one page', function (bool $withBackground) {
    Storage::fake('public');
    $this->quotation->items()->where('description', '!=', 'PC WALL BRACKET 24"')->delete();
    $this->quotation->branch->update([
        'bank_name' => 'Axis bank',
        'bank_account_number' => '921020043177051',
        'bank_ifsc' => 'UTIB0003079',
        'bank_branch' => 'Jayamahal Extension',
        'quotation_background_path' => $withBackground
            ? UploadedFile::fake()->image('background.png', 1414, 2000)->store('branch-quotation-backgrounds', 'public')
            : null,
    ]);
    $this->quotation->update([
        'terms' => "GST : 18 % Included in above Grand Total rates.\nPayment : ADVANCE\nValidity : 20 DAYS\nDelivery Time : WITHIN 2 DAYS\nFreight Charge : FREE DELIVERY\nAll Above Prices Are Ex-Godown Karnataka.",
        'notes' => 'DELIVERY',
    ]);

    $quotation = $this->quotation->fresh();
    $html = view('quotations.pdf', QuotationDocument::for($quotation, 'localhost'))->render();
    $pdf = QuotationDocument::render($quotation, 'localhost');

    expect($html)->toMatch('/<tr class="filler">\s*<td colspan="7" style="height: [1-9]\d{2}px;"><\/td>/');
    expect($pdf->getDomPDF()->getCanvas()->get_page_count())->toBe(1);
})->with(['plain letterhead' => false, 'branch background' => true]);

test('a quotation that fills its page leaves the items box at the height of its lines', function () {
    $product = Product::factory()->create(['name' => 'FIBROCAST MHC', 'unit' => 'nos']);

    QuotationItem::factory()->count(30)->create([
        'quotation_id' => $this->quotation->id,
        'product_id' => $product->id,
        'description' => 'FIBROCAST MHC',
        'quantity' => 1,
        'unit_price' => 10,
    ]);

    $html = view('quotations.pdf', QuotationDocument::for($this->quotation->fresh(), 'localhost'))->render();

    expect($html)->not->toContain('class="filler"');
});

test('a quotation without a customer or contact is billed to its builder', function () {
    $builder = Builder::factory()->create([
        'name' => 'Skyline Developers',
        'address' => 'MG Road, Kochi',
        'phone' => '9847000000',
    ]);
    $this->quotation->update(['customer_id' => null, 'contact_id' => null, 'builder_id' => $builder->id]);

    $html = view('quotations.pdf', [
        'quotation' => $this->quotation->fresh()->load(['customer.state', 'contact.state', 'project.state', 'builder.state', 'creator', 'items.product']),
        'invoice' => new QuotationInvoice($this->quotation),
        'company' => Branding::companyForHost('localhost'),
        'logo' => null,
    ])->render();

    expect($html)
        ->toContain('Skyline Developers')
        ->toContain('MG Road, Kochi')
        ->toContain('9847000000');
});

test('a quotation raised on a contact or builder prints their GST number', function (string $party) {
    $record = $party === 'contact'
        ? Contact::factory()->create(['gst_number' => '32CONTACT1234Z5'])
        : Builder::factory()->create(['gst_number' => '32BUILDER1234Z5']);

    $this->quotation->update(['customer_id' => null, 'gstin' => null, "{$party}_id" => $record->id]);

    $html = view('quotations.pdf', [
        'quotation' => $this->quotation->fresh()->load(['customer.state', 'contact.state', 'builder.state', 'project', 'creator', 'items.product']),
        'invoice' => new QuotationInvoice($this->quotation),
        'company' => Branding::companyForHost('localhost'),
        'logo' => null,
    ])->render();

    expect($html)->toContain($record->gst_number);
})->with(['contact', 'builder']);

test('the quotation details box prints the quotation\'s own lines, else the branch\'s, else the sender and GSTIN', function () {
    $sender = User::factory()->create(['name' => 'Anu Sales', 'email' => 'anu@example.com']);
    $this->quotation->update(['created_by' => $sender->id]);
    $this->quotation->branch->update(['gstin' => '32ABCDE1234F1Z5', 'quotation_details' => null]);

    expect(QuotationDocument::details($this->quotation->fresh()))->toBe([
        ['label' => 'Sales Contact', 'value' => 'Anu Sales'],
        ['label' => 'Email', 'value' => 'anu@example.com'],
        ['label' => 'GSTIN', 'value' => '32ABCDE1234F1Z5'],
    ]);

    $this->quotation->branch->update(['quotation_details' => "Sales Desk: Calicut Office\nMobile: 98470 00000"]);

    expect(QuotationDocument::details($this->quotation->fresh()))->toBe([
        ['label' => 'Sales Desk', 'value' => 'Calicut Office'],
        ['label' => 'Mobile', 'value' => '98470 00000'],
    ]);

    $this->quotation->update(['details' => "Sales Contact: Rahul\nDelivery from our Kozhikode godown"]);

    $html = view('quotations.pdf', QuotationDocument::for($this->quotation->fresh(), 'localhost'))->render();

    expect($html)
        ->toContain('<td class="k">Sales Contact</td>')
        ->toContain('<td class="v">Rahul</td>')
        ->toContain('<td class="v" colspan="2">Delivery from our Kozhikode godown</td>')
        ->not->toContain('Calicut Office')
        ->not->toContain('anu@example.com');
});

test('the print shows freight above the tax, the site address, and only the page number in the footer', function () {
    $this->quotation->update([
        'freight' => 250,
        'freight_tax_percent' => 18,
        'site_address' => "Plot 4, Infopark Phase 2\nKakkanad",
    ]);

    $html = view('quotations.pdf', QuotationDocument::for($this->quotation->fresh(), 'localhost'))->render();

    expect($html)
        ->toContain('<td class="k">Freight</td>')
        ->toContain('Site Address:</span> Plot 4, Infopark Phase 2, Kakkanad')
        ->not->toContain('&middot; Quotation QT-000001');
});

test('bank details sit beside the totals, and terms and notes share a row of boxes', function () {
    $this->quotation->branch->update(['bank_name' => 'Federal Bank', 'bank_account_number' => '123456']);
    $this->quotation->update(['terms' => 'Payment: 30 days', 'notes' => 'Prices firm for this order']);

    $html = view('quotations.pdf', QuotationDocument::for($this->quotation->fresh(), 'localhost'))->render();

    expect($html)
        ->toMatch('/class="summary"[^>]*>\s*<tr>\s*<td class="bank" style="width: 55%;">\s*<div class="label">Bank Details<\/div>.*?<table class="totals">/s')
        ->toMatch('/class="notes-terms"[^>]*>\s*<tr>\s*<td class="label" style="width: 64%;">Terms and Conditions<\/td>\s*<td class="gap"><\/td>\s*<td class="label">Notes<\/td>/s');
});

test('the branch city is left off the quotation', function () {
    $this->quotation->branch->update(['address' => "12, MG Road\nKochi", 'city' => 'Ernakulam']);

    $company = QuotationDocument::company($this->quotation->fresh(), 'localhost');
    $html = view('quotations.pdf', QuotationDocument::for($this->quotation->fresh(), 'localhost'))->render();

    expect($company['address'])->toBe(['12, MG Road', 'Kochi'])
        ->and($html)->not->toContain('Ernakulam');
});

test('an address pasted with Word or PDF line separators keeps its line breaks', function () {
    $this->quotation->branch->update(['address' => "59/4699, Panchavadi Arcade,\u{2028}M.N. Road, Calicut\r\nKerala", 'city' => null]);

    expect($this->quotation->branch->fresh()->letterhead()['address'])->toBe([
        '59/4699, Panchavadi Arcade,',
        'M.N. Road, Calicut',
        'Kerala',
    ]);
});
