<?php

namespace App\Http\Controllers;

use App\Exports\GenericSheetExport;
use App\Http\Requests\SaveContactRequest;
use App\Models\Contact;
use App\Models\ContactType;
use App\Models\Country;
use App\Models\User;
use App\Support\BranchAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ContactController extends Controller
{
    /**
     * Display a listing of contacts.
     */
    public function index(Request $request): Response
    {
        $search = trim((string) $request->input('search', ''));
        $contactTypeId = $request->input('contact_type_id');
        $assignedTo = $request->input('assigned_to');
        $isSuperAdmin = $request->user()->hasRole('Super Admin');
        $createdBy = $isSuperAdmin ? $request->input('created_by') : null;
        $createdFrom = $request->input('created_from');
        $createdTo = $request->input('created_to');

        return Inertia::render('contacts/Index', [
            'contacts' => Contact::query()
                ->with(['contactType', 'assignee', 'creator'])
                ->when($search !== '', function ($query) use ($search) {
                    $query->where(function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
                })
                ->when($contactTypeId, fn ($query) => $query->where('contact_type_id', $contactTypeId))
                ->when($assignedTo, fn ($query) => $query->where('assigned_to', $assignedTo))
                ->when($createdBy, fn ($query) => $query->where('created_by', $createdBy))
                ->when($createdFrom, fn ($query) => $query->whereDate('created_at', '>=', $createdFrom))
                ->when($createdTo, fn ($query) => $query->whereDate('created_at', '<=', $createdTo))
                ->orderBy('name')
                ->paginate(15)
                ->withQueryString(),
            'contactTypes' => ContactType::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
            'filters' => [
                'search' => $search,
                'contact_type_id' => $contactTypeId,
                'assigned_to' => $assignedTo,
                'created_by' => $createdBy,
                'created_from' => $createdFrom,
                'created_to' => $createdTo,
            ],
            'stats' => [
                'total' => Contact::count(),
                'unassigned' => Contact::whereNull('assigned_to')->count(),
                'newThisMonth' => Contact::whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year)
                    ->count(),
                'typesBreakdown' => Contact::query()
                    ->join('contact_types', 'contacts.contact_type_id', '=', 'contact_types.id')
                    ->selectRaw('contact_types.name as label, count(contacts.id) as value')
                    ->groupBy('contact_types.name')
                    ->get()
                    ->toArray(),
            ],
        ]);
    }

    /**
     * Export the filtered contacts to an Excel spreadsheet.
     */
    public function export(Request $request): BinaryFileResponse
    {
        $search = trim((string) $request->input('search', ''));

        $contacts = Contact::query()
            ->with(['contactType', 'assignee', 'creator'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($request->input('contact_type_id'), fn ($query, $value) => $query->where('contact_type_id', $value))
            ->when($request->input('assigned_to'), fn ($query, $value) => $query->where('assigned_to', $value))
            ->when($request->user()->hasRole('Super Admin') && $request->input('created_by'), fn ($query, $value) => $query->where('created_by', $value))
            ->when($request->input('created_from'), fn ($query, $value) => $query->whereDate('created_at', '>=', $value))
            ->when($request->input('created_to'), fn ($query, $value) => $query->whereDate('created_at', '<=', $value))
            ->orderBy('name')
            ->get();

        $rows = $contacts->map(fn (Contact $contact): array => [
            $contact->name,
            $contact->contactType->name,
            $contact->phone,
            $contact->email,
            $contact->assignee?->name,
            $contact->creator?->name,
            $contact->created_at?->format('Y-m-d'),
        ])->all();

        return Excel::download(
            new GenericSheetExport(
                ['Name', 'Type', 'Phone', 'Email', 'Assigned To', 'Created By', 'Created At'],
                $rows,
            ),
            'contacts.xlsx',
        );
    }

    /**
     * Show the form for creating a new contact.
     */
    public function create(): Response
    {
        return Inertia::render('contacts/Create', [
            'contactTypes' => ContactType::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'countries' => Country::query()->orderBy('name')->get(['id', 'name']),
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
            'branches' => BranchAccess::canChooseBranch() ? BranchAccess::options() : [],
        ]);
    }

    /**
     * Store a newly created contact.
     */
    public function store(SaveContactRequest $request): RedirectResponse
    {
        $contact = Contact::create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Contact created.')]);

        return to_route('contacts.show', $contact);
    }

    /**
     * Display the given contact.
     */
    public function show(Contact $contact): Response
    {
        $contact->load(['contactType', 'country', 'state', 'district', 'assignee', 'creator', 'branch']);

        return Inertia::render('contacts/Show', [
            'contact' => $contact,
            'notes' => $contact->notes()->with('user')->latest()->get(),
            'reminders' => $contact->reminders()->with('user')->orderBy('remind_at')->get(),
            'visitReports' => $contact->visitReports()->with('user')->orderByDesc('visit_date')->get(),
            'enquiries' => $contact->enquiries()->with(['project', 'product'])->latest()->get(),
            'quotations' => $contact->quotations()->latest()
                ->get(['id', 'number', 'version', 'status', 'total', 'quotation_date', 'created_at']),
            'auditLogs' => $contact->auditLogs()->with('user')->latest()->get(),
        ]);
    }

    /**
     * Display contact analytics.
     */
    public function analytics(Request $request): Response
    {
        $range = $request->input('range', '30d');
        $from = $request->input('from');
        $to = $request->input('to');

        $query = Contact::query();

        // Date filter
        if ($range === 'custom' && $from && $to) {
            $query->whereBetween('created_at', [$from.' 00:00:00', $to.' 23:59:59']);
        } else {
            $days = match ($range) {
                '7d' => 7,
                'this_month' => now()->day,
                '3m' => 90,
                '6m' => 180,
                '1y' => 365,
                default => 30, // '30d'
            };
            $query->where('created_at', '>=', now()->subDays($days));
        }

        // Gather all filtered contacts to build aggregations
        $filteredContacts = $query->with(['contactType', 'assignee', 'branch'])->get();

        // 1. General Stats
        $stats = [
            'total' => Contact::count(),
            'inRange' => $filteredContacts->count(),
            'unassigned' => $filteredContacts->whereNull('assigned_to')->count(),
            'newThisMonth' => Contact::whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count(),
        ];

        // 2. Contacts by Type
        $contactsByType = $filteredContacts->groupBy('contactType.name')
            ->map(fn ($group) => ['type' => $group->first()->contactType->name, 'count' => $group->count()])
            ->values()
            ->toArray();

        // 3. Contacts by Branch
        $contactsByBranch = $filteredContacts->groupBy('branch.name')
            ->map(fn ($group) => ['branch' => $group->first()->branch?->name ?? 'No Branch', 'count' => $group->count()])
            ->values()
            ->toArray();

        // 4. Contacts by Assignee
        $contactsByStaff = $filteredContacts->groupBy('assignee.name')
            ->map(fn ($group) => ['staff' => $group->first()->assignee?->name ?? 'Unassigned', 'count' => $group->count()])
            ->values()
            ->toArray();

        // 5. Creation Trend
        $trendFormat = ($range === '6m' || $range === '1y' || ($range === 'custom' && now()->parse($from)->diffInDays($to) > 60))
            ? '%Y-%m'
            : '%Y-%m-%d';

        $dbDriver = Contact::query()->getConnection()->getDriverName();
        $selectRaw = $dbDriver === 'sqlite'
            ? ($trendFormat === '%Y-%m' ? "strftime('%Y-%m', created_at) as label, count(id) as count" : "strftime('%Y-%m-%d', created_at) as label, count(id) as count")
            : "DATE_FORMAT(created_at, '{$trendFormat}') as label, count(id) as count";

        $rawTrend = Contact::query()
            ->when($range === 'custom' && $from && $to, function ($q) use ($from, $to) {
                $q->whereBetween('created_at', [$from.' 00:00:00', $to.' 23:59:59']);
            }, function ($q) use ($range) {
                $days = match ($range) {
                    '7d' => 7,
                    'this_month' => now()->day,
                    '3m' => 90,
                    '6m' => 180,
                    '1y' => 365,
                    default => 30,
                };
                $q->where('created_at', '>=', now()->subDays($days));
            })
            ->selectRaw($selectRaw)
            ->groupBy('label')
            ->orderBy('label')
            ->get();

        $contactTrends = $rawTrend->map(fn ($item) => [
            'label' => $item->label,
            'count' => $item->count,
        ])->toArray();

        return Inertia::render('contacts/Analytics', [
            'range' => $range,
            'from' => $from,
            'to' => $to,
            'stats' => $stats,
            'contactsByType' => $contactsByType,
            'contactsByBranch' => $contactsByBranch,
            'contactsByStaff' => $contactsByStaff,
            'contactTrends' => $contactTrends,
        ]);
    }

    /**
     * Show the form for editing the given contact.
     */
    public function edit(Contact $contact): Response
    {
        return Inertia::render('contacts/Edit', [
            'contact' => $contact,
            'contactTypes' => ContactType::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'countries' => Country::query()->orderBy('name')->get(['id', 'name']),
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
            'branches' => BranchAccess::canChooseBranch() ? BranchAccess::options() : [],
        ]);
    }

    /**
     * Update the given contact.
     */
    public function update(SaveContactRequest $request, Contact $contact): RedirectResponse
    {
        $contact->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Contact updated.')]);

        return to_route('contacts.show', $contact);
    }

    /**
     * Remove the given contact.
     */
    public function destroy(Contact $contact): RedirectResponse
    {
        if ($contact->enquiries()->exists()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Cannot delete a contact that still has enquiries.')]);

            return back();
        }

        $contact->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Contact deleted.')]);

        return to_route('contacts.index');
    }
}
