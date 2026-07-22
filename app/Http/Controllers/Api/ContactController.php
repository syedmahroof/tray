<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\VisitReport;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->input('search', ''));
        $contactTypeId = $request->input('contact_type_id');
        $assignedTo = $request->input('assigned_to');
        $isSuperAdmin = $request->user()->hasRole('Super Admin');
        $createdBy = $isSuperAdmin ? $request->input('created_by') : null;
        $createdFrom = $request->input('created_from');
        $createdTo = $request->input('created_to');
        $noVisitWithin = $request->input('no_visit_within');

        $contacts = Contact::query()
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
            ->when(VisitReport::NO_VISIT_PERIODS[$noVisitWithin] ?? null, function ($query, $days) {
                $query->whereDoesntHave('visitReports', fn ($sub) => $sub->where('visit_date', '>=', now()->subDays($days)->toDateString()));
            })
            ->orderBy('name')
            ->paginate(15);

        return response()->json($contacts);
    }

    public function show(Contact $contact)
    {
        $contact->load(['contactType', 'country', 'state', 'district', 'assignee', 'creator', 'branch']);

        return response()->json([
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

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'contact_type_id' => 'nullable|integer',
            'country_id' => 'nullable|integer|exists:countries,id',
            'state_id' => 'nullable|integer|exists:states,id',
            'district_id' => 'nullable|integer|exists:districts,id',
            'assigned_to' => 'nullable|integer|exists:users,id',
        ]);

        $validated['branch_id'] = $request->user()?->branch_id ?? 1;
        $validated['contact_type_id'] = $validated['contact_type_id'] ?? 1;
        $validated['created_by'] = $request->user()?->id ?? 1;

        $contact = Contact::create($validated);
        return response()->json($contact->load(['contactType', 'assignee']), 201);
    }

    public function update(Request $request, Contact $contact)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'contact_type_id' => 'nullable|integer',
            'country_id' => 'nullable|integer|exists:countries,id',
            'state_id' => 'nullable|integer|exists:states,id',
            'district_id' => 'nullable|integer|exists:districts,id',
            'assigned_to' => 'nullable|integer|exists:users,id',
        ]);

        $contact->update($validated);
        return response()->json($contact->load(['contactType', 'assignee']));
    }

    public function destroy(Contact $contact)
    {
        $contact->delete();
        return response()->json(['message' => 'Contact deleted successfully']);
    }
}
