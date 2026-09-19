@php
    /** @var \App\Models\Quotation $quotation */
    /** @var \App\Support\QuotationInvoice $invoice */
    /** @var array<string, mixed> $company */

    $buyer = $quotation->customer;
    $contact = $quotation->contact;
    $buyerName = $buyer?->name ?? $contact?->name;
    $buyerAddress = $buyer?->address ?? $contact?->address;
    $buyerPhone = $buyer?->phone ?? $contact?->phone;
    $buyerGstin = $quotation->gstin ?: $buyer?->gst_number;
    $buyerState = $buyer?->state?->name ?? $contact?->state?->name;
    $buyerStateCode = $buyer?->state?->code ?? $contact?->state?->code;
    $buyerLines = preg_split('/\r\n|\r|\n/', (string) $buyerAddress, -1, PREG_SPLIT_NO_EMPTY);

    $lines = $invoice->lines();
    $taxLines = $invoice->taxLines();
    $summary = $invoice->hsnSummary();
    $roundOff = $invoice->roundOff();
    $hasTaxBlock = $invoice->discount() > 0 || $taxLines !== [] || abs($roundOff) >= 0.01;

    $money = fn (float $value): string => number_format($value, 2);
    $date = fn (?\Carbon\CarbonInterface $value): string => $value?->format('j-M-y') ?? '';

    // The ruled block is held open so a short invoice still fills its page, and
    // gives that space back line by line as the invoice grows.
    $fillerHeight = max(24, 300 - count($lines) * 17 - count($taxLines) * 15);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $quotation->number }}</title>
    <style>
        @page { margin: 18px 20px; }
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 8.5px; line-height: 1.35; color: #000; margin: 0; }
        table { width: 100%; border-collapse: collapse; }
        td, th { vertical-align: top; }

        .doc-title { text-align: center; font-size: 12px; font-weight: bold; padding-bottom: 7px; }
        .frame { border: 1px solid #000; }
        .frame > tbody > tr > td { border: 1px solid #000; padding: 4px 6px; }
        .bare, .bare > tbody > tr > td { border: 0; padding: 0; }
        .bare > tbody > tr > td.rule { border-top: 1px solid #000; }

        .label { font-size: 7.5px; }
        .value { font-weight: bold; font-size: 9px; padding-top: 1px; }
        .party-name { font-size: 11px; font-weight: bold; padding-bottom: 1px; }
        .tiny { font-size: 7.5px; }
        .right { text-align: right; }
        .center { text-align: center; }
        .bold { font-weight: bold; }
        .italic { font-style: italic; }
        .rule { border-top: 1px solid #000; }

        /* A colon column, so party and bank details line up the way Tally sets them. */
        .keys > tbody > tr > td { border: 0; padding: 0 0 1px; }
        .keys .k { width: 76px; }
        .keys.wide .k { width: 96px; }

        .items th { border: 1px solid #000; padding: 5px 6px; font-weight: normal; }
        .items td { border-left: 1px solid #000; border-right: 1px solid #000; padding: 2px 6px; }
        .items tr.first td { padding-top: 5px; }
        .items tr.filler td { border-bottom: 0; }
        .items tr.sum td { padding-top: 3px; }
        .items tr.total td { border-top: 1px solid #000; border-bottom: 1px solid #000; font-weight: bold; padding: 4px 6px; }

        .band { border: 1px solid #000; border-top: 0; }
        .band > tbody > tr > td { padding: 4px 6px; }

        .summary th, .summary td { border: 1px solid #000; padding: 3px 6px; font-weight: normal; }
        .summary th { font-size: 8px; }
        .summary tr.total td { font-weight: bold; }

        .footer { text-align: center; padding-top: 8px; font-size: 8px; }
    </style>
</head>
<body>
    <div class="doc-title">{{ $company['quotation_title'] ?? 'QUOTATION' }}</div>

    <table class="frame">
        <tr>
            {{-- The seller and the buyer stack up alongside the document's own details. --}}
            <td style="width: 52%; padding: 5px 6px 6px;">
                <div class="party-name">{{ $company['name'] ?? config('app.name') }}</div>
                @foreach ((array) ($company['address'] ?? []) as $line)
                    <div class="tiny">{{ $line }}</div>
                @endforeach
                @if (($company['phone'] ?? null) || ($company['mobile'] ?? null))
                    <div class="tiny">PH: {{ $company['phone'] }}@if ($company['mobile'] ?? null),Mob:{{ $company['mobile'] }}@endif</div>
                @endif
                @if ($company['udyam'] ?? null)
                    <div class="tiny">UDYAM : {{ $company['udyam'] }}</div>
                @endif
                @if ($company['gstin'] ?? null)
                    <div class="tiny">GSTIN/UIN: {{ $company['gstin'] }}</div>
                @endif
                @if ($company['state']['name'] ?? null)
                    <div class="tiny">State Name : {{ $company['state']['name'] }}@if ($company['state']['code'] ?? null), Code : {{ $company['state']['code'] }}@endif</div>
                @endif
                @if ($company['email'] ?? null)
                    <div class="tiny">E-Mail : {{ $company['email'] }}</div>
                @endif
            </td>

            <td rowspan="2" style="width: 48%; padding: 0;">
                <table class="frame" style="border: 0;">
                    @foreach ([
                        ['Invoice No.', $quotation->number, 'Dated', $date($quotation->quotation_date)],
                        ['Delivery Note', '', 'Mode/Terms of Payment', ''],
                        ['Reference No. & Date.', $quotation->enquiry_id ? $quotation->enquiry_id.' dt. '.$date($quotation->quotation_date) : '', 'Other References', $quotation->version > 1 ? 'Rev. '.$quotation->version : ''],
                        ["Buyer's Order No.", '', 'Dated', ''],
                        ['Dispatch Doc No.', '', 'Delivery Note Date', ''],
                        ['Dispatched through', '', 'Destination', ''],
                    ] as [$leftLabel, $leftValue, $rightLabel, $rightValue])
                        <tr>
                            <td style="width: 50%; height: 21px; border-left: 0; @if ($loop->first) border-top: 0; @endif">
                                <div class="label">{{ $leftLabel }}</div>
                                <div class="value">{{ $leftValue }}&nbsp;</div>
                            </td>
                            <td style="width: 50%; border-right: 0; @if ($loop->first) border-top: 0; @endif">
                                <div class="label">{{ $rightLabel }}</div>
                                <div class="value">{{ $rightValue }}&nbsp;</div>
                            </td>
                        </tr>
                    @endforeach
                    <tr>
                        <td colspan="2" style="height: 52px; border-left: 0; border-right: 0; border-bottom: 0;">
                            <div class="label">Terms of Delivery</div>
                            @if ($quotation->valid_until)
                                <div class="tiny" style="padding-top: 2px;">Valid until {{ $date($quotation->valid_until) }}</div>
                            @endif
                            @if ($quotation->terms)
                                <div class="tiny">{!! nl2br(e($quotation->terms)) !!}</div>
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td style="padding: 4px 6px 6px;">
                <div class="label">Buyer (Bill to)</div>
                <div class="party-name">{{ $buyerName ?? '—' }}</div>
                @foreach ($buyerLines as $line)
                    <div class="tiny">{{ $line }}</div>
                @endforeach
                @if ($buyerPhone)
                    <div class="bold" style="padding-top: 2px;">Phone : {{ $buyerPhone }}</div>
                @endif
                <table class="keys" style="margin-top: 2px;">
                    @if ($buyerGstin)
                        <tr>
                            <td class="k">GSTIN/UIN</td>
                            <td>: {{ $buyerGstin }}</td>
                        </tr>
                    @endif
                    @if ($buyerState)
                        <tr>
                            <td class="k">State Name</td>
                            <td>: {{ $buyerState }}@if ($buyerStateCode), Code : {{ $buyerStateCode }}@endif</td>
                        </tr>
                    @endif
                    @if ($quotation->project)
                        <tr>
                            <td class="k">Project</td>
                            <td>: {{ $quotation->project->name }}</td>
                        </tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    <table class="items" style="margin-top: -1px;">
        <thead>
            <tr>
                <th style="width: 5%;" class="center">Sl<br>No.</th>
                <th style="width: 40%;" class="center">Description of Goods</th>
                <th style="width: 11%;" class="center">HSN/SAC</th>
                <th style="width: 12%;" class="center">Quantity</th>
                <th style="width: 11%;" class="center">Rate</th>
                <th style="width: 6%;" class="center">per</th>
                <th style="width: 15%;" class="center">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($lines as $line)
                <tr @class(['first' => $loop->first])>
                    <td class="center">{{ $line['sl'] }}</td>
                    <td>{{ $line['description'] }}</td>
                    <td class="center">{{ $line['hsn'] }}</td>
                    <td class="right">{{ $invoice->quantity($line['quantity']) }} {{ $line['unit'] }}</td>
                    <td class="right">{{ $money($line['rate']) }}</td>
                    <td class="center">{{ $line['unit'] }}</td>
                    <td class="right">{{ $money($line['amount']) }}</td>
                </tr>
            @endforeach

            @if ($hasTaxBlock)
                {{-- The lines are ruled off from the tax added on top of them. --}}
                <tr class="sum">
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td class="right rule">{{ $money($invoice->subtotal()) }}</td>
                </tr>
            @endif

            @if ($invoice->discount() > 0)
                <tr>
                    <td></td>
                    <td class="right bold italic">Less : Discount</td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td class="right bold">(-){{ $money($invoice->discount()) }}</td>
                </tr>
            @endif

            @foreach ($taxLines as $tax)
                <tr>
                    <td></td>
                    <td class="right bold italic">{{ $tax['label'] }}</td>
                    <td></td>
                    <td></td>
                    <td class="right italic">{{ $invoice->percent($tax['rate']) }}</td>
                    <td class="center">%</td>
                    <td class="right bold">{{ $money($tax['amount']) }}</td>
                </tr>
            @endforeach

            @if (abs($roundOff) >= 0.01)
                <tr>
                    <td></td>
                    <td style="padding: 2px 6px;">
                        <table class="bare">
                            <tr>
                                <td class="italic">Less :</td>
                                <td class="right bold italic">Round Off Sale</td>
                            </tr>
                        </table>
                    </td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td class="right bold">{{ $roundOff < 0 ? '(-)' : '' }}{{ $money(abs($roundOff)) }}</td>
                </tr>
            @endif

            {{-- Holds the ruled block open the way the printed book does. --}}
            <tr class="filler">
                <td style="height: {{ $fillerHeight }}px;"></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
            </tr>

            <tr class="total">
                <td></td>
                <td class="right">Total</td>
                <td></td>
                <td class="right">{{ $invoice->quantity($invoice->totalQuantity()) }} {{ $invoice->totalUnit() }}</td>
                <td></td>
                <td></td>
                <td class="right">₹ {{ $money($invoice->total()) }}</td>
            </tr>
        </tbody>
    </table>

    <table class="band">
        <tr>
            <td>
                <div class="label">Amount Chargeable (in words)</div>
                <div class="bold" style="font-size: 9.5px; padding-top: 1px;">{{ \App\Support\QuotationInvoice::inWords($invoice->total()) }}</div>
            </td>
            <td class="right italic" style="width: 22%; vertical-align: bottom;">E. &amp; O.E.</td>
        </tr>
    </table>

    <table class="summary" style="margin-top: -1px;">
        <thead>
            <tr>
                <th rowspan="2" style="width: 28%; vertical-align: middle;" class="center">HSN/SAC</th>
                <th rowspan="2" style="width: 16%; vertical-align: middle;" class="center">Taxable<br>Value</th>
                @if ($invoice->isInterState())
                    <th colspan="2" class="center">IGST</th>
                @else
                    <th colspan="2" class="center">CGST</th>
                    <th colspan="2" class="center">SGST/UTGST</th>
                @endif
                <th rowspan="2" style="width: 14%; vertical-align: middle;" class="center">Total<br>Tax Amount</th>
            </tr>
            <tr>
                <th class="center" style="width: 7%;">Rate</th>
                <th class="center" style="width: 14%;">Amount</th>
                @if (! $invoice->isInterState())
                    <th class="center" style="width: 7%;">Rate</th>
                    <th class="center" style="width: 14%;">Amount</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @foreach ($summary as $row)
                <tr>
                    <td>{{ $row['hsn'] }}</td>
                    <td class="right">{{ $money($row['taxable']) }}</td>
                    @if ($invoice->isInterState())
                        <td class="center">{{ $invoice->percent($row['rate']) }}%</td>
                        <td class="right">{{ $money($row['igst']) }}</td>
                    @else
                        <td class="center">{{ $invoice->percent($row['rate'] / 2) }}%</td>
                        <td class="right">{{ $money($row['cgst']) }}</td>
                        <td class="center">{{ $invoice->percent($row['rate'] / 2) }}%</td>
                        <td class="right">{{ $money($row['sgst']) }}</td>
                    @endif
                    <td class="right">{{ $money($row['tax']) }}</td>
                </tr>
            @endforeach
            <tr class="total">
                <td class="right">Total</td>
                <td class="right">{{ $money(array_sum(array_column($summary, 'taxable'))) }}</td>
                @if ($invoice->isInterState())
                    <td></td>
                    <td class="right">{{ $money(array_sum(array_column($summary, 'igst'))) }}</td>
                @else
                    <td></td>
                    <td class="right">{{ $money(array_sum(array_column($summary, 'cgst'))) }}</td>
                    <td></td>
                    <td class="right">{{ $money(array_sum(array_column($summary, 'sgst'))) }}</td>
                @endif
                <td class="right">{{ $money(array_sum(array_column($summary, 'tax'))) }}</td>
            </tr>
        </tbody>
    </table>

    <table class="band" style="margin-top: -1px;">
        <tr>
            <td>
                <span class="label">Tax Amount (in words) :</span>
                <span class="bold" style="font-size: 9.5px;">&nbsp;&nbsp;{{ \App\Support\QuotationInvoice::inWords($invoice->taxTotal()) }}</span>
            </td>
        </tr>
    </table>

    {{-- The bank details and the signature run down the right of the declaration. --}}
    <table class="band" style="margin-top: -1px;">
        <tr>
            <td style="width: 52%; padding: 0; border-right: 1px solid #000;">
                <div style="padding: 4px 6px; height: 44px;">
                    @if ($quotation->notes)
                        <div class="label">Notes</div>
                        <div class="tiny">{!! nl2br(e($quotation->notes)) !!}</div>
                    @endif
                </div>
                <div style="border-top: 1px solid #000; padding: 4px 6px; height: 42px;">
                    <div class="bold" style="text-decoration: underline;">Declaration</div>
                    <div class="tiny">{{ $company['declaration'] ?? '' }}</div>
                </div>
            </td>
            <td style="width: 48%; padding: 4px 6px;">
                @if ($company['bank']['name'] ?? null)
                    <div class="bold">Company's Bank Details</div>
                    <table class="keys wide" style="margin-top: 2px;">
                        <tr>
                            <td class="k">Bank Name</td>
                            <td class="bold">: {{ $company['bank']['name'] }}</td>
                        </tr>
                        @if ($company['bank']['account'] ?? null)
                            <tr>
                                <td class="k">A/c No.</td>
                                <td class="bold">: {{ $company['bank']['account'] }}</td>
                            </tr>
                        @endif
                        @if ($company['bank']['branch_ifsc'] ?? null)
                            <tr>
                                <td class="k">Branch &amp; IFS Code</td>
                                <td class="bold">: {{ $company['bank']['branch_ifsc'] }}</td>
                            </tr>
                        @endif
                    </table>
                @endif
                <div class="bold right" style="padding-top: 12px;">for {{ $company['name'] ?? config('app.name') }}</div>
                <div class="right" style="padding-top: 24px;">Authorised Signatory</div>
            </td>
        </tr>
    </table>

    <div class="footer">{{ $company['footer'] ?? 'This is a Computer Generated Invoice' }}</div>
</body>
</html>
