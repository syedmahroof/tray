<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Quotation;
use Illuminate\Http\Request;

class QuotationController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->input('search', ''));
        $status = $request->input('status');
        $createdBy = $request->input('created_by');

        $quotations = Quotation::query()
            ->with(['customer', 'contact', 'project', 'creator'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('number', 'like', "%{$search}%")
                        ->orWhereHas('contact', fn ($sub) => $sub->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($createdBy, fn ($query) => $query->where('created_by', $createdBy))
            ->latest()
            ->paginate(15);

        return response()->json($quotations);
    }

    public function show(Quotation $quotation)
    {
        $quotation->load(['customer', 'contact', 'project', 'enquiry.contact:id,name', 'builder', 'creator', 'branch', 'items.product']);

        $rootId = $quotation->rootId();
        $versions = Quotation::query()
            ->where(fn ($query) => $query->where('id', $rootId)->orWhere('parent_id', $rootId))
            ->orderBy('version')
            ->get(['id', 'number', 'version', 'status', 'total', 'created_at']);

        return response()->json([
            'quotation' => $quotation,
            'versions' => $versions,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'required|integer',
            'contact_id' => 'nullable|integer',
            'project_id' => 'nullable|integer',
            'enquiry_id' => 'nullable|integer',
            'builder_id' => 'nullable|integer',
            'gstin' => 'nullable|string',
            'supply_type' => 'nullable|string',
            'quotation_date' => 'nullable|date',
            'valid_until' => 'nullable|date',
            'discount' => 'nullable|numeric',
            'total' => 'required|numeric',
            'status' => 'required|string|in:draft,sent,approved,rejected,expired',
            'notes' => 'nullable|string',
            'terms' => 'nullable|string',
        ]);

        $validated['branch_id'] = $request->user()?->branch_id ?? 1;
        $validated['number'] = $validated['number'] ?? ('QT-' . time() . '-' . rand(10, 99));
        $validated['version'] = 1;
        $validated['supply_type'] = $validated['supply_type'] ?? 'intra';
        $validated['quotation_date'] = $validated['quotation_date'] ?? now();
        $validated['created_by'] = $request->user()?->id ?? 1;
        $validated['discount'] = $validated['discount'] ?? 0;
        $validated['subtotal'] = $validated['total'] - $validated['discount'];
        $validated['tax_percent'] = 18;
        $validated['tax_amount'] = $validated['subtotal'] * 0.18;

        $quotation = Quotation::create($validated);
        return response()->json($quotation->load(['customer', 'contact']), 201);
    }

    public function update(Request $request, Quotation $quotation)
    {
        $validated = $request->validate([
            'customer_id' => 'required|integer',
            'contact_id' => 'nullable|integer',
            'project_id' => 'nullable|integer',
            'enquiry_id' => 'nullable|integer',
            'builder_id' => 'nullable|integer',
            'gstin' => 'nullable|string',
            'supply_type' => 'nullable|string',
            'quotation_date' => 'nullable|date',
            'valid_until' => 'nullable|date',
            'discount' => 'nullable|numeric',
            'total' => 'required|numeric',
            'status' => 'required|string|in:draft,sent,approved,rejected,expired',
            'notes' => 'nullable|string',
            'terms' => 'nullable|string',
        ]);

        $validated['discount'] = $validated['discount'] ?? 0;
        $validated['subtotal'] = $validated['total'] - $validated['discount'];
        $validated['tax_amount'] = $validated['subtotal'] * 0.18;

        $quotation->update($validated);
        return response()->json($quotation->load(['customer', 'contact']));
    }

    public function destroy(Quotation $quotation)
    {
        $quotation->delete();
        return response()->json(['message' => 'Quotation deleted successfully']);
    }
}
