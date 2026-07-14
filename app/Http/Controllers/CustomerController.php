<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveCustomerRequest;
use App\Models\Country;
use App\Models\Customer;
use App\Models\User;
use App\Support\BranchAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    /**
     * Display a listing of customers.
     */
    public function index(Request $request): Response
    {
        $search = trim((string) $request->input('search', ''));

        return Inertia::render('customers/Index', [
            'customers' => Customer::query()
                ->with(['assignee'])
                ->when($search !== '', function ($query) use ($search) {
                    $query->where(function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
                })
                ->orderBy('name')
                ->paginate(15)
                ->withQueryString(),
            'filters' => ['search' => $search],
        ]);
    }

    /**
     * Show the form for creating a new customer.
     */
    public function create(): Response
    {
        return Inertia::render('customers/Create', [
            'countries' => Country::query()->orderBy('name')->get(['id', 'name']),
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
            'branches' => BranchAccess::canChooseBranch() ? BranchAccess::options() : [],
        ]);
    }

    /**
     * Store a newly created customer.
     */
    public function store(SaveCustomerRequest $request): RedirectResponse
    {
        $customer = Customer::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Customer created.')]);

        return to_route('customers.show', $customer);
    }

    /**
     * Display the given customer.
     */
    public function show(Customer $customer): Response
    {
        $customer->load(['country', 'state', 'district', 'assignee', 'branch']);

        return Inertia::render('customers/Show', [
            'customer' => $customer,
            'quotations' => $customer->quotations()->latest()->get(['id', 'number', 'version', 'status', 'total', 'quotation_date', 'created_at']),
            'enquiries' => $customer->enquiries()->with(['contact', 'project'])->latest()->get(),
        ]);
    }

    /**
     * Show the form for editing the given customer.
     */
    public function edit(Customer $customer): Response
    {
        return Inertia::render('customers/Edit', [
            'customer' => $customer,
            'countries' => Country::query()->orderBy('name')->get(['id', 'name']),
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
            'branches' => BranchAccess::canChooseBranch() ? BranchAccess::options() : [],
        ]);
    }

    /**
     * Update the given customer.
     */
    public function update(SaveCustomerRequest $request, Customer $customer): RedirectResponse
    {
        $customer->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Customer updated.')]);

        return to_route('customers.show', $customer);
    }

    /**
     * Remove the given customer.
     */
    public function destroy(Customer $customer): RedirectResponse
    {
        $customer->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Customer deleted.')]);

        return to_route('customers.index');
    }
}
