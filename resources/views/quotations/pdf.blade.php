@php
    /** @var \App\Models\Quotation $quotation */
    /** @var \App\Support\QuotationInvoice $invoice */
    /** @var array<string, mixed> $company */
    /** @var string|null $logo */
    /** @var list<array{name: string, logo: string|null}> $brands */

    $logo ??= null;
    $brands ??= [];
    $buyer = $quotation->customer;
    $contact = $quotation->contact;
    $buyerName = $buyer?->name ?? $contact?->name;
    $buyerAddress = $buyer?->address ?? $contact?->address;
    $buyerGstin = $quotation->gstin ?: $buyer?->gst_number;
    $buyerState = $buyer?->state?->name ?? $contact?->state?->name;
    $buyerLines = preg_split('/\r\n|\r|\n/', (string) $buyerAddress, -1, PREG_SPLIT_NO_EMPTY);

    // The contact person is only named separately when the quotation is raised on a customer.
    $attention = $buyer !== null ? $contact : null;
    $attentionPhone = $attention?->phone ?? ($attention === null ? ($buyer?->phone ?? $contact?->phone) : null);
    $attentionEmail = $attention?->email ?? ($attention === null ? ($buyer?->email ?? $contact?->email) : null);

    $sender = $quotation->creator;
    $terms = preg_split('/\r\n|\r|\n/', (string) \App\Support\QuotationDocument::terms($quotation), -1, PREG_SPLIT_NO_EMPTY);
    $terms = array_map(fn (string $term): string => preg_replace('/^\s*\d+[.)]\s*/', '', $term), $terms);

    $lines = $invoice->lines();
    $roundOff = $invoice->roundOff();
    $companyName = $company['name'] ?? config('app.name');
    $bank = $company['bank'] ?? [];

    $money = fn (float $value): string => '₹ '.\Illuminate\Support\Number::format($value, 2, locale: 'en_IN');
    $plain = fn (float $value): string => \Illuminate\Support\Number::format($value, 2, locale: 'en_IN');
    $date = fn (?\Carbon\CarbonInterface $value): string => $value?->format('d/m/Y') ?? '';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $quotation->number }}</title>
    <style>
        @page { margin: 118px 34px 40px; }
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 9px; line-height: 1.4; color: #1a1a1a; margin: 0; }
        table { width: 100%; border-collapse: collapse; }
        td, th { vertical-align: top; }

        /* The letterhead repeats on every page. */
        .letterhead { position: fixed; top: -104px; left: 0; right: 0; text-align: center; }
        .letterhead img { max-height: 52px; max-width: 260px; }
        .letterhead .brand { font-size: 18px; font-weight: bold; color: #1f3f99; }
        .letterhead .link { color: #1a1ae6; font-weight: bold; font-size: 9px; line-height: 1.5; }
        .page-number { position: fixed; bottom: -26px; left: 0; right: 0; text-align: center; font-size: 10px; }
        .page-number:after { content: counter(page); }

        .title { text-align: center; font-size: 17px; font-weight: bold; color: #2f80ed; text-decoration: underline; padding: 2px 0 14px; }

        .party .name { font-weight: bold; font-size: 10px; }
        .keys td { padding: 0 0 1px; }
        .keys .k { font-weight: bold; white-space: nowrap; }
        .bold { font-weight: bold; }
        .right { text-align: right; }
        .center { text-align: center; }

        .items { margin-top: 14px; border: 1px solid #e3dcc2; }
        .items th, .items td { border: 1px solid #e3dcc2; padding: 6px 5px; }
        .items th { background: #efefef; font-size: 8px; font-weight: bold; }
        .items td { font-size: 8.5px; }
        .items .product { font-weight: bold; }
        .items .detail { font-size: 8px; color: #333; }

        .totals { margin-top: 12px; }
        .totals td { padding: 4px 6px; font-weight: bold; font-size: 9px; }
        .totals .label { text-align: right; width: 50%; }
        .totals .value { text-align: right; }
        .totals .shade { background: #d9d9d9; }

        .band { background: #d9d9d9; border-top: 1.5px solid #333; border-bottom: 1.5px solid #333; text-align: center; font-weight: bold; font-size: 11px; padding: 6px 0; margin: 16px -34px 8px; }
        .terms td { padding: 0 0 1px; font-size: 9px; }

        .bank { border: 1px solid #e3dcc2; table-layout: fixed; }
        .bank td { border: 1px solid #e3dcc2; padding: 7px 6px; font-weight: bold; font-size: 9px; width: 25%; vertical-align: middle; }
        .bank td.k { letter-spacing: 0.3px; }
        .bank td.v { word-wrap: break-word; }

        .signoff { margin-top: 14px; }
        .signoff td { font-weight: bold; line-height: 1.5; }

        .brands { margin-top: 18px; border-top: 1.5px solid #333; padding-top: 8px; text-align: center; }
        .brands .item { display: inline-block; margin: 4px 10px; vertical-align: middle; }
        .brands img { max-height: 34px; max-width: 110px; }
        .brands .name { font-weight: bold; font-size: 10px; color: #1f3f99; }
    </style>
</head>
<body>
    <div class="letterhead">
        @if ($logo)
            <img src="{{ $logo }}" alt="{{ $companyName }}">
        @else
            <div class="brand">{{ $companyName }}</div>
        @endif
        @if ($company['email'] ?? null)
            <div class="link">{{ $company['email'] }}</div>
        @endif
        @if ($company['website'] ?? null)
            <div class="link">{{ $company['website'] }}</div>
        @endif
    </div>
    <div class="page-number"></div>

    <div class="title">Quotation</div>

    <table class="party">
        <tr>
            <td style="width: 60%; padding-right: 20px;">
                <div class="name">M/s {{ $buyerName ?? '—' }}</div>
                @foreach ($buyerLines as $line)
                    <div>{{ $line }}</div>
                @endforeach
                @if ($buyerState)
                    <div>{{ $buyerState }}, India</div>
                @endif
                @if ($buyerGstin)
                    <div><span class="bold">GSTIN:</span> {{ $buyerGstin }}</div>
                @endif

                <div style="padding-top: 10px;">
                    @if ($attention)
                        <div class="bold">{{ $attention->name }}</div>
                    @endif
                    @if ($attentionPhone)
                        <div><span class="bold">Mob:</span>{{ $attentionPhone }}</div>
                    @endif
                    @if ($attentionEmail)
                        <div><span class="bold">E-Mail:</span> {{ $attentionEmail }}</div>
                    @endif
                </div>
            </td>
            <td style="width: 40%;">
                <table class="keys">
                    <tr>
                        <td class="k" style="width: 38%;">Quotation Date</td>
                        <td>: {{ $date($quotation->quotation_date) }}</td>
                    </tr>
                    @if ($quotation->valid_until)
                        <tr>
                            <td class="k">Valid Until</td>
                            <td>: {{ $date($quotation->valid_until) }}</td>
                        </tr>
                    @endif
                    <tr>
                        <td class="k">Quotation No</td>
                        <td>: {{ $quotation->number }}@if ($quotation->version > 1) (Rev. {{ $quotation->version }})@endif</td>
                    </tr>
                    <tr><td colspan="2" style="height: 10px;"></td></tr>
                    @if ($quotation->project)
                        <tr>
                            <td class="k">Project Name</td>
                            <td>: {{ $quotation->project->name }}</td>
                        </tr>
                    @endif
                    @if ($sender)
                        <tr>
                            <td class="k">Sent By</td>
                            <td>: {{ $sender->name }}</td>
                        </tr>
                        <tr>
                            <td class="k">E-Mail</td>
                            <td>: {{ $sender->email }}</td>
                        </tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th style="width: 4%;">S.<br>No</th>
                <th style="width: 30%;">Product Name</th>
                <th style="width: 9%;">HSN<br>Code</th>
                <th style="width: 7%;">Qty</th>
                <th style="width: 7%;">UoM</th>
                <th style="width: 12%;">List Price</th>
                <th style="width: 7%;">Disc<br>(%)</th>
                <th style="width: 10%;">Price after<br>Disc.</th>
                <th style="width: 14%;">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($lines as $line)
                <tr>
                    <td class="center">{{ $line['sl'] }}</td>
                    <td>
                        <div class="product">{{ $line['description'] }}</div>
                        @if ($line['detail'])
                            <div class="detail">{!! nl2br(e($line['detail'])) !!}</div>
                        @endif
                    </td>
                    <td class="center">{{ $line['hsn'] }}</td>
                    <td class="center">{{ $invoice->quantity($line['quantity']) }}</td>
                    <td class="center">{{ strtoupper($line['unit']) }}</td>
                    <td class="right">{{ $money($line['rate']) }}</td>
                    <td class="center">{{ $invoice->percent($line['discount_percent']) }}%</td>
                    <td class="right">{{ $plain($line['net_rate']) }}</td>
                    <td class="right">{{ $money($line['net_amount']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td class="label">Sub-Total</td>
            <td class="value">{{ $money($invoice->subtotal()) }}</td>
        </tr>
        <tr>
            <td class="label">Total Discount</td>
            <td class="value">{{ $money($invoice->discount()) }}</td>
        </tr>
        <tr>
            <td class="label"><span class="shade">Pre -Tax Sub-Total</span></td>
            <td class="value"><span class="shade">{{ $money($invoice->taxableValue()) }}</span></td>
        </tr>
        <tr>
            <td class="label">Tax Amount</td>
            <td class="value">{{ $money($invoice->taxTotal()) }}</td>
        </tr>
        @if (abs($roundOff) >= 0.01)
            <tr>
                <td class="label">Round Off</td>
                <td class="value">{{ $roundOff < 0 ? '(-) ' : '' }}{{ $money(abs($roundOff)) }}</td>
            </tr>
        @endif
        <tr>
            <td class="label">Grand Total</td>
            <td class="value">{{ $money($invoice->total()) }}</td>
        </tr>
    </table>

    @if ($terms !== [])
        <div class="band">Terms and Conditions</div>
        <table class="terms">
            @foreach ($terms as $term)
                <tr>
                    <td>{{ $loop->iteration }}. {{ $term }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    @if ($quotation->notes)
        <div style="padding-top: 8px;">
            <span class="bold">Notes:</span> {!! nl2br(e($quotation->notes)) !!}
        </div>
    @endif

    <div style="page-break-inside: avoid;">
        @if ($bank['name'] ?? null)
            <div class="band">Bank Details</div>
            <table class="bank">
                <tr>
                    <td class="k">Name</td>
                    <td class="v">{{ $companyName }}</td>
                    <td class="k">BANK NAME</td>
                    <td class="v">{{ $bank['name'] }}</td>
                </tr>
                <tr>
                    <td class="k">RTGS/NEFT IFSC</td>
                    <td class="v">{{ $bank['ifsc'] ?? '' }}</td>
                    <td class="k">AC/No</td>
                    <td class="v">{{ $bank['account'] ?? '' }}</td>
                </tr>
                <tr>
                    <td class="k">Bank Details</td>
                    <td class="v">{{ $bank['branch'] ?? '' }}</td>
                    <td class="k">GST No</td>
                    <td class="v">{{ $company['gstin'] ?? '' }}</td>
                </tr>
            </table>
        @endif

        <table class="signoff">
            <tr>
                <td style="width: 50%;">
                    @if ($sender)
                        Prepared By,<br>
                        {{ $sender->name }}<br>
                        {{ $sender->email }}
                    @endif
                </td>
                <td class="right" style="width: 50%;">
                    {{ $companyName }}<br>
                    @foreach ((array) ($company['address'] ?? []) as $line)
                        {{ $line }}<br>
                    @endforeach
                    @if (($company['phone'] ?? null) || ($company['mobile'] ?? null))
                        Phone : {{ implode(', ', array_filter([$company['phone'] ?? null, $company['mobile'] ?? null])) }}<br>
                    @endif
                    @if ($company['website'] ?? null)
                        {{ $company['website'] }}
                    @endif
                </td>
            </tr>
        </table>
    </div>

    @if ($brands !== [])
        <div class="brands" style="page-break-inside: avoid;">
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
</body>
</html>
