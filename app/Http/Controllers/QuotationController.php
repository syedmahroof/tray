<?php

namespace App\Http\Controllers;

use App\Exports\GenericSheetExport;
use App\Http\Requests\SaveQuotationRequest;
use App\Models\Contact;
use App\Models\Product;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\User;
use App\Support\BranchAccess;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class QuotationController extends Controller
{
    /**
     * Display a listing of quotations.
     */
    public function index(Request $request): Response
    {
        $search = trim((string) $request->input('search', ''));
        $status = $request->input('status');
        $createdBy = $request->input('created_by');

        $statusCounts = Quotation::query()
            ->groupBy('status')
            ->selectRaw('status, count(*) as total')
            ->pluck('total', 'status');

        return Inertia::render('quotations/Index', [
            'stats' => [
                'total' => (int) $statusCounts->sum(),
                'draft' => (int) $statusCounts->get('draft', 0),
                'sent' => (int) $statusCounts->get('sent', 0),
                'accepted' => (int) $statusCounts->get('accepted', 0),
            ],
            'quotations' => Quotation::query()
                ->with(['contact', 'project', 'creator'])
                ->when($search !== '', function ($query) use ($search) {
                    $query->where(function ($q) use ($search) {
                        $q->where('number', 'like', "%{$search}%")
                            ->orWhereHas('contact', fn ($sub) => $sub->where('name', 'like', "%{$search}%"));
                    });
                })
                ->when($status, fn ($query) => $query->where('status', $status))
                ->when($createdBy, fn ($query) => $query->where('created_by', $createdBy))
                ->latest()
                ->paginate(15)
                ->withQueryString(),
            'statuses' => Quotation::STATUSES,
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
            'filters' => [
                'search' => $search,
                'status' => $status,
                'created_by' => $createdBy,
            ],
        ]);
    }

    /**
     * Export the filtered quotations to an Excel spreadsheet.
     */
    public function export(Request $request): BinaryFileResponse
    {
        $search = trim((string) $request->input('search', ''));

        $quotations = Quotation::query()
            ->with(['contact', 'project', 'creator'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('number', 'like', "%{$search}%")
                        ->orWhereHas('contact', fn ($sub) => $sub->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($request->input('status'), fn ($query, $value) => $query->where('status', $value))
            ->when($request->input('created_by'), fn ($query, $value) => $query->where('created_by', $value))
            ->latest()
            ->get();

        $rows = $quotations->map(fn (Quotation $quotation): array => [
            $quotation->number,
            $quotation->contact?->name,
            $quotation->project?->name,
            $quotation->quotation_date?->format('Y-m-d'),
            ucfirst($quotation->status),
            $quotation->total,
            $quotation->creator?->name,
        ])->all();

        return Excel::download(
            new GenericSheetExport(
                ['Number', 'Contact', 'Project', 'Date', 'Status', 'Total', 'Created By'],
                $rows,
            ),
            'quotations.xlsx',
        );
    }

    /**
     * Show the form for creating a new quotation.
     */
    public function create(): Response
    {
        return Inertia::render('quotations/Create', $this->formData());
    }

    /**
     * Store a newly created quotation.
     */
    public function store(SaveQuotationRequest $request): RedirectResponse
    {
        $quotation = DB::transaction(function () use ($request) {
            $totals = $this->calculateTotals($request);

            $quotation = Quotation::create([
                ...$request->safe()->except(['items']),
                'number' => 'TEMP',
                'subtotal' => $totals['subtotal'],
                'tax_amount' => $totals['tax_amount'],
                'total' => $totals['total'],
                'created_by' => $request->user()->id,
            ]);

            $quotation->update(['number' => $this->generateNumber($quotation->id)]);
            $quotation->items()->createMany($this->itemRows($request));

            return $quotation;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Quotation created.')]);

        return to_route('quotations.show', $quotation);
    }

    /**
     * Display the given quotation.
     */
    public function show(Quotation $quotation): Response
    {
        $quotation->load(['contact', 'project', 'creator', 'branch', 'items.product']);

        return Inertia::render('quotations/Show', [
            'quotation' => $quotation,
        ]);
    }

    /**
     * Show the form for editing the given quotation.
     */
    public function edit(Quotation $quotation): Response
    {
        $quotation->load(['items.product']);

        return Inertia::render('quotations/Edit', [
            'quotation' => $quotation,
            ...$this->formData(),
        ]);
    }

    /**
     * Update the given quotation.
     */
    public function update(SaveQuotationRequest $request, Quotation $quotation): RedirectResponse
    {
        DB::transaction(function () use ($request, $quotation) {
            $totals = $this->calculateTotals($request);

            $quotation->update([
                ...$request->safe()->except(['items']),
                'subtotal' => $totals['subtotal'],
                'tax_amount' => $totals['tax_amount'],
                'total' => $totals['total'],
            ]);

            $quotation->items()->delete();
            $quotation->items()->createMany($this->itemRows($request));
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Quotation updated.')]);

        return to_route('quotations.show', $quotation);
    }

    /**
     * Remove the given quotation.
     */
    public function destroy(Quotation $quotation): RedirectResponse
    {
        $quotation->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Quotation deleted.')]);

        return to_route('quotations.index');
    }

    /**
     * Download the quotation as a PDF document.
     */
    public function pdf(Quotation $quotation): HttpResponse
    {
        $quotation->load(['contact', 'project', 'branch', 'items.product']);

        $pdf = Pdf::loadView('quotations.pdf', ['quotation' => $quotation]);

        return $pdf->download("{$quotation->number}.pdf");
    }

    /**
     * Shared lookup data for the create/edit forms.
     *
     * @return array<string, mixed>
     */
    private function formData(): array
    {
        return [
            'contacts' => Contact::query()->orderBy('name')->get(['id', 'name', 'phone', 'email']),
            'projects' => Project::query()->orderBy('name')->get(['id', 'name']),
            'products' => Product::query()->orderBy('name')->get(['id', 'name', 'price']),
            'statuses' => Quotation::STATUSES,
            'branches' => BranchAccess::canChooseBranch() ? BranchAccess::options() : [],
        ];
    }

    /**
     * Build the persisted item rows from the validated request.
     *
     * @return array<int, array<string, mixed>>
     */
    private function itemRows(SaveQuotationRequest $request): array
    {
        return array_map(fn (array $item): array => [
            'product_id' => $item['product_id'] ?? null,
            'description' => $item['description'],
            'quantity' => $item['quantity'],
            'unit_price' => $item['unit_price'],
        ], $request->validated('items', []));
    }

    /**
     * Calculate subtotal, tax, and total from the request items.
     *
     * @return array{subtotal: float, tax_amount: float, total: float}
     */
    private function calculateTotals(SaveQuotationRequest $request): array
    {
        $subtotal = array_reduce(
            $request->validated('items', []),
            fn (float $carry, array $item): float => $carry + ((float) $item['quantity'] * (float) $item['unit_price']),
            0.0,
        );

        $discount = (float) $request->validated('discount', 0);
        $taxPercent = (float) $request->validated('tax_percent', 0);
        $taxable = max($subtotal - $discount, 0);
        $taxAmount = round($taxable * $taxPercent / 100, 2);

        return [
            'subtotal' => round($subtotal, 2),
            'tax_amount' => $taxAmount,
            'total' => round($taxable + $taxAmount, 2),
        ];
    }

    /**
     * Generate a sequential quotation number.
     */
    private function generateNumber(int $id): string
    {
        return 'QT-'.now()->format('Y').'-'.str_pad((string) $id, 5, '0', STR_PAD_LEFT);
    }
}
