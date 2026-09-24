<?php

namespace App\Support;

use App\Models\Brand;
use App\Models\Quotation;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfDocument;
use Illuminate\Support\Facades\Storage;

/**
 * The quotation as a document: the figures, the company identity behind them
 * and the page they are set on. The download, the print view, the share link,
 * the emailed attachment and the on-screen view are all built from here, so
 * they can never drift apart.
 */
class QuotationDocument
{
    /**
     * Everything the document is made of.
     *
     * @param  string  $host  the request host, which decides whose company
     *                        identity the document carries
     * @return array{quotation: Quotation, invoice: QuotationInvoice, company: array<string, mixed>, logo: string|null, brands: list<array{name: string, logo: string|null}>}
     */
    public static function for(Quotation $quotation, string $host): array
    {
        $quotation->loadMissing(['customer.state', 'contact.state', 'project', 'branch.brands', 'creator', 'items.product']);

        return [
            'quotation' => $quotation,
            'invoice' => new QuotationInvoice($quotation),
            'company' => self::company($quotation, $host),
            'logo' => self::logoPath($quotation, $host),
            'brands' => self::brands($quotation),
        ];
    }

    /**
     * The brands the raising branch deals in, shown under the footer, each
     * with its logo as a file on disk when it has one.
     *
     * @return list<array{name: string, logo: string|null}>
     */
    public static function brands(Quotation $quotation): array
    {
        $disk = Storage::disk(StoredImage::DISK);

        return array_values(($quotation->branch?->brands ?? collect())
            ->filter(fn (Brand $brand): bool => $brand->is_active)
            ->sortBy('name')
            ->map(fn (Brand $brand): array => [
                'name' => $brand->name,
                'logo' => filled($brand->logo_path) && $disk->exists($brand->logo_path) ? $disk->path($brand->logo_path) : null,
            ])
            ->all());
    }

    /**
     * The logo as a file on disk, which is how the PDF renderer reads images.
     * A quotation raised by a branch carries only that branch's logo; the
     * brand's is used only when there is no branch behind it.
     */
    public static function logoPath(Quotation $quotation, string $host): ?string
    {
        if ($quotation->branch !== null) {
            $branchLogo = $quotation->branch->logo_path;

            return filled($branchLogo) && Storage::disk(StoredImage::DISK)->exists($branchLogo)
                ? Storage::disk(StoredImage::DISK)->path($branchLogo)
                : null;
        }

        $logo = Branding::forHost($host)['logo'];

        if (blank($logo)) {
            return null;
        }

        $path = public_path(ltrim($logo, '/'));

        return is_file($path) ? $path : null;
    }

    /**
     * The company identity printed on this quotation: the raising branch's
     * letterhead, with the brand behind the host supplying only what a
     * branch does not keep (its state, declaration and the like).
     *
     * @return array<string, mixed>
     */
    public static function company(Quotation $quotation, string $host): array
    {
        return [
            ...Branding::companyForHost($host),
            ...($quotation->branch?->letterhead() ?? []),
        ];
    }

    /**
     * The terms the quotation is printed with: its own, or the raising
     * branch's standard terms when it has none.
     */
    public static function terms(Quotation $quotation): ?string
    {
        return filled($quotation->terms) ? $quotation->terms : $quotation->branch?->quotation_terms;
    }

    /**
     * Render the printable page.
     */
    public static function render(Quotation $quotation, string $host): PdfDocument
    {
        return Pdf::loadView('quotations.pdf', self::for($quotation, $host));
    }
}
