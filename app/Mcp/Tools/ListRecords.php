<?php

namespace App\Mcp\Tools;

use App\Models\User;
use App\Support\BranchAccess;
use App\Support\McpListings;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class ListRecords extends Tool
{
    public function __construct(private string $module)
    {
        $this->name = 'list_'.str_replace('-', '_', $module);
        $this->description = "List {$module} accessible to the signed-in user. Supports search, exact filters, ID lookup and pagination. Results are ordered newest ID first.";
    }

    public function handle(Request $request): Response
    {
        if (! $request->user() instanceof User || Gate::denies($this->module.'.view')) {
            return Response::error('You do not have permission to view these records.');
        }

        $definition = McpListings::definitions()[$this->module];
        $rules = ['id' => ['sometimes', 'integer', 'min:1'], 'search' => ['sometimes', 'string', 'max:255'], 'page' => ['sometimes', 'integer', 'min:1'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:100']];
        foreach ($definition['filters'] as $filter) {
            $rules[$filter] = match (true) {
                str_ends_with($filter, '_id'), in_array($filter, ['assigned_to', 'created_by'], true) => ['sometimes', 'integer', 'min:1'],
                $filter === 'is_active' => ['sometimes', 'boolean'],
                $filter === 'visit_date' => ['sometimes', 'date_format:Y-m-d'],
                default => ['sometimes', 'string', 'max:100'],
            };
        }
        $validated = $request->validate($rules);
        $query = $definition['model']::query();
        if (in_array($this->module, ['sales-orders', 'purchase-orders'], true)) {
            $query->where('type', $this->module === 'sales-orders' ? 'sale' : 'purchase');
        }
        if ($this->module === 'reminders') {
            $query->where('user_id', $request->user()->getAuthIdentifier());
        }
        if ($this->module === 'branches') {
            $query->whereIn('id', BranchAccess::options()->modelKeys());
        }
        if (isset($validated['id'])) {
            $query->whereKey($validated['id']);
        }
        if (($search = trim($validated['search'] ?? '')) !== '') {
            $query->where(function (Builder $query) use ($definition, $search): void {
                foreach ($definition['search'] as $column) {
                    $query->orWhere($column, 'like', '%'.$search.'%');
                }
            });
        }
        foreach ($definition['filters'] as $filter) {
            if (array_key_exists($filter, $validated)) {
                $query->where($filter, $validated[$filter]);
            }
        }
        $records = $query->orderByDesc('id')->paginate($validated['per_page'] ?? 15, $definition['columns'] ?? ['*'], 'page', $validated['page'] ?? 1);
        if ($this->module === 'products' && Gate::denies('products.price.view')) {
            $records->getCollection()->each->makeHidden(['price', 'taxable_amount']);
        }

        return Response::json([
            'data' => $records->items(),
            'current_page' => $records->currentPage(),
            'last_page' => $records->lastPage(),
            'per_page' => $records->perPage(),
            'total' => $records->total(),
        ]);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        $fields = [
            'id' => $schema->integer()->min(1)->description('Return a specific accessible record.'),
            'search' => $schema->string()->max(255),
            'page' => $schema->integer()->min(1)->default(1),
            'per_page' => $schema->integer()->min(1)->max(100)->default(15),
        ];
        foreach (McpListings::definitions()[$this->module]['filters'] as $filter) {
            $fields[$filter] = match (true) {
                str_ends_with($filter, '_id'), in_array($filter, ['assigned_to', 'created_by'], true) => $schema->integer()->min(1),
                $filter === 'is_active' => $schema->boolean(),
                default => $schema->string(),
            };
        }

        return $fields;
    }
}
