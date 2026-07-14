<?php

namespace App\Http\Controllers;

use App\Exports\GenericSheetExport;
use App\Http\Requests\SaveQuotationRequest;
use App\Mail\QuotationMail;
use App\Models\Builder;
use App\Models\Contact;
use App\Models\Enquiry;
use App\Models\Product;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\User;
use App\Support\BranchAccess;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
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
     * Display quotation analytics: value, status mix, win rate, and trends.
     */
    public function analytics(Request $request): Response
    {
        $range = $request->input('range', '30d');
        $from = $request->input('from');
        $to = $request->input('to');

        $applyRange = function ($query) use ($range, $from, $to) {
            if ($range === 'custom' && $from && $to) {
                return $query->whereBetween('quotation_date', [$from, $to]);
            }

            $days = match ($range) {
                '7d' => 7,
                'this_month' => now()->day,
                '3m' => 90,
                '6m' => 180,
                '1y' => 365,
                default => 30,
            };

            return $query->where('quotation_date', '>=', now()->subDays($days)->toDateString());
        };

        $filtered = $applyRange(Quotation::query())
            ->with(['creator:id,name', 'branch:id,name'])
            ->get(['id', 'status', 'total', 'quotation_date', 'created_by', 'branch_id']);

        $won = $filtered->where('status', 'accepted')->count();
        $lost = $filtered->whereIn('status', ['rejected', 'expired'])->count();

        $statusBreakdown = array_map(
            fn (string $status): array => [
                'status' => $status,
                'count' => $filtered->where('status', $status)->count(),
                'value' => (float) $filtered->where('status', $status)->sum('total'),
            ],
            Quotation::STATUSES,
        );

        return Inertia::render('quotations/Analytics', [
            'range' => $range,
            'from' => $from,
            'to' => $to,
            'stats' => [
                'total' => (int) $filtered->count(),
                'quotedValue' => (float) $filtered->sum('total'),
                'acceptedValue' => (float) $filtered->where('status', 'accepted')->sum('total'),
                'winRate' => ($won + $lost) > 0 ? round($won / ($won + $lost) * 100, 1) : 0.0,
            ],
            'statusBreakdown' => $statusBreakdown,
            'byCreator' => $filtered->groupBy(fn (Quotation $q): string => $q->creator?->name ?? 'Unassigned')
                ->map(fn ($group, string $name): array => [
                    'staff' => $name,
                    'count' => $group->count(),
                    'value' => (float) $group->sum('total'),
                ])
                ->sortByDesc('value')
                ->values()
                ->all(),
            'byBranch' => $filtered->groupBy(fn (Quotation $q): string => $q->branch?->name ?? 'No Branch')
                ->map(fn ($group, string $name): array => [
                    'branch' => $name,
                    'value' => (float) $group->sum('total'),
                ])
                ->sortByDesc('value')
                ->values()
                ->all(),
            'trend' => $this->quotationTrend($applyRange, $range, $from, $to),
        ]);
    }

    /**
     * Build a time-series of quoted value, driver-agnostic across sqlite/MySQL.
     *
     * @return array<int, array{label: string, value: float}>
     */
    private function quotationTrend(callable $applyRange, string $range, ?string $from, ?string $to): array
    {
        $monthly = in_array($range, ['6m', '1y'], true)
            || ($range === 'custom' && $from && $to && now()->parse($from)->diffInDays($to) > 60);

        $driver = Quotation::query()->getConnection()->getDriverName();
        $format = $monthly ? '%Y-%m' : '%Y-%m-%d';

        $expression = $driver === 'sqlite'
            ? "strftime('{$format}', quotation_date) as label, sum(total) as value"
            : "DATE_FORMAT(quotation_date, '{$format}') as label, sum(total) as value";

        return $applyRange(Quotation::query())
            ->selectRaw($expression)
            ->groupBy('label')
            ->orderBy('label')
            ->get()
            ->map(fn ($row): array => ['label' => (string) $row->label, 'value' => (float) $row->value])
            ->all();
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
    public function create(Request $request): Response
    {
        return Inertia::render('quotations/Create', [
            ...$this->formData(),
            'defaults' => $this->prefillDefaults($request),
        ]);
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
        $quotation->load(['contact', 'project', 'enquiry.contact:id,name', 'builder', 'creator', 'branch', 'items.product']);

        $rootId = $quotation->rootId();

        return Inertia::render('quotations/Show', [
            'quotation' => $quotation,
            'shareUrl' => URL::signedRoute('quotations.shared', $quotation),
            'statuses' => Quotation::STATUSES,
            'versions' => Quotation::query()
                ->where(fn ($query) => $query->where('id', $rootId)->orWhere('parent_id', $rootId))
                ->orderBy('version')
                ->get(['id', 'number', 'version', 'status', 'total', 'created_at']),
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
        return $this->renderPdf($quotation)->download("{$quotation->number}.pdf");
    }

    /**
     * Stream the quotation PDF inline so the browser can print it.
     */
    public function print(Quotation $quotation): HttpResponse
    {
        return $this->renderPdf($quotation)->stream("{$quotation->number}.pdf");
    }

    /**
     * Publicly stream the quotation PDF via a signed share link (no auth).
     */
    public function shared(Quotation $quotation): HttpResponse
    {
        return $this->renderPdf($quotation)->stream("{$quotation->number}.pdf");
    }

    /**
     * Update the quotation status and record an audit entry.
     */
    public function updateStatus(Request $request, Quotation $quotation): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(Quotation::STATUSES)],
        ]);

        $previous = $quotation->status;

        if ($previous !== $validated['status']) {
            $quotation->update($validated);

            $quotation->auditLogs()->create([
                'user_id' => $request->user()->id,
                'action' => 'status_changed',
                'description' => "Status changed from {$previous} to {$validated['status']}.",
                'changes' => ['old' => ['status' => $previous], 'new' => ['status' => $validated['status']]],
            ]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Quotation status updated.')]);

        return back();
    }

    /**
     * Email the quotation PDF to its contact and mark it as sent.
     */
    public function email(Request $request, Quotation $quotation): RedirectResponse
    {
        $quotation->load(['contact', 'branch']);

        if (! $quotation->contact?->email) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('This contact has no email address.')]);

            return back();
        }

        Mail::to($quotation->contact->email)->queue(new QuotationMail($quotation));

        if ($quotation->status === 'draft') {
            $quotation->update(['status' => 'sent']);
        }

        $quotation->auditLogs()->create([
            'user_id' => $request->user()->id,
            'action' => 'emailed',
            'description' => "Quotation emailed to {$quotation->contact->email}.",
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Quotation emailed to :email.', ['email' => $quotation->contact->email])]);

        return back();
    }

    /**
     * Create a new revision (version) of the quotation, copying its items and
     * marking the source version as superseded.
     */
    public function revise(Request $request, Quotation $quotation): RedirectResponse
    {
        $revision = DB::transaction(function () use ($request, $quotation) {
            $quotation->load(['items', 'parent']);

            $rootId = $quotation->rootId();
            $rootNumber = $quotation->parent_id ? $quotation->parent->number : $quotation->number;

            $nextVersion = (int) Quotation::query()
                ->where(fn ($query) => $query->where('id', $rootId)->orWhere('parent_id', $rootId))
                ->max('version') + 1;

            $revision = Quotation::create([
                ...$quotation->only([
                    'branch_id', 'contact_id', 'project_id', 'enquiry_id', 'builder_id',
                    'valid_until', 'subtotal', 'discount', 'tax_percent', 'tax_amount',
                    'total', 'notes', 'terms',
                ]),
                'number' => "{$rootNumber}-R{$nextVersion}",
                'version' => $nextVersion,
                'parent_id' => $rootId,
                'quotation_date' => now()->toDateString(),
                'status' => 'draft',
                'created_by' => $request->user()->id,
            ]);

            $revision->items()->createMany(
                $quotation->items->map(fn (QuotationItem $item): array => [
                    'product_id' => $item->product_id,
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                ])->all(),
            );

            if ($quotation->status !== 'revised') {
                $quotation->update(['status' => 'revised']);
            }

            $revision->auditLogs()->create([
                'user_id' => $request->user()->id,
                'action' => 'revised',
                'description' => "Created as revision v{$nextVersion} of {$rootNumber}.",
            ]);

            $quotation->auditLogs()->create([
                'user_id' => $request->user()->id,
                'action' => 'superseded',
                'description' => "Superseded by revision {$revision->number}.",
            ]);

            return $revision;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Revision :number created.', ['number' => $revision->number])]);

        return to_route('quotations.show', $revision);
    }

    /**
     * Build the DomPDF document for the given quotation.
     */
    private function renderPdf(Quotation $quotation): PdfDocument
    {
        $quotation->load(['contact', 'project', 'branch', 'items.product']);

        return Pdf::loadView('quotations.pdf', ['quotation' => $quotation]);
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
            'enquiries' => Enquiry::query()->with('contact:id,name')->latest()->get(['id', 'contact_id', 'project_id'])
                ->map(fn (Enquiry $enquiry): array => [
                    'id' => $enquiry->id,
                    'name' => "#{$enquiry->id} — ".($enquiry->contact?->name ?? __('Enquiry')),
                ]),
            'builders' => Builder::query()->orderBy('name')->get(['id', 'name']),
            'products' => Product::query()->orderBy('name')->get(['id', 'name', 'price']),
            'statuses' => Quotation::STATUSES,
            'branches' => BranchAccess::canChooseBranch() ? BranchAccess::options() : [],
        ];
    }

    /**
     * Resolve prefill values for the create form from query parameters.
     *
     * When an enquiry is supplied its contact and project are inherited, and a
     * project's builder is inherited when a builder isn't explicitly provided.
     *
     * @return array<string, int|null>
     */
    private function prefillDefaults(Request $request): array
    {
        $defaults = [
            'contact_id' => $request->integer('contact_id') ?: null,
            'project_id' => $request->integer('project_id') ?: null,
            'enquiry_id' => $request->integer('enquiry_id') ?: null,
            'builder_id' => $request->integer('builder_id') ?: null,
        ];

        if ($defaults['enquiry_id'] && $enquiry = Enquiry::find($defaults['enquiry_id'])) {
            $defaults['contact_id'] ??= $enquiry->contact_id;
            $defaults['project_id'] ??= $enquiry->project_id;
        }

        if ($defaults['project_id'] && ! $defaults['builder_id'] && $project = Project::find($defaults['project_id'])) {
            $defaults['builder_id'] = $project->builder_id;
        }

        return $defaults;
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
