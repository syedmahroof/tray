<x-mail::message>
# Quotation {{ $quotation->number }}

Dear {{ $quotation->contact?->name ?? 'Customer' }},

Please find attached our quotation **{{ $quotation->number }}** dated {{ $quotation->quotation_date?->format('d M Y') }}.

<x-mail::panel>
**Total: {{ number_format((float) $quotation->total, 2) }}**
@if ($quotation->valid_until)
<br>Valid until: {{ $quotation->valid_until->format('d M Y') }}
@endif
</x-mail::panel>

@if ($quotation->notes)
{{ $quotation->notes }}
@endif

Thank you for your business.

Regards,<br>
{{ $quotation->branch->name ?? config('app.name') }}
</x-mail::message>
