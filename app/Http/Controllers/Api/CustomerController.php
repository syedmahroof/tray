<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->input('search', ''));

        $customers = Customer::query()
            ->with(['assignee'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(15);

        return response()->json($customers);
    }

    public function show(Customer $customer)
    {
        $customer->load(['country', 'state', 'district', 'assignee', 'branch']);

        return response()->json([
            'customer' => $customer,
            'quotations' => $customer->quotations()->latest()->get(['id', 'number', 'version', 'status', 'total', 'quotation_date', 'created_at']),
            'enquiries' => $customer->enquiries()->with(['contact', 'project'])->latest()->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'gst_number' => 'nullable|string|max:50',
            'country_id' => 'nullable|integer|exists:countries,id',
            'state_id' => 'nullable|integer|exists:states,id',
            'district_id' => 'nullable|integer|exists:districts,id',
            'assigned_to' => 'nullable|integer|exists:users,id',
        ]);

        $validated['branch_id'] = $request->user()?->branch_id ?? 1;

        $customer = Customer::create($validated);
        return response()->json($customer->load(['assignee']), 201);
    }

    public function update(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'gst_number' => 'nullable|string|max:50',
            'country_id' => 'nullable|integer|exists:countries,id',
            'state_id' => 'nullable|integer|exists:states,id',
            'district_id' => 'nullable|integer|exists:districts,id',
            'assigned_to' => 'nullable|integer|exists:users,id',
        ]);

        $customer->update($validated);
        return response()->json($customer->load(['assignee']));
    }

    public function destroy(Customer $customer)
    {
        $customer->delete();
        return response()->json(['message' => 'Customer deleted successfully']);
    }
}
