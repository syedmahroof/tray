<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Enquiry;
use Illuminate\Http\Request;

class EnquiryController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->input('search', ''));

        $enquiries = Enquiry::query()
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
            ->paginate(15);

        return response()->json($enquiries);
    }

    public function show(Enquiry $enquiry)
    {
        $enquiry->load(['customer', 'contact', 'project', 'product', 'assignee', 'branch']);

        return response()->json([
            'enquiry' => $enquiry,
            'notes' => $enquiry->notes()->with('user')->latest()->get(),
            'reminders' => $enquiry->reminders()->with('user')->orderBy('remind_at')->get(),
            'quotations' => $enquiry->quotations()->latest()
                ->get(['id', 'number', 'version', 'status', 'total', 'quotation_date', 'created_at']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'nullable|integer|exists:customers,id',
            'contact_id' => 'required|integer|exists:contacts,id',
            'project_id' => 'nullable|integer',
            'product_id' => 'nullable|integer',
            'assigned_to' => 'nullable|integer|exists:users,id',
            'status' => 'required|string|in:new,in_progress,converted,lost',
            'source' => 'nullable|string',
            'remarks' => 'nullable|string',
        ]);

        $validated['branch_id'] = $request->user()?->branch_id ?? 1;

        $enquiry = Enquiry::create($validated);
        return response()->json($enquiry->load(['customer', 'contact', 'assignee']), 201);
    }

    public function update(Request $request, Enquiry $enquiry)
    {
        $validated = $request->validate([
            'customer_id' => 'nullable|integer|exists:customers,id',
            'contact_id' => 'required|integer|exists:contacts,id',
            'project_id' => 'nullable|integer',
            'product_id' => 'nullable|integer',
            'assigned_to' => 'nullable|integer|exists:users,id',
            'status' => 'required|string|in:new,in_progress,converted,lost',
            'source' => 'nullable|string',
            'remarks' => 'nullable|string',
        ]);

        $enquiry->update($validated);
        return response()->json($enquiry->load(['customer', 'contact', 'assignee']));
    }

    public function destroy(Enquiry $enquiry)
    {
        $enquiry->delete();
        return response()->json(['message' => 'Enquiry deleted successfully']);
    }
}
