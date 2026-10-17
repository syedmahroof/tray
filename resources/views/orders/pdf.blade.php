@php
    /** @var \App\Models\Order $order */
    /** @var array<string, mixed> $company */
    /** @var string|null $logo */
    /** @var string|null $letterhead */

    $isSale = $order->type === 'sale';
    $docTitle = $isSale ? 'SALES ORDER' : 'PURCHASE ORDER';

    // The other side of the order: who we sell to, or who we buy from.
    $party = $isSale ? $order->customer : $order->supplier;
    $partyLines = preg_split('/\r\n|\r|\n/', (string) $party?->address, -1, PREG_SPLIT_NO_EMPTY);
    $partyState = $isSale ? $order->customer?->state?->name : null;
    $partyGstin = $order->gstin ?: $party?->gst_number;
    $partyContact = $isSale ? null : $order->supplier?->contact_person;

    $sender = $order->creator;
    $terms = preg_split('/\r\n|\r|\n/', (string) $order->terms, -1, PREG_SPLIT_NO_EMPTY);
    $terms = array_map(fn (string $term): string => preg_replace('/^\s*\d+[.)]\s*/', '', $term), $terms);

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
    $number = fn (float $value): string => rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    $date = fn (?\Carbon\CarbonInterface $value): string => $value?->format('d M Y') ?? '';
    $taxable = (float) $order->subtotal - (float) $order->discount;
    $inWords = \App\Support\OrderDocument::amountInWords((float) $order->total);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $order->number }}</title>
    <style>
        @page { margin: {{ $letterhead ? '140px' : '128px' }} 36px 64px; }
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

        .footer { position: fixed; bottom: -40px; left: 0; right: 0; height: 20px; font-size: 7.5px; color: #6b7280; border-top: 1px solid #d8dde5; padding-top: 5px; }
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

        .signoff { margin-top: 40px; page-break-inside: avoid; }
        .signoff .line { border-top: 1px solid #1f2933; margin-top: 38px; padding-top: 3px; }
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

    <table class="footer">
        <tr>
            <td style="width: 70%;">{{ $companyName }} &middot; {{ ucwords(strtolower($docTitle)) }} {{ $order->number }}</td>
            <td class="right page" style="width: 30%;"></td>
        </tr>
    </table>

    <table>
        <tr>
            <td style="width: 50%; vertical-align: bottom;">
                <div class="doc-title">{{ $docTitle }}</div>
            </td>
            <td style="width: 50%;">
                <table class="meta">
                    <tr>
                        <td class="k">Order No</td>
                        <td class="v">{{ $order->number }}</td>
                    </tr>
                    <tr>
                        <td class="k">Order Date</td>
                        <td class="v">{{ $date($order->order_date) }}</td>
                    </tr>
                    @if ($order->expected_date)
                        <tr>
                            <td class="k">{{ $isSale ? 'Delivery By' : 'Expected By' }}</td>
                            <td class="v">{{ $date($order->expected_date) }}</td>
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
                    <div class="label">{{ $isSale ? 'Bill To' : 'Supplier' }}</div>
                    <div class="body">
                        <div class="name">M/s {{ $party?->name ?? '—' }}</div>
                        @foreach ($partyLines as $line)
                            <div>{{ $line }}</div>
                        @endforeach
                        @if ($partyState)
                            <div>{{ $partyState }}, India</div>
                        @endif
                        @if ($partyGstin)
                            <div style="margin-top: 3px;"><span class="muted">GSTIN:</span> <span class="strong">{{ $partyGstin }}</span></div>
                        @endif
                        @if ($partyContact || $party?->phone || $party?->email)
                            <div style="margin-top: 5px;">
                                @if ($partyContact)
                                    <span class="muted">Kind Attn:</span> <span class="strong">{{ $partyContact }}</span><br>
                                @endif
                                @if ($party?->phone)
                                    <span class="muted">Phone:</span> {{ $party->phone }}<br>
                                @endif
                                @if ($party?->email)
                                    <span class="muted">Email:</span> {{ $party->email }}
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            </td>
            <td style="width: 45%; padding-left: 8px;">
                <div class="box">
                    <div class="label">Order Details</div>
                    <div class="body">
                        <table class="kv">
                            @if ($sender)
                                <tr>
                                    <td class="k">{{ $isSale ? 'Sales Contact' : 'Raised By' }}</td>
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
                                <td class="k">Supply</td>
                                <td class="v">{{ $order->supply_type === 'inter' ? 'Inter-state' : 'Intra-state' }}</td>
                            </tr>
                            <tr>
                                <td class="k">Items</td>
                                <td class="v">{{ $order->items->count() }}</td>
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
                <th class="left" style="width: 34%;">Description</th>
                <th style="width: 9%;">HSN Code</th>
                <th style="width: 7%;">Qty</th>
                <th style="width: 6%;">UoM</th>
                <th style="width: 11%;">Rate</th>
                <th style="width: 13%;">Taxable</th>
                <th style="width: 6%;">GST</th>
                <th style="width: 10%;">Tax</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($order->items as $item)
                <tr @class(['alt' => $loop->even])>
                    <td class="center muted">{{ $loop->iteration }}</td>
                    <td><div class="product">{{ $item->description }}</div></td>
                    <td class="center">{{ $item->hsn_code }}</td>
                    <td class="center">{{ $number((float) $item->quantity) }}</td>
                    <td class="center">{{ strtoupper((string) $item->product?->unit) }}</td>
                    <td class="right">{{ $plain((float) $item->unit_price) }}</td>
                    <td class="right strong">{{ $plain((float) $item->quantity * (float) $item->unit_price) }}</td>
                    <td class="center">{{ $number((float) $item->tax_percentage) }}%</td>
                    <td class="right">{{ $plain((float) $item->tax_amount) }}</td>
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

                @if ($isSale && ($bank['name'] ?? null))
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
                        <td class="v">{{ $money((float) $order->subtotal) }}</td>
                    </tr>
                    @if ((float) $order->discount > 0)
                        <tr>
                            <td class="k">Discount</td>
                            <td class="v">(-) {{ $money((float) $order->discount) }}</td>
                        </tr>
                    @endif
                    <tr class="sub">
                        <td class="k strong">Taxable Value</td>
                        <td class="v">{{ $money($taxable) }}</td>
                    </tr>
                    @if ($order->supply_type === 'inter')
                        <tr>
                            <td class="k">IGST</td>
                            <td class="v">{{ $money((float) $order->igst_amount) }}</td>
                        </tr>
                    @else
                        <tr>
                            <td class="k">CGST</td>
                            <td class="v">{{ $money((float) $order->cgst_amount) }}</td>
                        </tr>
                        <tr>
                            <td class="k">SGST</td>
                            <td class="v">{{ $money((float) $order->sgst_amount) }}</td>
                        </tr>
                    @endif
                    <tr class="grand">
                        <td>Grand Total</td>
                        <td class="right">{{ $money((float) $order->total) }}</td>
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

    @if ($order->notes)
        <div class="section">
            <div class="heading">Notes</div>
            <div>{!! nl2br(e($order->notes)) !!}</div>
        </div>
    @endif

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
