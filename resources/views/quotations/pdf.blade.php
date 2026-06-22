<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Quotation {{ $quotation->number }}</title>
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 12px; color: #1f2937; margin: 0; }
        .header { display: flex; justify-content: space-between; border-bottom: 2px solid #4f46e5; padding-bottom: 12px; }
        .brand { font-size: 22px; font-weight: bold; color: #4f46e5; }
        .muted { color: #6b7280; }
        .title { font-size: 18px; font-weight: bold; text-align: right; }
        table { width: 100%; border-collapse: collapse; }
        .meta td { padding: 2px 0; vertical-align: top; }
        .items { margin-top: 18px; }
        .items th { background: #f3f4f6; text-align: left; padding: 8px; font-size: 11px; text-transform: uppercase; color: #6b7280; border-bottom: 1px solid #e5e7eb; }
        .items td { padding: 8px; border-bottom: 1px solid #f3f4f6; }
        .right { text-align: right; }
        .totals { margin-top: 12px; width: 40%; float: right; }
        .totals td { padding: 4px 8px; }
        .totals .grand { border-top: 2px solid #4f46e5; font-weight: bold; font-size: 14px; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 999px; background: #eef2ff; color: #4f46e5; font-size: 11px; text-transform: capitalize; }
        .section { margin-top: 14px; }
        .section-title { font-weight: bold; margin-bottom: 4px; }
        .clearfix::after { content: ""; display: table; clear: both; }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <div class="brand">{{ $quotation->branch->name ?? config('app.name') }}</div>
            <div class="muted">Quotation</div>
        </div>
        <div>
            <div class="title">{{ $quotation->number }}</div>
            <div class="muted right">
                Date: {{ $quotation->quotation_date?->format('d M Y') }}<br>
                @if ($quotation->valid_until)
                    Valid until: {{ $quotation->valid_until->format('d M Y') }}<br>
                @endif
                <span class="badge">{{ $quotation->status }}</span>
            </div>
        </div>
    </div>

    <table class="meta" style="margin-top: 16px;">
        <tr>
            <td style="width: 50%;">
                <div class="section-title">Quotation For</div>
                <div>{{ $quotation->contact->name ?? '—' }}</div>
                @if ($quotation->contact?->phone)
                    <div class="muted">{{ $quotation->contact->phone }}</div>
                @endif
                @if ($quotation->contact?->email)
                    <div class="muted">{{ $quotation->contact->email }}</div>
                @endif
            </td>
            <td style="width: 50%;">
                @if ($quotation->project)
                    <div class="section-title">Project</div>
                    <div>{{ $quotation->project->name }}</div>
                @endif
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th style="width: 5%;">#</th>
                <th style="width: 45%;">Description</th>
                <th class="right" style="width: 15%;">Qty</th>
                <th class="right" style="width: 17%;">Unit Price</th>
                <th class="right" style="width: 18%;">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($quotation->items as $index => $item)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>
                        {{ $item->description }}
                        @if ($item->product)
                            <div class="muted">{{ $item->product->name }}</div>
                        @endif
                    </td>
                    <td class="right">{{ rtrim(rtrim(number_format((float) $item->quantity, 2), '0'), '.') }}</td>
                    <td class="right">{{ number_format((float) $item->unit_price, 2) }}</td>
                    <td class="right">{{ number_format((float) $item->quantity * (float) $item->unit_price, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="clearfix">
        <table class="totals">
            <tr>
                <td>Subtotal</td>
                <td class="right">{{ number_format((float) $quotation->subtotal, 2) }}</td>
            </tr>
            @if ((float) $quotation->discount > 0)
                <tr>
                    <td>Discount</td>
                    <td class="right">- {{ number_format((float) $quotation->discount, 2) }}</td>
                </tr>
            @endif
            @if ((float) $quotation->tax_percent > 0)
                <tr>
                    <td>Tax ({{ rtrim(rtrim(number_format((float) $quotation->tax_percent, 2), '0'), '.') }}%)</td>
                    <td class="right">{{ number_format((float) $quotation->tax_amount, 2) }}</td>
                </tr>
            @endif
            <tr class="grand">
                <td>Total</td>
                <td class="right">{{ number_format((float) $quotation->total, 2) }}</td>
            </tr>
        </table>
    </div>

    @if ($quotation->notes)
        <div class="section">
            <div class="section-title">Notes</div>
            <div class="muted">{!! nl2br(e($quotation->notes)) !!}</div>
        </div>
    @endif

    @if ($quotation->terms)
        <div class="section">
            <div class="section-title">Terms &amp; Conditions</div>
            <div class="muted">{!! nl2br(e($quotation->terms)) !!}</div>
        </div>
    @endif
</body>
</html>
