<?php

namespace App\Support;

use App\Models\Quotation;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfDocument;

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
     * @return array{quotation: Quotation, invoice: QuotationInvoice, company: array<string, mixed>}
     */
    public static function for(Quotation $quotation, string $host): array
    {
        $quotation->loadMissing(['customer.state', 'contact.state', 'project', 'branch', 'items.product']);

        return [
            'quotation' => $quotation,
            'invoice' => new QuotationInvoice($quotation),
            'company' => self::company($quotation, $host),
        ];
    }

    /**
     * The company identity printed on this quotation: the brand behind the
     * host, carrying the raising branch's own bank account when it has one.
     *
     * @return array<string, mixed>
     */
    public static function company(Quotation $quotation, string $host): array
    {
        $company = Branding::companyForHost($host);
        $bank = $quotation->branch?->bankDetails();

        if ($bank !== null) {
            $company['bank'] = $bank;
        }

        return $company;
    }

    /**
     * Render the printable page.
     */
    public static function render(Quotation $quotation, string $host): PdfDocument
    {
        return Pdf::loadView('quotations.pdf', self::for($quotation, $host));
    }
}
