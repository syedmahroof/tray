<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveEnquiryRequest;
use App\Models\Contact;
use App\Models\Enquiry;
use App\Models\Product;
use App\Models\Project;
use App\Models\User;
use App\Support\BranchAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class EnquiryController extends Controller
{
    /**
     * Display a listing of enquiries.
     */
    public function index(Request $request): Response
    {
        $search = trim((string) $request->input('search', ''));

        return Inertia::render('enquiries/Index', [
            'enquiries' => Enquiry::query()
                ->with(['customer', 'contact', 'project', 'product', 'assignee'])
                ->when($search !== '', function ($query) use ($search) {
                    $query->where(function ($q) use ($search) {
                        $q->where('status', 'like', "%{$search}%")
                            ->orWhere('source', 'like', "%{$search}%")
                            ->orWhere('remarks', 'like', "%{$search}%")
                            ->orWhereHas('contact', fn ($sub) => $sub->where('name', 'like', "%{$search}%"))
                            ->orWhereHas('project', fn ($sub) => $sub->where('name', 'like', "%{$search}%"))
                            ->orWhereHas('product', fn ($sub) => $sub->where('name', 'like', "%{$search}%"));
                    });
                })
                ->latest()
                ->paginate(15)
                ->withQueryString(),
            'statusCounts' => $this->statusCounts(),
            'filters' => ['search' => $search],
        ]);
    }

    /**
     * Display the kanban board of enquiries grouped by status.
     */
    public function kanban(): Response
    {
        $enquiries = Enquiry::query()
            ->with(['customer', 'contact', 'project', 'product', 'assignee'])
            ->latest()
            ->get();

        return Inertia::render('enquiries/Kanban', [
            'enquiriesByStatus' => collect(Enquiry::STATUSES)
                ->mapWithKeys(fn (string $status) => [$status => $enquiries->where('status', $status)->values()]),
        ]);
    }

    /**
     * Update the status of the given enquiry, used by the kanban board.
     */
    public function updateStatus(Request $request, Enquiry $enquiry): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(Enquiry::STATUSES)],
        ]);

        $enquiry->update($validated);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Enquiry status updated.')]);

        return back();
    }

    /**
     * Show the form for creating a new enquiry.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('enquiries/Create', [
            'customers' => \App\Models\Customer::query()->orderBy('name')->get(['id', 'name']),
            'contacts' => Contact::query()->with('contactType:id,name')->orderBy('name')->get(['id', 'name', 'contact_type_id']),
            'projects' => Project::query()->orderBy('name')->get(['id', 'name']),
            'products' => Product::query()->orderBy('name')->get(['id', 'name']),
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
            'statuses' => Enquiry::STATUSES,
            'branches' => BranchAccess::canChooseBranch() ? BranchAccess::options() : [],
            'preselectedContactId' => $request->integer('contact_id') ?: null,
            'preselectedCustomerId' => $request->integer('customer_id') ?: null,
        ]);
    }

    /**
     * Store a newly created enquiry.
     */
    public function store(SaveEnquiryRequest $request): RedirectResponse
    {
        $enquiry = Enquiry::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Enquiry created.')]);

        return to_route('enquiries.show', $enquiry);
    }

    /**
     * Display the given enquiry.
     */
    public function show(Enquiry $enquiry): Response
    {
        $enquiry->load(['customer', 'contact', 'project', 'product', 'assignee', 'branch']);

        return Inertia::render('enquiries/Show', [
            'enquiry' => $enquiry,
            'notes' => $enquiry->notes()->with('user')->latest()->get(),
            'reminders' => $enquiry->reminders()->with('user')->orderBy('remind_at')->get(),
            'quotations' => $enquiry->quotations()->latest()
                ->get(['id', 'number', 'version', 'status', 'total', 'quotation_date', 'created_at']),
        ]);
    }

    /**
     * Show the form for editing the given enquiry.
     */
    public function edit(Enquiry $enquiry): Response
    {
        return Inertia::render('enquiries/Edit', [
            'enquiry' => $enquiry,
            'customers' => \App\Models\Customer::query()->orderBy('name')->get(['id', 'name']),
            'contacts' => Contact::query()->with('contactType:id,name')->orderBy('name')->get(['id', 'name', 'contact_type_id']),
            'projects' => Project::query()->orderBy('name')->get(['id', 'name']),
            'products' => Product::query()->orderBy('name')->get(['id', 'name']),
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
            'statuses' => Enquiry::STATUSES,
            'branches' => BranchAccess::canChooseBranch() ? BranchAccess::options() : [],
        ]);
    }

    /**
     * Update the given enquiry.
     */
    public function update(SaveEnquiryRequest $request, Enquiry $enquiry): RedirectResponse
    {
        $enquiry->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Enquiry updated.')]);

        return to_route('enquiries.show', $enquiry);
    }

    /**
     * Remove the given enquiry.
     */
    public function destroy(Enquiry $enquiry): RedirectResponse
    {
        $enquiry->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Enquiry deleted.')]);

        return to_route('enquiries.index');
    }

    /**
     * @return array<int, array{status: string, count: int}>
     */
    private function statusCounts(): array
    {
        $counts = Enquiry::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return array_map(
            fn (string $status): array => ['status' => $status, 'count' => $counts->get($status, 0)],
            Enquiry::STATUSES,
        );
    }
}
