<?php

namespace App\Support;

use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfDocument;
use Illuminate\Support\Facades\Storage;

/**
 * A sales or purchase order as a printable page, set on the raising branch's
 * letterhead. The download and the print view are both built from here.
 */
class OrderDocument
{
    /**
     * Everything the document is made of.
     *
     * @param  string  $host  the request host, which supplies company details a branch does not keep
     * @return array{order: Order, company: array<string, mixed>, logo: string|null, letterhead: string|null}
     */
    public static function for(Order $order, string $host): array
    {
        $order->loadMissing(['customer.state', 'supplier', 'branch', 'creator', 'items.product']);

        return [
            'order' => $order,
            'company' => [
                ...Branding::companyForHost($host),
                ...$order->branch->letterhead(),
            ],
            'logo' => self::storedFile($order->branch->logo_path),
            'letterhead' => self::storedFile($order->branch->letterhead_path),
        ];
    }

    /**
     * Render the printable page.
     */
    public static function render(Order $order, string $host): PdfDocument
    {
        return Pdf::loadView('orders.pdf', self::for($order, $host));
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
     * The on-disk path of a stored image, which is how the PDF renderer reads
     * images, or null when it is missing.
     */
    private static function storedFile(?string $path): ?string
    {
        $disk = Storage::disk(StoredImage::DISK);

        return filled($path) && $disk->exists($path) ? $disk->path($path) : null;
    }
}
