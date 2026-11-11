<?php

namespace App\Support;

use App\Models\Branch;
use App\Models\Brand;
use App\Models\Quotation;
use App\Models\User;
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
     * @return array{quotation: Quotation, invoice: QuotationInvoice, company: array<string, mixed>, logo: string|null, background: string|null, brands: list<array{name: string, logo: string|null}>}
     */
    public static function for(Quotation $quotation, string $host): array
    {
        $quotation->loadMissing(['customer.state', 'contact.state', 'project.state', 'builder.state', 'branch.brands', 'creator', 'items.product']);

        return [
            'quotation' => $quotation,
            'invoice' => new QuotationInvoice($quotation),
            'company' => self::company($quotation, $host),
            'logo' => self::logoPath($quotation, $host),
            'background' => self::backgroundPath($quotation),
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
            return self::storedFile($quotation->branch->logo_path);
        }

        $logo = Branding::forHost($host)['logo'];

        if (blank($logo)) {
            return null;
        }

        $path = public_path(ltrim($logo, '/'));

        return is_file($path) ? $path : null;
    }

    /**
     * The raising branch's full-page background as a file on disk. When there
     * is one, every page is printed on it, and its artwork takes the place of
     * the logo, contact lines and brand strip.
     */
    public static function backgroundPath(Quotation $quotation): ?string
    {
        return self::storedFile($quotation->branch?->quotation_background_path);
    }

    /**
     * The on-disk path of a stored image, or null when it is missing.
     */
    private static function storedFile(?string $path): ?string
    {
        $disk = Storage::disk(StoredImage::DISK);

        return filled($path) && $disk->exists($path) ? $disk->path($path) : null;
    }

    /**
     * The company identity printed on this quotation: the raising branch's
     * letterhead, with the brand behind the host supplying only what a
     * branch does not keep (its state, declaration and the like). The
     * branch's city is not printed.
     *
     * @return array<string, mixed>
     */
    public static function company(Quotation $quotation, string $host): array
    {
        return [
            ...Branding::companyForHost($host),
            ...($quotation->branch?->letterhead(withCity: false) ?? []),
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
     * The lines printed in the "Quotation Details" box, each read as a label
     * and value when it has one ("Email: sales@example.com"). The quotation's
     * own lines win; otherwise the raising branch's standard ones are used.
     *
     * @return list<array{label: string|null, value: string}>
     */
    public static function details(Quotation $quotation): array
    {
        $text = filled($quotation->details)
            ? $quotation->details
            : self::defaultDetails($quotation->branch, $quotation->creator);

        return array_values(array_map(
            fn (string $line): array => preg_match('/^([^:]{1,30}?)\s*:\s*(.*)$/', trim($line), $parts)
                ? ['label' => $parts[1], 'value' => $parts[2]]
                : ['label' => null, 'value' => trim($line)],
            preg_split('/\R/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [],
        ));
    }

    /**
     * The details a new quotation starts with: the branch's standard lines,
     * or, when it keeps none, the person sending it and the branch GSTIN.
     */
    public static function defaultDetails(?Branch $branch, ?User $sender): string
    {
        if (filled($branch?->quotation_details)) {
            return $branch->quotation_details;
        }

        return implode("\n", array_filter([
            $sender ? 'Sales Contact: '.$sender->name : null,
            filled($sender?->email) ? 'Email: '.$sender->email : null,
            filled($branch?->gstin) ? 'GSTIN: '.$branch->gstin : null,
        ]));
    }

    /**
     * Render the printable page. The items box is stretched down a short
     * quotation's page; should that stretch tip the quotation onto another
     * page, it is set again without it.
     */
    public static function render(Quotation $quotation, string $host): PdfDocument
    {
        $data = self::for($quotation, $host);
        $html = view('quotations.pdf', $data)->render();

        if (str_contains($html, 'class="filler"') && ($pages = self::pageCount($html)) > 1) {
            $unstretched = view('quotations.pdf', [...$data, 'fillItems' => false])->render();

            if (self::pageCount($unstretched) < $pages) {
                $html = $unstretched;
            }
        }

        return Pdf::loadHTML($html);
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
}
