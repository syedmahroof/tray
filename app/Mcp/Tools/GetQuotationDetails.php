<?php

namespace App\Mcp\Tools;

use App\Models\Quotation;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class GetQuotationDetails extends Tool
{
    protected string $name = 'get_quotation_details';

    protected string $description = 'Get a quotation by ID, including linked parties, saved line items, GST totals, freight, discounts, notes, terms, branch, creator and revision history. Use list_quotations to find the ID. Requires quotations.view permission and branch access.';

    public function handle(Request $request): Response
    {
        if (! $request->user() instanceof User || Gate::denies('quotations.view')) {
            return Response::error('You do not have permission to view quotations.');
        }

        $validated = $request->validate(['quotation_id' => ['required', 'integer', 'min:1']]);
        $quotation = Quotation::query()->with([
            'customer', 'contact', 'project', 'enquiry', 'builder',
            'creator:id,name', 'branch:id,name,code,company_name,address,phone,email,gstin',
            'items.product:id,name,code,unit,hsn_code',
        ])->whereKey($validated['quotation_id'])->first();

        if (! $quotation) {
            return Response::error('Quotation not found or unavailable to you.');
        }

        $rootId = $quotation->rootId();
        $versions = Quotation::query()
            ->where(fn ($query) => $query->where('id', $rootId)->orWhere('parent_id', $rootId))
            ->orderBy('version')->orderBy('id')
            ->get(['id', 'number', 'version', 'status', 'total', 'created_at']);

        return Response::json(['quotation' => $quotation, 'versions' => $versions]);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return ['quotation_id' => $schema->integer()->min(1)->description('Quotation ID returned by list_quotations.')->required()];
    }
}
