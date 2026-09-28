@php
    /** @var \App\Models\Quotation $quotation */
    /** @var \App\Support\QuotationInvoice $invoice */
    /** @var array<string, mixed> $company */
    /** @var string|null $logo */
    /** @var string|null $letterhead */
    /** @var list<array{name: string, logo: string|null}> $brands */

    $logo ??= null;
    $letterhead ??= null;
    $brands ??= [];
    $buyer = $quotation->customer;
    $contact = $quotation->contact;
    // Without a customer or contact, the builder or project is who the quotation is for.
    $fallback = $quotation->builder ?? $quotation->project;
    $buyerName = $buyer?->name ?? $contact?->name ?? $fallback?->name;
    $buyerAddress = $buyer?->address ?? $contact?->address ?? $fallback?->address;
    $buyerGstin = $quotation->gstin ?: $buyer?->gst_number;
    $buyerState = $buyer?->state?->name ?? $contact?->state?->name ?? $fallback?->state?->name;
    $buyerLines = preg_split('/\r\n|\r|\n/', (string) $buyerAddress, -1, PREG_SPLIT_NO_EMPTY);

    // The contact person is only named separately when the quotation is raised on a customer.
    $attention = $buyer !== null ? $contact : null;
    $fallbackPhone = $quotation->builder?->phone ?? $quotation->project?->owner_phone;
    $fallbackEmail = $quotation->builder?->email ?? $quotation->project?->owner_email;
    $attentionPhone = $attention?->phone ?? ($attention === null ? ($buyer?->phone ?? $contact?->phone ?? $fallbackPhone) : null);
    $attentionEmail = $attention?->email ?? ($attention === null ? ($buyer?->email ?? $contact?->email ?? $fallbackEmail) : null);

    $sender = $quotation->creator;
    $terms = preg_split('/\r\n|\r|\n/', (string) \App\Support\QuotationDocument::terms($quotation), -1, PREG_SPLIT_NO_EMPTY);
    $terms = array_map(fn (string $term): string => preg_replace('/^\s*\d+[.)]\s*/', '', $term), $terms);

    $lines = $invoice->lines();
    $roundOff = $invoice->roundOff();
    $companyName = $company['name'] ?? config('app.name');
    $companyPhones = implode(', ', array_filter([$company['phone'] ?? null, $company['mobile'] ?? null]));
    $bank = $company['bank'] ?? [];
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
    $taxRate = fn (float $amount): float => $invoice->taxableValue() > 0 ? round($amount / $invoice->taxableValue() * 100, 2) : 0.0;
    // Room kept at the foot of every page for the brand strip and page line.
    $hasBrandLogos = collect($brands)->contains(fn (array $brand): bool => filled($brand['logo']));
    $brandRows = $brands === [] ? 0 : (int) ceil(count($brands) / ($hasBrandLogos ? 8 : 10));
    $footerHeight = 30 + ($brandRows > 0 ? 18 + $brandRows * ($hasBrandLogos ? 38 : 20) : 0);
    $inWords = 'Rupees '.ucwords(str_replace('-', ' ', (string) (new NumberFormatter('en_IN', NumberFormatter::SPELLOUT))->format($invoice->total()))).' Only';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $quotation->number }}</title>
    <style>
        @page { margin: {{ $letterhead ? '140px' : '128px' }} 36px {{ $footerHeight + 34 }}px; }
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 8.5px; line-height: 1.45; color: #1f2933; margin: 0; }
        table { width: 100%; border-collapse: collapse; }
        td, th { vertical-align: top; padding: 0; }
        .muted { color: #6b7280; }
        .strong { font-weight: bold; }
        .right { text-align: right; }
        .center { text-align: center; }

        /* The letterhead and footer repeat on every page. */
        .header { position: fixed; top: {{ $letterhead ? '-122px' : '-110px' }}; left: 0; right: 0; }
        .header img.banner { width: 100%; max-height: 110px; }
        .header .logo { max-height: 58px; max-width: 200px; }
        .header .company { font-size: 14px; font-weight: bold; color: #1f3a5f; letter-spacing: 0.3px; }
        .header .contact { font-size: 8px; color: #4b5563; line-height: 1.55; }
        .header .rule { border-bottom: 2px solid #1f3a5f; margin-top: 8px; }

        .footer { position: fixed; bottom: -{{ $footerHeight }}px; left: 0; right: 0; height: {{ $footerHeight - 14 }}px; font-size: 7.5px; color: #6b7280; }
        .footer .pageline { position: absolute; bottom: 0; left: 0; right: 0; border-top: 1px solid #d8dde5; padding-top: 5px; }
        .footer .page:after { content: "Page " counter(page); }

        .doc-title { font-size: 20px; font-weight: bold; color: #1f3a5f; letter-spacing: 3px; }
        .meta td { padding: 1.5px 0; font-size: 8.5px; }
        .meta .k { color: #6b7280; text-align: right; padding-right: 10px; }
        .meta .v { font-weight: bold; text-align: right; width: 1%; white-space: nowrap; }

        .box { border: 1px solid #d8dde5; }
        .box .label { background: #f3f5f8; border-bottom: 1px solid #d8dde5; padding: 4px 8px; font-size: 7.5px; font-weight: bold; color: #1f3a5f; letter-spacing: 0.8px; text-transform: uppercase; }
        .box .body { padding: 7px 8px; }
        .box .name { font-size: 10px; font-weight: bold; }
        .kv td { padding: 1.5px 0; }
        .kv .k { color: #6b7280; width: 34%; white-space: nowrap; padding-right: 6px; }
        .kv .v { font-weight: bold; }

        .items { margin-top: 14px; }
        .items th { background: #1f3a5f; color: #ffffff; font-size: 7.5px; font-weight: bold; padding: 6px 5px; text-align: center; }
        .items th.left { text-align: left; }
        .items td { padding: 6px 5px; border-bottom: 1px solid #e5e8ee; font-size: 8.5px; }
        .items tr.alt td { background: #f8f9fb; }
        .items .product { font-weight: bold; }
        .items .detail { font-size: 7.5px; color: #6b7280; }

        .summary { margin-top: 12px; }
        .words { border-left: 3px solid #1f3a5f; background: #f3f5f8; padding: 6px 8px; margin-bottom: 10px; }
        .words .k { font-size: 7.5px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.8px; }
        .words .v { font-weight: bold; font-size: 9px; }

        .totals td { padding: 4px 8px; }
        .totals .k { color: #4b5563; }
        .totals .v { text-align: right; font-weight: bold; white-space: nowrap; }
        .totals .sub td { border-top: 1px solid #d8dde5; }
        .totals .grand td { background: #1f3a5f; color: #ffffff; font-size: 11px; font-weight: bold; padding: 7px 8px; }

        .section { margin-top: 16px; }
        .section .heading { font-size: 8px; font-weight: bold; color: #1f3a5f; letter-spacing: 0.8px; text-transform: uppercase; border-bottom: 1px solid #d8dde5; padding-bottom: 3px; margin-bottom: 5px; }
        .terms td { padding: 1.5px 0; }
        .terms .n { width: 16px; color: #6b7280; }

        .signoff { position: absolute; bottom: 72px; left: 0; right: 0; }
        .signoff .line { border-top: 1px solid #1f2933; margin-top: 38px; padding-top: 3px; }

        .brands { border-top: 2px solid #1f3a5f; padding-top: 6px; text-align: center; }
        .brands .heading { font-size: 7px; color: #6b7280; letter-spacing: 1px; text-transform: uppercase; margin-bottom: 4px; }
        .brands .item { display: inline-block; margin: 3px 9px; vertical-align: middle; }
        .brands img { max-height: 30px; max-width: 100px; }
        .brands .name { font-weight: bold; font-size: 9px; color: #1f3a5f; }
    </style>
</head>
<body>
    <div class="header">
        @if ($letterhead)
            <img class="banner" src="{{ $letterhead }}" alt="{{ $companyName }}">
        @else
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
        @endif
    </div>

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
                <td style="width: 70%;">{{ $companyName }} &middot; Quotation {{ $quotation->number }}</td>
                <td class="right page" style="width: 30%;"></td>
            </tr>
        </table>
    </div>

    <table>
        <tr>
            <td style="width: 50%; vertical-align: bottom;">
                <div class="doc-title">QUOTATION</div>
            </td>
            <td style="width: 50%;">
                <table class="meta">
                    <tr>
                        <td class="k">Quotation No</td>
                        <td class="v">{{ $quotation->number }}@if ($quotation->version > 1) (Rev. {{ $quotation->version }})@endif</td>
                    </tr>
                    <tr>
                        <td class="k">Quotation Date</td>
                        <td class="v">{{ $date($quotation->quotation_date) }}</td>
                    </tr>
                    @if ($quotation->valid_until)
                        <tr>
                            <td class="k">Valid Until</td>
                            <td class="v">{{ $date($quotation->valid_until) }}</td>
                        </tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    <table style="margin-top: 12px;">
        <tr>
            <td style="width: 55%; padding-right: 8px;">
                <div class="box">
                    <div class="label">Quotation To</div>
                    <div class="body">
                        <div class="name">M/s {{ $buyerName ?? '—' }}</div>
                        @foreach ($buyerLines as $line)
                            <div>{{ $line }}</div>
                        @endforeach
                        @if ($buyerState)
                            <div>{{ $buyerState }}, India</div>
                        @endif
                        @if ($buyerGstin)
                            <div style="margin-top: 3px;"><span class="muted">GSTIN:</span> <span class="strong">{{ $buyerGstin }}</span></div>
                        @endif
                        @if ($attention || $attentionPhone || $attentionEmail)
                            <div style="margin-top: 5px;">
                                @if ($attention)
                                    <span class="muted">Kind Attn:</span> <span class="strong">{{ $attention->name }}</span><br>
                                @endif
                                @if ($attentionPhone)
                                    <span class="muted">Mobile:</span> {{ $attentionPhone }}<br>
                                @endif
                                @if ($attentionEmail)
                                    <span class="muted">Email:</span> {{ $attentionEmail }}
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            </td>
            <td style="width: 45%; padding-left: 8px;">
                <div class="box">
                    <div class="label">Quotation Details</div>
                    <div class="body">
                        <table class="kv">
                            @if ($quotation->project)
                                <tr>
                                    <td class="k">Project</td>
                                    <td class="v">{{ $quotation->project->name }}</td>
                                </tr>
                            @endif
                            @if ($sender)
                                <tr>
                                    <td class="k">Sales Contact</td>
                                    <td class="v">{{ $sender->name }}</td>
                                </tr>
                                <tr>
                                    <td class="k">Email</td>
                                    <td class="v">{{ $sender->email }}</td>
                                </tr>
                            @endif
                            @if ($letterhead && ($company['gstin'] ?? null))
                                <tr>
                                    <td class="k">Our GSTIN</td>
                                    <td class="v">{{ $company['gstin'] }}</td>
                                </tr>
                            @endif
                            <tr>
                                <td class="k">Items</td>
                                <td class="v">{{ count($lines) }}</td>
                            </tr>
                        </table>
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th style="width: 4%;">#</th>
                <th class="left" style="width: 32%;">Product Name</th>
                <th style="width: 9%;">HSN Code</th>
                <th style="width: 6%;">Qty</th>
                <th style="width: 6%;">UoM</th>
                <th style="width: 11%;">List Price</th>
                <th style="width: 7%;">Disc (%)</th>
                <th style="width: 11%;">Price after Disc.</th>
                <th style="width: 14%;">Amount</th>
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
                    <td class="right">{{ $plain($line['rate']) }}</td>
                    <td class="center">{{ $invoice->percent($line['discount_percent']) }}%</td>
                    <td class="right">{{ $plain($line['net_rate']) }}</td>
                    <td class="right strong">{{ $plain($line['net_amount']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="summary" style="page-break-inside: avoid;">
        <tr>
            <td style="width: 55%; padding-right: 16px;">
                <div class="words">
                    <div class="k">Amount in words</div>
                    <div class="v">{{ $inWords }}</div>
                </div>

                @if ($bank['name'] ?? null)
                    <div class="box">
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
                    </div>
                @endif
            </td>
            <td style="width: 45%;">
                <table class="totals">
                    <tr>
                        <td class="k">Sub-Total</td>
                        <td class="v">{{ $money($invoice->subtotal()) }}</td>
                    </tr>
                    @if ($invoice->discount() > 0)
                        <tr>
                            <td class="k">Discount</td>
                            <td class="v">(-) {{ $money($invoice->discount()) }}</td>
                        </tr>
                    @endif
                    <tr class="sub">
                        <td class="k strong">Taxable Value</td>
                        <td class="v">{{ $money($invoice->taxableValue()) }}</td>
                    </tr>
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

    @if ($terms !== [])
        <div class="section">
            <div class="heading">Terms and Conditions</div>
            <table class="terms">
                @foreach ($terms as $term)
                    <tr>
                        <td class="n">{{ $loop->iteration }}.</td>
                        <td>{{ $term }}</td>
                    </tr>
                @endforeach
            </table>
        </div>
    @endif

    @if ($quotation->notes)
        <div class="section">
            <div class="heading">Notes</div>
            <div>{!! nl2br(e($quotation->notes)) !!}</div>
        </div>
    @endif

    {{-- Holds room at the end of the flow so the pinned sign-off below lands on the last page without covering anything. --}}
    <div style="height: 86px;"></div>
    <table class="signoff">
        <tr>
            <td style="width: 50%;">
                @if ($sender)
                    <div class="muted">Prepared by</div>
                    <div class="strong">{{ $sender->name }}</div>
                    <div>{{ $sender->email }}</div>
                @endif
            </td>
            <td style="width: 15%;"></td>
            <td class="center" style="width: 35%;">
                <div class="strong">For {{ $companyName }}</div>
                <div class="line">Authorised Signatory</div>
            </td>
        </tr>
    </table>
</body>
</html>
