<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ComputesQuotationTotals;
use App\Http\Controllers\Controller;
use App\Models\Quotation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QuotationController extends Controller
{
    use ComputesQuotationTotals;

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
            'supply_type' => 'nullable|string|in:intra,inter',
            'quotation_date' => 'nullable|date',
            'valid_until' => 'nullable|date',
            'discount' => 'nullable|numeric|min:0',
            'status' => 'required|string|in:draft,sent,approved,rejected,expired',
            'notes' => 'nullable|string',
            'terms' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'nullable|exists:products,id',
            'items.*.description' => 'required|string|max:255',
            'items.*.hsn_code' => 'nullable|string|max:20',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.tax_percentage' => 'nullable|numeric|min:0|max:100',
        ]);

        $items = $validated['items'];
        $supplyType = $validated['supply_type'] ?? 'intra';
        $computed = $this->computeQuotationTotals($items, (float) ($validated['discount'] ?? 0), $supplyType);

        $quotation = DB::transaction(function () use ($request, $validated, $supplyType, $computed) {
            $quotation = Quotation::create([
                ...collect($validated)->except(['items', 'discount', 'supply_type'])->all(),
                'branch_id' => $request->user()?->branch_id ?? 1,
                'number' => 'TEMP',
                'version' => 1,
                'supply_type' => $supplyType,
                'quotation_date' => $validated['quotation_date'] ?? now(),
                'created_by' => $request->user()?->id ?? 1,
                ...$computed['totals'],
            ]);

            $quotation->update(['number' => $this->generateQuotationNumber($quotation->id)]);
            $quotation->items()->createMany($computed['rows']);

            return $quotation;
        });

        return response()->json($quotation->load(['customer', 'contact', 'items.product']), 201);
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
            'supply_type' => 'nullable|string|in:intra,inter',
            'quotation_date' => 'nullable|date',
            'valid_until' => 'nullable|date',
            'discount' => 'nullable|numeric|min:0',
            'status' => 'required|string|in:draft,sent,approved,rejected,expired',
            'notes' => 'nullable|string',
            'terms' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'nullable|exists:products,id',
            'items.*.description' => 'required|string|max:255',
            'items.*.hsn_code' => 'nullable|string|max:20',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.tax_percentage' => 'nullable|numeric|min:0|max:100',
        ]);

        $items = $validated['items'];
        $supplyType = $validated['supply_type'] ?? 'intra';
        $computed = $this->computeQuotationTotals($items, (float) ($validated['discount'] ?? 0), $supplyType);

        DB::transaction(function () use ($validated, $supplyType, $computed, $quotation) {
            $quotation->update([
                ...collect($validated)->except(['items', 'discount', 'supply_type'])->all(),
                'supply_type' => $supplyType,
                ...$computed['totals'],
            ]);

            $quotation->items()->delete();
            $quotation->items()->createMany($computed['rows']);
        });

        return response()->json($quotation->load(['customer', 'contact', 'items.product']));
    }

    public function destroy(Quotation $quotation)
    {
        $quotation->delete();

        return response()->json(['message' => 'Quotation deleted successfully']);
    }
}
