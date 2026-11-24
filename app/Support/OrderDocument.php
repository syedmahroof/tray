<?php

namespace App\Support;

use App\Models\Order;
use App\Models\OrderItem;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfDocument;
use Illuminate\Support\Facades\Storage;

/**
 * A sales or purchase order as a printable page, laid out the same way as a
 * quotation and set on the raising branch's letterhead. The download and the
 * print view are both built from here.
 */
class OrderDocument
{
    /**
     * Everything the document is made of.
     *
     * @param  string  $host  the request host, which supplies company details a branch does not keep
     * @return array{order: Order, company: array<string, mixed>, logo: string|null, background: string|null, brands: list<array{name: string, logo: string|null}>, lines: list<array{sl: int, description: string, hsn: string|null, quantity: float, unit: string, net_rate: float, net_amount: float}>, details: list<array{label: string|null, value: string}>}
     */
    public static function for(Order $order, string $host): array
    {
        $order->loadMissing(['customer.state', 'supplier', 'branch.brands', 'creator', 'items.product']);

        return [
            'order' => $order,
            'company' => [
                ...Branding::companyForHost($host),
                ...$order->branch->letterhead(withCity: false),
            ],
            'logo' => self::storedFile($order->branch->logo_path),
            'background' => self::backgroundPath($order),
            'brands' => QuotationDocument::brandsFor($order->branch),
            'lines' => self::lines($order),
            'details' => self::details($order),
        ];
    }

    /**
     * Render the printable page. As on a quotation, the items box is stretched
     * down a short order's page; should that stretch tip the order onto
     * another page, it is set again without it.
     */
    public static function render(Order $order, string $host): PdfDocument
    {
        $data = self::for($order, $host);
        $html = view('orders.pdf', $data)->render();

        if (str_contains($html, 'class="filler"') && ($pages = self::pageCount($html)) > 1) {
            $unstretched = view('orders.pdf', [...$data, 'fillItems' => false])->render();

            if (self::pageCount($unstretched) < $pages) {
                $html = $unstretched;
            }
        }

        return Pdf::loadHTML($html);
    }

    /**
     * The full-page background a purchase order is printed on, when its
     * branch keeps one. It is never borrowed from the quotation page, and
     * sales orders print on the plain letterhead.
     */
    public static function backgroundPath(Order $order): ?string
    {
        return $order->type === 'purchase' ? self::storedFile($order->branch->purchase_order_background_path) : null;
    }

    /**
     * The printed lines. As on a quotation, each line's price already carries
     * its share of the order discount, so the lines add up to the sub total.
     *
     * @return list<array{sl: int, description: string, hsn: string|null, quantity: float, unit: string, net_rate: float, net_amount: float}>
     */
    public static function lines(Order $order): array
    {
        $subtotal = (float) $order->subtotal;
        $share = $subtotal > 0 ? 1 - (float) $order->discount / $subtotal : 1.0;

        return array_values($order->items->values()->map(fn (OrderItem $item, int $index): array => [
            'sl' => $index + 1,
            'description' => $item->description ?: (string) $item->product?->name,
            'hsn' => $item->hsn_code,
            'quantity' => (float) $item->quantity,
            'unit' => $item->product?->unit ?: 'nos',
            'net_rate' => round((float) $item->unit_price * $share, 2),
            'net_amount' => round((float) $item->quantity * (float) $item->unit_price * $share, 2),
        ])->all());
    }

    /**
     * The lines printed in the order's details box, each a label and value.
     *
     * @return list<array{label: string|null, value: string}>
     */
    public static function details(Order $order): array
    {
        $sender = $order->creator;

        return array_values(array_filter([
            $sender ? ['label' => $order->type === 'sale' ? 'Sales Contact' : 'Raised By', 'value' => $sender->name] : null,
            filled($sender?->email) ? ['label' => 'Email', 'value' => $sender->email] : null,
            filled($order->branch->gstin) ? ['label' => 'GSTIN', 'value' => $order->branch->gstin] : null,
            $order->expected_date ? ['label' => $order->type === 'sale' ? 'Delivery By' : 'Expected By', 'value' => $order->expected_date->format('d M Y')] : null,
        ]));
    }

    /**
     * The tax lines the order carries, each with the rate the amount works
     * out to on the taxable value.
     *
     * @return list<array{label: string, rate: float, amount: float}>
     */
    public static function taxLines(Order $order): array
    {
        $taxable = (float) $order->subtotal - (float) $order->discount;
        $rate = fn (float $amount): float => $taxable > 0 ? round($amount / $taxable * 100, 2) : 0.0;
        $amounts = $order->supply_type === 'inter'
            ? ['IGST' => (float) $order->igst_amount]
            : ['CGST' => (float) $order->cgst_amount, 'SGST' => (float) $order->sgst_amount];

        $lines = [];

        foreach ($amounts as $label => $amount) {
            if ($amount > 0) {
                $lines[] = ['label' => $label, 'rate' => $rate($amount), 'amount' => $amount];
            }
        }

        return $lines;
    }

    /**
     * The total spelt out, rupees and paise, as printed under the items.
     */
    public static function amountInWords(float $amount): string
    {
        $speller = new \NumberFormatter('en_IN', \NumberFormatter::SPELLOUT);
        $rupees = (int) floor($amount);
        $paise = (int) round(($amount - $rupees) * 100);
        $spell = fn (int $value): string => ucwords(str_replace('-', ' ', (string) $speller->format($value)));

        return 'Rupees '.$spell($rupees).($paise > 0 ? ' and '.$spell($paise).' Paise' : '').' Only';
    }

    /**
     * The number of pages the page markup runs to once set.
     */
    private static function pageCount(string $html): int
    {
        $pdf = Pdf::loadHTML($html);
        $pdf->render();

        return $pdf->getDomPDF()->getCanvas()->get_page_count();
    }

    /**
     * The on-disk path of a stored image, which is how the PDF renderer reads
     * images, or null when it is missing.
     */
    private static function storedFile(?string $path): ?string
    {
        $disk = Storage::disk(StoredImage::DISK);

        return filled($path) && $disk->exists($path) ? $disk->path($path) : null;
    }
}
