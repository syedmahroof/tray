<?php

namespace App\Mcp\Tools;

use App\Http\Controllers\Concerns\ComputesOrderTotals;
use App\Http\Controllers\Concerns\ComputesQuotationTotals;
use App\Http\Requests\SaveOrderRequest;
use App\Http\Requests\SaveQuotationRequest;
use App\Models;
use App\Support\BranchAccess;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;

#[IsDestructive(false)]
#[IsOpenWorld(false)]
class CreateDocument extends Tool
{
    use ComputesOrderTotals;
    use ComputesQuotationTotals;

    public function __construct(private string $document)
    {
        $this->name = 'create_'.$document;
        $this->description = 'Create a '.str_replace('_', ' ', $document).' with validated line items and calculated GST totals. Defaults to draft and today. Use listing tools to resolve related IDs. Requires create permission. This operation is not idempotent.';
    }

    public function handle(Request $request): Response
    {
        $module = match ($this->document) {
            'quotation' => 'quotations',
            'sales_order' => 'sales-orders',
            'purchase_order' => 'purchase-orders',
            default => throw new \InvalidArgumentException('Unsupported document type.'),
        };
        $user = $request->user();
        if (! $user instanceof Models\User || Gate::denies($module.'.create')) {
            return Response::error('You do not have permission to create this document.');
        }

        $isQuotation = $this->document === 'quotation';
        $dateField = $isQuotation ? 'quotation_date' : 'order_date';
        $arguments = array_replace([
            'status' => 'draft', 'supply_type' => 'intra', $dateField => now()->toDateString(),
            'branch_id' => $user->branch_id,
        ], $request->all());

        /** @var SaveQuotationRequest|SaveOrderRequest $form */
        $form = FormRequest::createFrom(HttpRequest::create('/'.$module, 'POST', $arguments), $isQuotation ? new SaveQuotationRequest : new SaveOrderRequest);
        $form->setContainer(app())->setRedirector(app('redirect'));
        $form->setUserResolver(fn (): Models\User => $user);
        $form->validateResolved();
        $data = $form->validated();
        $this->validateReferences($data);

        $record = DB::transaction(function () use ($data, $isQuotation, $user): Models\Quotation|Models\Order {
            $computed = $isQuotation
                ? $this->computeQuotationTotals(
                    $data['items'], (float) ($data['discount'] ?? 0), $data['supply_type'],
                    (float) ($data['agent_commission'] ?? 0), (float) ($data['freight'] ?? 0),
                    (float) ($data['freight_tax_percent'] ?? Models\Quotation::DEFAULT_FREIGHT_TAX_PERCENT),
                )
                : $this->computeOrderTotals($data['items'], (float) ($data['discount'] ?? 0), $data['supply_type']);
            $attributes = [
                ...Arr::except($data, ['items']), 'number' => 'TEMP',
                ...$computed['totals'], 'created_by' => $user->id,
            ];
            if ($isQuotation) {
                $record = Models\Quotation::create($attributes);
                $number = $this->generateQuotationNumber($record);
            } else {
                $record = Models\Order::create([...$attributes, 'type' => $this->document === 'sales_order' ? 'sale' : 'purchase']);
                $prefix = $record->type === 'sale' ? 'SO' : 'PO';
                $number = $prefix.'-'.now()->format('Y').'-'.str_pad((string) $record->id, 5, '0', STR_PAD_LEFT);
            }
            $record->update(['number' => $number]);
            $record->items()->createMany($computed['rows']);

            return $record->load('items');
        });

        return Response::json(['document' => $record]);
    }

    /** @param array<string, mixed> $data */
    private function validateReferences(array $data): void
    {
        $branchId = $data['branch_id'] ?? null;
        if (! $branchId || ! BranchAccess::options()->contains('id', $branchId)) {
            throw ValidationException::withMessages(['branch_id' => 'Select a branch you can access.']);
        }
        foreach ([
            'customer_id' => Models\Customer::class, 'contact_id' => Models\Contact::class,
            'project_id' => Models\Project::class, 'enquiry_id' => Models\Enquiry::class,
            'builder_id' => Models\Builder::class, 'supplier_id' => Models\Supplier::class,
        ] as $field => $model) {
            if (empty($data[$field])) {
                continue;
            }
            $related = $model::query()->whereKey($data[$field])->first();
            if (! $related || ($related->getAttribute('branch_id') && (int) $related->getAttribute('branch_id') !== (int) $branchId)) {
                throw ValidationException::withMessages([$field => 'The selected record is not available in this branch.']);
            }
            if ($related instanceof Models\Supplier && ! $related->is_active) {
                throw ValidationException::withMessages([$field => 'Select an active supplier.']);
            }
        }
        $productIds = array_values(array_unique(array_filter(array_column($data['items'], 'product_id'))));
        if (Models\Product::query()->selectable()->whereKey($productIds)->count() !== count($productIds)) {
            throw ValidationException::withMessages(['items' => 'One or more products are inactive or unavailable to you.']);
        }
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        $isQuotation = $this->document === 'quotation';
        $fields = [
            'branch_id' => $schema->integer()->min(1)->description('Defaults to your branch. Must be accessible to you.'),
            'supply_type' => $schema->string()->enum(['intra', 'inter'])->default('intra'),
            'status' => $schema->string()->enum($isQuotation ? Models\Quotation::STATUSES : Models\Order::STATUSES)->default('draft'),
            ($isQuotation ? 'quotation_date' : 'order_date') => $schema->string()->description('YYYY-MM-DD; defaults to today.'),
            ($isQuotation ? 'valid_until' : 'expected_date') => $schema->string()->description('YYYY-MM-DD'),
            'gstin' => $schema->string()->max(20),
            'discount' => $schema->number()->min(0),
            'notes' => $schema->string(),
            'terms' => $schema->string(),
        ];
        foreach ($isQuotation ? ['customer_id', 'contact_id', 'project_id', 'enquiry_id', 'builder_id'] : [$this->document === 'purchase_order' ? 'supplier_id' : 'customer_id'] as $field) {
            $fields[$field] = $schema->integer()->min(1)->description('An accessible related record ID.');
            if (! $isQuotation) {
                $fields[$field]->required();
            }
        }
        $item = [
            'product_id' => $schema->integer()->min(1),
            'description' => $schema->string()->max(255)->required(),
            'hsn_code' => $schema->string()->max(20),
            'quantity' => $schema->number()->min(0.01)->required(),
            'unit_price' => $schema->number()->min(0)->required(),
            'tax_percentage' => $schema->number()->min(0)->max(100),
        ];
        if ($isQuotation) {
            $fields['customer_id'] = $schema->integer()->min(1)->description('Link at least one customer, contact, project, enquiry or builder.');
            foreach (['agent_commission', 'freight', 'freight_tax_percent'] as $field) {
                $fields[$field] = $schema->number()->min(0);
            }
            $fields['freight_tax_percent']->max(100);
            $fields['site_address'] = $schema->string()->max(1000);
            $fields['details'] = $schema->string()->max(2000);
            $fields['rate_tier'] = $schema->string()->enum(array_keys(Models\ProductBranchPrice::RATE_TIERS));
            $item['rate_tier'] = $schema->string()->enum(array_keys(Models\QuotationItem::rateTiers()));
            $item['price_basis'] = $schema->string()->enum(array_keys(Models\ProductBranchPrice::RATE_BASES));
            $item['list_price'] = $schema->number()->min(0);
            $item['price_percent'] = $schema->number()->min(0)->max(9999);
        }
        $fields['items'] = $schema->array()->items($schema->object($item))->min(1)->required();

        return $fields;
    }
}
