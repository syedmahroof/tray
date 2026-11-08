@php
    /** @var \App\Models\Quotation $quotation */
    /** @var \App\Support\QuotationInvoice $invoice */
    /** @var array<string, mixed> $company */
    /** @var string|null $logo */
    /** @var string|null $background */
    /** @var list<array{name: string, logo: string|null}> $brands */

    $logo ??= null;
    $background ??= null;
    // A background carries its own header and brand strip, so the page is
    // printed on it with room left clear for that artwork at the top and foot.
    $headerSpace = $background ? 118 : 128;
    $brands = $background ? [] : ($brands ?? []);
    $buyer = $quotation->customer;
    $contact = $quotation->contact;
    // Without a customer or contact, the builder or project is who the quotation is for.
    $fallback = $quotation->builder ?? $quotation->project;
    $buyerName = $buyer?->name ?? $contact?->name ?? $fallback?->name;
    $buyerAddress = $buyer?->address ?? $contact?->address ?? $fallback?->address;
    $buyerGstin = $quotation->gstin ?: ($buyer?->gst_number ?? $contact?->gst_number ?? $quotation->builder?->gst_number);
    $buyerState = $buyer?->state?->name ?? $contact?->state?->name ?? $fallback?->state?->name;
    $buyerLines = preg_split('/\R/u', (string) $buyerAddress, -1, PREG_SPLIT_NO_EMPTY);

    // The contact person is only named separately when the quotation is raised on a customer.
    $attention = $buyer !== null ? $contact : null;
    $fallbackPhone = $quotation->builder?->phone ?? $quotation->project?->owner_phone;
    $fallbackEmail = $quotation->builder?->email ?? $quotation->project?->owner_email;
    $attentionPhone = $attention?->phone ?? ($attention === null ? ($buyer?->phone ?? $contact?->phone ?? $fallbackPhone) : null);
    $attentionEmail = $attention?->email ?? ($attention === null ? ($buyer?->email ?? $contact?->email ?? $fallbackEmail) : null);

    $terms = preg_split('/\R/u', (string) \App\Support\QuotationDocument::terms($quotation), -1, PREG_SPLIT_NO_EMPTY);
    // "Payment : 30 days" prints as a label and value, so the colons line up down the list.
    $terms = array_map(function (string $term): array {
        $term = trim(preg_replace('/^\s*\d+[.)]\s*/', '', $term));

        return preg_match('/^([^:]{1,30}?)\s*:\s*(.*)$/', $term, $parts)
            ? ['label' => $parts[1], 'value' => $parts[2]]
            : ['label' => null, 'value' => $term];
    }, $terms);

    $lines = $invoice->lines();
    $roundOff = $invoice->roundOff();
    $companyName = $company['name'] ?? config('app.name');
    $companyPhones = implode(', ', array_filter([$company['phone'] ?? null, $company['mobile'] ?? null]));
    $bank = $company['bank'] ?? [];
    $hasBank = filled($bank['name'] ?? null);
    $bankRows = array_filter([
        'Account Name' => $companyName,
        'Bank' => $bank['name'] ?? null,
        'Account No.' => $bank['account'] ?? null,
        'RTGS/NEFT IFSC' => $bank['ifsc'] ?? null,
        'Branch' => $bank['branch'] ?? null,
    ], 'filled');

    $money = fn (float $value): string => '₹ '.\Illuminate\Support\Number::format($value, 2, locale: 'en_IN');
    $plain = fn (float $value): string => \Illuminate\Support\Number::format($value, 2, locale: 'en_IN');
    $date = fn (?\Carbon\CarbonInterface $value): string => $value?->format('d M Y') ?? '';
    // The rate is read off the amount charged, as the stored rate can lag behind it.
    $taxRate = fn (float $amount): float => $invoice->taxableTotal() > 0 ? round($amount / $invoice->taxableTotal() * 100, 2) : 0.0;
    // Room kept at the foot of every page for the brand strip and page line.
    $hasBrandLogos = collect($brands)->contains(fn (array $brand): bool => filled($brand['logo']));
    $brandRows = $brands === [] ? 0 : (int) ceil(count($brands) / ($hasBrandLogos ? 8 : 10));
    $footerHeight = 30 + ($brandRows > 0 ? 18 + $brandRows * ($hasBrandLogos ? 38 : 20) : 0);
    // Lifts the page line clear of a background's own footer artwork.
    $footerLift = $background ? 22 : 0;
    $inWords = 'Rupees '.ucwords(str_replace('-', ' ', (string) (new NumberFormatter('en_IN', NumberFormatter::SPELLOUT))->format($invoice->total()))).' Only';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $quotation->number }}</title>
    <style>
        @page { margin: {{ $headerSpace }}px 36px {{ $footerHeight + 34 + $footerLift }}px; }
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 8.5px; line-height: 1.45; color: #1f2933; margin: 0; }
        table { width: 100%; border-collapse: collapse; }
        td, th { vertical-align: top; padding: 0; }
        .muted { color: #6b7280; }
        .strong { font-weight: bold; }
        .right { text-align: right; }
        .center { text-align: center; }

        /* The background, letterhead and footer repeat on every page. */
        .background { position: fixed; top: -{{ $headerSpace }}px; left: -36px; width: 794px; height: 1123px; z-index: -1; }
        .background img { width: 794px; height: 1123px; z-index: -1; }
        /* The company address, set in the top right corner of a background's header. */
        .address { position: fixed; top: -{{ $headerSpace - 22 }}px; right: 0; width: 300px; text-align: right; font-size: 9px; color: #374151; line-height: 1.25; }
        .header { position: fixed; top: -{{ $headerSpace - 18 }}px; left: 0; right: 0; }
        .header .logo { max-height: 58px; max-width: 200px; }
        .header .company { font-size: 14px; font-weight: bold; color: #1f3a5f; letter-spacing: 0.3px; }
        .header .contact { font-size: 8px; color: #4b5563; line-height: 1.55; }
        .header .rule { border-bottom: 2px solid #1f3a5f; margin-top: 8px; }

        .footer { position: fixed; bottom: -{{ $footerHeight }}px; left: 0; right: 0; height: {{ $footerHeight - 14 }}px; font-size: 7.5px; color: #6b7280; }
        .footer .pageline { position: absolute; bottom: 0; left: 0; right: 0; border-top: 1px solid #d8dde5; padding-top: 5px; }
        .footer .page:after { content: "Page " counter(page); }

        .doc-title { font-size: 15px; font-weight: bold; color: #1f3a5f; letter-spacing: 3px; }
        .meta { width: auto; margin-left: auto; }
        .meta td { font-size: 8.5px; white-space: nowrap; vertical-align: bottom; }
        .meta .k { color: #6b7280; padding: 0 4px 0 14px; }
        .meta .v { font-weight: bold; }

        .box { border: 1px solid #d8dde5; }
        .box .label { background: #f3f5f8; border-bottom: 1px solid #d8dde5; padding: 3px 8px; font-size: 7.5px; font-weight: bold; color: #1f3a5f; letter-spacing: 0.8px; text-transform: uppercase; }
        .box .body { padding: 5px 8px; }
        .box .name { font-size: 10px; font-weight: bold; }
        .parties { margin-top: 8px; }
        .parties td.label, .parties td.body { border: 1px solid #d8dde5; }
        .parties td.label { background: #f3f5f8; padding: 3px 8px; font-size: 7.5px; font-weight: bold; color: #1f3a5f; letter-spacing: 0.8px; text-transform: uppercase; }
        .parties td.body { padding: 4px 8px; line-height: 1.4; }
        .parties td.gap { width: 2%; border: none; }
        .parties .name { font-size: 10px; font-weight: bold; }
        .kv td { padding: 1px 0; }
        .kv .k { color: #6b7280; width: 34%; white-space: nowrap; padding-right: 6px; }
        .kv .v { font-weight: bold; }

        .items { margin-top: 8px; }
        .items th { background: #1f3a5f; color: #ffffff; font-size: 7.5px; font-weight: bold; padding: 4px 4px; text-align: center; }
        .items th.left { text-align: left; }
        .items td { padding: 2px 4px; border-bottom: 1px solid #e5e8ee; font-size: 8.5px; line-height: 1.3; }
        .items tr.alt td { background: #f8f9fb; }
        .items .product { font-weight: bold; }
        .items .detail { font-size: 7.5px; color: #6b7280; }

        .summary { margin-top: 8px; }
        .words { border-left: 3px solid #1f3a5f; background: #f3f5f8; padding: 4px 8px; margin-bottom: 6px; }
        .words .k { font-size: 7.5px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.8px; }
        .words .v { font-weight: bold; font-size: 9px; }

        .totals td { padding: 2.5px 8px; }
        .totals .k { color: #4b5563; }
        .totals .v { text-align: right; font-weight: bold; white-space: nowrap; }
        .totals .sub td { border-top: 1px solid #d8dde5; }
        .totals .grand td { background: #1f3a5f; color: #ffffff; font-size: 11px; font-weight: bold; padding: 5px 8px; }

        .section { margin-top: 10px; }
        .section .heading { font-size: 8px; font-weight: bold; color: #1f3a5f; letter-spacing: 0.8px; text-transform: uppercase; border-bottom: 1px solid #d8dde5; padding-bottom: 3px; margin-bottom: 5px; }
        .summary td.bank { border: 1px solid #d8dde5; padding: 0; }
        .summary td.bank .label { background: #f3f5f8; border-bottom: 1px solid #d8dde5; padding: 3px 8px; font-size: 7.5px; font-weight: bold; color: #1f3a5f; letter-spacing: 0.8px; text-transform: uppercase; }
        .summary td.bank .body { padding: 4px 8px; }
        .summary td.bank .kv td { padding: 0.5px 0; }

        .notes-terms { margin-top: 8px; }
        .notes-terms td.label, .notes-terms td.body { border: 1px solid #d8dde5; }
        .notes-terms td.label { background: #f3f5f8; padding: 3px 8px; font-size: 7px; font-weight: bold; color: #1f3a5f; letter-spacing: 0.8px; text-transform: uppercase; }
        .notes-terms td.body { padding: 4px 8px; font-size: 7px; line-height: 1.35; }
        .notes-terms td.gap { width: 2%; border: none; }
        .terms td { padding: 0.5px 0; font-size: 7px; line-height: 1.3; }
        .terms .n { width: 12px; color: #6b7280; }
        .terms .k { width: 70px; font-weight: bold; white-space: nowrap; }
        .terms .sep { width: 8px; }

        .signoff { margin-top: 6px; page-break-inside: avoid; }
        .signoff .line { border-top: 1px solid #1f2933; margin-top: 20px; padding-top: 2px; }

        .brands { border-top: 2px solid #1f3a5f; padding-top: 6px; text-align: center; }
        .brands .heading { font-size: 7px; color: #6b7280; letter-spacing: 1px; text-transform: uppercase; margin-bottom: 4px; }
        .brands .item { display: inline-block; margin: 3px 9px; vertical-align: middle; }
        .brands img { max-height: 30px; max-width: 100px; }
        .brands .name { font-weight: bold; font-size: 9px; color: #1f3a5f; }
    </style>
</head>
<body>
    @if ($background)
        <div class="background"><img src="{{ $background }}" alt="{{ $companyName }}"></div>
        <div class="address">
            {{-- Each address line prints as it was entered for the branch. --}}
            @foreach ((array) ($company['address'] ?? []) as $addressLine)
                <div>{{ $addressLine }}</div>
            @endforeach
            @if ($companyPhones !== '')
                <div>Ph: {{ $companyPhones }}</div>
            @endif
            @if (($company['email'] ?? null) || ($company['website'] ?? null))
                <div>{{ implode('  |  ', array_filter([$company['email'] ?? null, $company['website'] ?? null])) }}</div>
            @endif
        </div>
    @else
        <div class="header">
            <table>
                <tr>
                    <td style="width: 40%; vertical-align: middle;">
                        @if ($logo)
                            <img class="logo" src="{{ $logo }}" alt="{{ $companyName }}">
                        @else
                            <div class="company">{{ $companyName }}</div>
                        @endif
                    </td>
                    <td class="right" style="width: 60%;">
                        @if ($logo)
                            <div class="company">{{ $companyName }}</div>
                        @endif
                        <div class="contact">
                            @if (($company['address'] ?? []) !== [])
                                {{ implode(', ', (array) $company['address']) }}<br>
                            @endif
                            @if ($companyPhones !== '')
                                Phone: {{ $companyPhones }}<br>
                            @endif
                            @if (($company['email'] ?? null) || ($company['website'] ?? null))
                                {{ implode('  |  ', array_filter([$company['email'] ?? null, $company['website'] ?? null])) }}<br>
                            @endif
                            @if ($company['gstin'] ?? null)
                                <span class="strong">GSTIN: {{ $company['gstin'] }}</span>
                            @endif
                        </div>
                    </td>
                </tr>
            </table>
            <div class="rule"></div>
        </div>
    @endif

    <div class="footer">
        @if ($brands !== [])
            <div class="brands">
                <div class="heading">Brands we deal in</div>
                @foreach ($brands as $brand)
                    <span class="item">
                        @if ($brand['logo'])
                            <img src="{{ $brand['logo'] }}" alt="{{ $brand['name'] }}">
                        @else
                            <span class="name">{{ $brand['name'] }}</span>
                        @endif
                    </span>
                @endforeach
            </div>
        @endif
        <table class="pageline">
            <tr>
                <td class="right page"></td>
            </tr>
        </table>
    </div>

    <table>
        <tr>
            <td style="width: 30%; vertical-align: bottom;">
                <div class="doc-title">QUOTATION</div>
            </td>
            <td style="width: 70%; vertical-align: bottom;">
                <table class="meta">
                    <tr>
                        <td class="k">Quotation No</td>
                        <td class="v">{{ $quotation->number }}@if ($quotation->version > 1) (Rev. {{ $quotation->version }})@endif</td>
                        <td class="k">Quotation Date</td>
                        <td class="v">{{ $date($quotation->quotation_date) }}</td>
                        @if ($quotation->valid_until)
                            <td class="k">Valid Until</td>
                            <td class="v">{{ $date($quotation->valid_until) }}</td>
                        @endif
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- One row of cells, so both boxes stretch to the taller one. --}}
    <table class="parties">
        <tr>
            <td class="label" style="width: 54%;">Quotation To</td>
            <td class="gap"></td>
            <td class="label" style="width: 44%;">Quotation Details</td>
        </tr>
        <tr>
            <td class="body">
                <div class="name">M/s {{ $buyerName ?? '—' }}</div>
                @if ($buyerLines !== [] || $buyerState)
                    <div>{{ implode(', ', array_filter([...$buyerLines, $buyerState ? $buyerState.', India' : null])) }}</div>
                @endif
                @if ($buyerGstin || $quotation->project)
                    <div>
                        @if ($buyerGstin)
                            <span class="muted">GSTIN:</span> <span class="strong">{{ $buyerGstin }}</span>
                        @endif
                        @if ($buyerGstin && $quotation->project)
                            &nbsp;|&nbsp;
                        @endif
                        @if ($quotation->project)
                            <span class="muted">Project:</span> <span class="strong">{{ $quotation->project->name }}</span>
                        @endif
                    </div>
                @endif
                @if (filled($quotation->site_address))
                    <div><span class="muted">Site Address:</span> {{ implode(', ', preg_split('/\R/u', $quotation->site_address, -1, PREG_SPLIT_NO_EMPTY)) }}</div>
                @endif
                @if ($attention || $attentionPhone || $attentionEmail)
                    <div>
                        {!! implode(' &nbsp;|&nbsp; ', array_filter([
                            $attention ? '<span class="muted">Kind Attn:</span> <span class="strong">'.e($attention->name).'</span>' : null,
                            $attentionPhone ? '<span class="muted">Mobile:</span> '.e($attentionPhone) : null,
                            $attentionEmail ? '<span class="muted">Email:</span> '.e($attentionEmail) : null,
                        ])) !!}
                    </div>
                @endif
            </td>
            <td class="gap"></td>
            <td class="body">
                <table class="kv">
                    @foreach (\App\Support\QuotationDocument::details($quotation) as $detail)
                        <tr>
                            @if ($detail['label'] !== null)
                                <td class="k">{{ $detail['label'] }}</td>
                                <td class="v">{{ $detail['value'] }}</td>
                            @else
                                <td class="v" colspan="2">{{ $detail['value'] }}</td>
                            @endif
                        </tr>
                    @endforeach
                </table>
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th style="width: 4%;">#</th>
                <th class="left" style="width: 53%;">Product Name</th>
                <th style="width: 9%;">HSN Code</th>
                <th style="width: 6%;">Qty</th>
                <th style="width: 6%;">Unit</th>
                <th style="width: 10%;">Price</th>
                <th style="width: 12%;">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($lines as $line)
                <tr @class(['alt' => $loop->even])>
                    <td class="center muted">{{ $line['sl'] }}</td>
                    <td>
                        <div class="product">{{ $line['description'] }}</div>
                        @if ($line['detail'])
                            <div class="detail">{!! nl2br(e($line['detail'])) !!}</div>
                        @endif
                    </td>
                    <td class="center">{{ $line['hsn'] }}</td>
                    <td class="center">{{ $invoice->quantity($line['quantity']) }}</td>
                    <td class="center">{{ strtoupper($line['unit']) }}</td>
                    <td class="right">{{ $plain($line['net_rate']) }}</td>
                    <td class="right strong">{{ $plain($line['net_amount']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- One row of cells, so the bank box stretches to the height of the totals beside it. --}}
    <table class="summary" style="page-break-inside: avoid;">
        <tr>
            @if ($hasBank)
                <td class="bank" style="width: 55%;">
                    <div class="label">Bank Details</div>
                    <div class="body">
                        <table class="kv">
                            @foreach ($bankRows as $label => $value)
                                <tr>
                                    <td class="k">{{ $label }}</td>
                                    <td class="v">{{ $value }}</td>
                                </tr>
                            @endforeach
                        </table>
                    </div>
                </td>
            @else
                <td style="width: 55%;">
                    <div class="words">
                        <div class="k">Amount in words</div>
                        <div class="v">{{ $inWords }}</div>
                    </div>
                </td>
            @endif
            <td style="width: 2%;"></td>
            <td style="width: 43%;">
                <table class="totals">
                    {{-- Line prices already carry any discount, so the lines add up to the taxable value. --}}
                    <tr>
                        <td class="k strong">Sub Total</td>
                        <td class="v">{{ $money($invoice->taxableValue()) }}</td>
                    </tr>
                    @if ($invoice->freight() > 0)
                        <tr>
                            <td class="k">Freight</td>
                            <td class="v">{{ $money($invoice->freight()) }}</td>
                        </tr>
                    @endif
                    @foreach ($invoice->taxLines() as $tax)
                        <tr>
                            <td class="k">{{ strtok($tax['label'], ' ') }} @ {{ $invoice->percent($taxRate($tax['amount'])) }}%</td>
                            <td class="v">{{ $money($tax['amount']) }}</td>
                        </tr>
                    @endforeach
                    @if (abs($roundOff) >= 0.01)
                        <tr>
                            <td class="k">Round Off</td>
                            <td class="v">{{ $roundOff < 0 ? '(-) ' : '' }}{{ $money(abs($roundOff)) }}</td>
                        </tr>
                    @endif
                    <tr class="grand">
                        <td>Grand Total</td>
                        <td class="right">{{ $money($invoice->total()) }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
    @if ($hasBank)
        <div class="words" style="margin-top: 6px; margin-bottom: 0;">
            <span class="k">Amount in words:</span> <span class="v">{{ $inWords }}</span>
        </div>
    @endif

    {{-- Terms and notes share a row of boxes, each taking the full width when alone. --}}
    @php
        $hasNotes = filled($quotation->notes);
        $termsWidth = $hasNotes ? '64%' : '100%';
    @endphp
    @if ($terms !== [] || $hasNotes)
        <table class="notes-terms" style="page-break-inside: avoid;">
            <tr>
                @if ($terms !== [])
                    <td class="label" style="width: {{ $termsWidth }};">Terms and Conditions</td>
                @endif
                @if ($terms !== [] && $hasNotes)
                    <td class="gap"></td>
                @endif
                @if ($hasNotes)
                    <td class="label">Notes</td>
                @endif
            </tr>
            <tr>
                @if ($terms !== [])
                    <td class="body">
                        <table class="terms">
                            @foreach ($terms as $term)
                                <tr>
                                    <td class="n">{{ $loop->iteration }}.</td>
                                    @if ($term['label'] !== null)
                                        <td class="k">{{ $term['label'] }}</td>
                                        <td class="sep">:</td>
                                        <td>{{ $term['value'] }}</td>
                                    @else
                                        <td colspan="3">{{ $term['value'] }}</td>
                                    @endif
                                </tr>
                            @endforeach
                        </table>
                    </td>
                @endif
                @if ($terms !== [] && $hasNotes)
                    <td class="gap"></td>
                @endif
                @if ($hasNotes)
                    <td class="body">{!! nl2br(e($quotation->notes)) !!}</td>
                @endif
            </tr>
        </table>
    @endif

    {{-- The sign-off follows straight on from the terms, so a quotation that fits one page stays on one page. --}}
    <table class="signoff">
        <tr>
            <td style="width: 70%;"></td>
            <td class="center" style="width: 30%;">
                <div class="strong">For {{ $companyName }}</div>
                <div class="line">Authorised Signatory</div>
            </td>
        </tr>
    </table>
</body>
</html>
