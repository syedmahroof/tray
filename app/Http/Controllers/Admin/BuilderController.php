<?php

namespace App\Http\Controllers\Admin;

use App\Exports\GenericSheetExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveBuilderRequest;
use App\Models\Builder;
use App\Models\Country;
use App\Models\User;
use App\Models\VisitReport;
use App\Support\BranchAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BuilderController extends Controller
{
    /**
     * Display a listing of builders.
     */
    public function index(Request $request): Response
    {
        $search = trim((string) $request->input('search', ''));
        $createdBy = $request->input('created_by');
        $createdFrom = $request->input('created_from');
        $createdTo = $request->input('created_to');
        $noVisitWithin = $request->input('no_visit_within');

        return Inertia::render('admin/builders/Index', [
            'builders' => Builder::query()
                ->with(['country', 'state', 'district', 'creator'])
                ->when($search !== '', function ($query) use ($search) {
                    $query->where(function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%")
                            ->orWhere('contact_person', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
                })
                ->when($createdBy, fn ($query) => $query->where('created_by', $createdBy))
                ->when($createdFrom, fn ($query) => $query->whereDate('created_at', '>=', $createdFrom))
                ->when($createdTo, fn ($query) => $query->whereDate('created_at', '<=', $createdTo))
                ->when(VisitReport::NO_VISIT_PERIODS[$noVisitWithin] ?? null, function ($query, $days) {
                    // A builder is "visited" when any of its projects has a recent visit report.
                    $query->whereDoesntHave('projects', fn ($project) => $project->whereHas('visitReports', fn ($sub) => $sub->where('visit_date', '>=', now()->subDays($days)->toDateString())));
                })
                ->orderBy('name')
                ->paginate(15)
                ->withQueryString(),
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
            'filters' => [
                'search' => $search,
                'created_by' => $createdBy,
                'created_from' => $createdFrom,
                'created_to' => $createdTo,
                'no_visit_within' => $noVisitWithin,
            ],
        ]);
    }

    /**
     * Export the filtered builders to an Excel spreadsheet.
     */
    public function export(Request $request): BinaryFileResponse
    {
        $search = trim((string) $request->input('search', ''));

        $builders = Builder::query()
            ->with(['country', 'state', 'district', 'creator'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('contact_person', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($request->input('created_by'), fn ($query, $value) => $query->where('created_by', $value))
            ->when($request->input('created_from'), fn ($query, $value) => $query->whereDate('created_at', '>=', $value))
            ->when($request->input('created_to'), fn ($query, $value) => $query->whereDate('created_at', '<=', $value))
            ->when(VisitReport::NO_VISIT_PERIODS[$request->input('no_visit_within')] ?? null, function ($query, $days) {
                $query->whereDoesntHave('projects', fn ($project) => $project->whereHas('visitReports', fn ($sub) => $sub->where('visit_date', '>=', now()->subDays($days)->toDateString())));
            })
            ->orderBy('name')
            ->get();

        $rows = $builders->map(fn (Builder $builder): array => [
            $builder->name,
            $builder->contact_person,
            $builder->phone,
            $builder->email,
            collect([$builder->district?->name, $builder->state?->name, $builder->country?->name])->filter()->join(', '),
            $builder->is_active ? 'Active' : 'Inactive',
            $builder->creator?->name,
            $builder->created_at?->format('Y-m-d'),
        ])->all();

        return Excel::download(
            new GenericSheetExport(
                ['Name', 'Contact Person', 'Phone', 'Email', 'Location', 'Status', 'Created By', 'Created At'],
                $rows,
            ),
            'builders.xlsx',
        );
    }

    /**
     * Show the form for creating a new builder.
     */
    public function create(): Response
    {
        return Inertia::render('admin/builders/Create', [
            'countries' => Country::query()->orderBy('name')->get(['id', 'name']),
            'branches' => BranchAccess::canChooseBranch() ? BranchAccess::options() : [],
        ]);
    }

    /**
     * Store a newly created builder.
     */
    public function store(SaveBuilderRequest $request): RedirectResponse
    {
        Builder::create([
            ...$request->validated(),
            'is_active' => $request->boolean('is_active'),
            'created_by' => $request->user()->id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Builder created.')]);

        return to_route('builders.index');
    }

    /**
     * Display the given builder.
     */
    public function show(Builder $builder): Response
    {
        $builder->load(['country', 'state', 'district', 'projects', 'visitReports.user']);

        return Inertia::render('admin/builders/Show', [
            'builder' => $builder,
            'quotations' => $builder->quotations()->latest()
                ->get(['id', 'number', 'version', 'status', 'total', 'quotation_date', 'created_at']),
        ]);
    }

    /**
     * Show the form for editing the given builder.
     */
    public function edit(Builder $builder): Response
    {
        return Inertia::render('admin/builders/Edit', [
            'builder' => $builder,
            'countries' => Country::query()->orderBy('name')->get(['id', 'name']),
            'branches' => BranchAccess::canChooseBranch() ? BranchAccess::options() : [],
        ]);
    }

    /**
     * Update the given builder.
     */
    public function update(SaveBuilderRequest $request, Builder $builder): RedirectResponse
    {
        $builder->update([
            ...$request->validated(),
            'is_active' => $request->boolean('is_active'),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Builder updated.')]);

        return to_route('builders.index');
    }

    /**
     * Remove the given builder.
     */
    public function destroy(Builder $builder): RedirectResponse
    {
        if ($builder->projects()->exists()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Cannot delete a builder that still has projects.')]);

            return back();
        }

        $builder->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Builder deleted.')]);

        return to_route('builders.index');
    }
}
