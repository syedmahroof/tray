<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ComputesOrderTotals;
use App\Http\Requests\SaveOrderRequest;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Supplier;
use App\Support\BranchAccess;
use App\Support\OrderDocument;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    use ComputesOrderTotals;

    /**
     * How many products the line-item picker loads at a time.
     */
    private const int PRODUCT_PAGE_SIZE = 30;

    /**
     * Display a listing of sales or purchase orders.
     */
    public function index(Request $request): Response
    {
        $type = $this->orderType($request);
        $search = trim((string) $request->input('search', ''));
        $status = $request->input('status');

        $statusCounts = Order::query()->where('type', $type)
            ->groupBy('status')->selectRaw('status, count(*) as total')->pluck('total', 'status');

        return Inertia::render('orders/Index', [
            'orderType' => $type,
            'orders' => Order::query()->where('type', $type)->with(['customer', 'supplier', 'creator'])
                ->when($search !== '', function ($query) use ($search) {
                    $query->where(function ($subquery) use ($search) {
                        $subquery->where('number', 'like', "%{$search}%")
                            ->orWhereHas('customer', fn ($customer) => $customer->where('name', 'like', "%{$search}%"))
                            ->orWhereHas('supplier', fn ($supplier) => $supplier->where('name', 'like', "%{$search}%"));
                    });
                })
                ->when($status, fn ($query) => $query->where('status', $status))
                ->latest()->paginate(15)->withQueryString(),
            'statuses' => Order::statusesFor($type),
            'stats' => [
                'total' => (int) $statusCounts->sum(),
                ...array_fill_keys(Order::statusesFor($type), 0),
                ...$statusCounts->map(fn ($count): int => (int) $count)->all(),
            ],
            'filters' => ['search' => $search, 'status' => $status],
        ]);
    }

    /**
     * Show the form for creating an order.
     */
    public function create(Request $request): Response
    {
        $type = $this->orderType($request);

        return Inertia::render('orders/Create', [
            'orderType' => $type,
            ...$this->formData($type),
            'prefill' => ['customer_id' => $type === 'sale' ? ($request->integer('customer_id') ?: null) : null],
        ]);
    }

    /**
     * Store a newly created order.
     */
    public function store(SaveOrderRequest $request): RedirectResponse
    {
        $type = $this->orderType($request);
        $order = DB::transaction(function () use ($request, $type): Order {
            $computed = $this->computeOrder($request);
            $order = Order::create([
                ...$request->safe()->except(['items']),
                'type' => $type,
                'number' => 'TEMP',
                ...$computed['totals'],
                'created_by' => $request->user()->id,
            ]);
            $order->update(['number' => $this->generateNumber($order)]);
            $order->items()->createMany($computed['rows']);

            return $order;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Order created.')]);

        return to_route($this->resourceName($type).'.show', $order);
    }

    /**
     * Display the given order.
     */
    public function show(Request $request, Order $order): Response
    {
        $type = $this->orderType($request);
        $this->ensureType($order, $type);
        $order->load(['customer', 'supplier', 'creator', 'branch', 'items.product']);

        return Inertia::render('orders/Show', ['orderType' => $type, 'order' => $order, 'statuses' => Order::statusesFor($type)]);
    }

    /**
     * Show the form for editing the given order.
     */
    public function edit(Request $request, Order $order): Response
    {
        $type = $this->orderType($request);
        $this->ensureType($order, $type);
        $order->load('items.product');

        return Inertia::render('orders/Edit', ['orderType' => $type, 'order' => $order, ...$this->formData($type, $order)]);
    }

    /**
     * Update the given order.
     */
    public function update(SaveOrderRequest $request, Order $order): RedirectResponse
    {
        $type = $this->orderType($request);
        $this->ensureType($order, $type);

        DB::transaction(function () use ($request, $order): void {
            $computed = $this->computeOrder($request);
            $order->update([...$request->safe()->except(['items']), ...$computed['totals']]);
            $order->items()->delete();
            $order->items()->createMany($computed['rows']);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Order updated.')]);

        return to_route($this->resourceName($type).'.show', $order);
    }

    /**
     * Download the order as a PDF on the branch letterhead.
     */
    public function pdf(Request $request, Order $order): HttpResponse
    {
        $this->ensureType($order, $this->orderType($request));

        return OrderDocument::render($order, $request->getHost())->download("{$order->number}.pdf");
    }

    /**
     * Stream the order PDF inline so the browser can print it.
     */
    public function print(Request $request, Order $order): HttpResponse
    {
        $this->ensureType($order, $this->orderType($request));

        return OrderDocument::render($order, $request->getHost())->stream("{$order->number}.pdf");
    }

    /**
     * Update an order status from its detail page.
     */
    public function updateStatus(Request $request, Order $order): RedirectResponse
    {
        $type = $this->orderType($request);
        $this->ensureType($order, $type);
        $order->update($request->validate(['status' => ['required', Rule::in(Order::statusesFor($type))]]));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Order status updated.')]);

        return back();
    }

    /**
     * Remove the given order.
     */
    public function destroy(Request $request, Order $order): RedirectResponse
    {
        $type = $this->orderType($request);
        $this->ensureType($order, $type);
        $order->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Order deleted.')]);

        return to_route($this->resourceName($type).'.index');
    }

    /**
     * One page of active products for the line-item picker, matching what was
     * typed, so the form never has to load the whole catalogue.
     */
    public function products(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));

        $page = $this->productOptionsQuery()
            ->selectable()
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                foreach (preg_split('/\s+/', $search) as $term) {
                    $query->where(fn ($query) => $query
                        ->where('name', 'like', '%'.$term.'%')
                        ->orWhere('code', 'like', '%'.$term.'%'));
                }
            }))
            ->simplePaginate(self::PRODUCT_PAGE_SIZE);

        return response()->json([
            'data' => $page->items(),
            'next_page' => $page->hasMorePages() ? $page->currentPage() + 1 : null,
        ]);
    }

    /**
     * Products as the line-item picker reads them, alphabetically.
     *
     * @return EloquentBuilder<Product>
     */
    private function productOptionsQuery(): EloquentBuilder
    {
        return Product::query()
            ->with('brand:id,name')
            ->orderBy('name')
            ->select(['id', 'name', 'code', 'brand_id', 'image_path', 'unit', 'hsn_code', 'price', 'taxable_amount', 'tax_percentage']);
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(string $type, ?Order $order = null): array
    {
        return [
            'customers' => $type === 'sale' ? Customer::query()->orderBy('name')->get(['id', 'name', 'gst_number']) : [],
            // Inactive suppliers are offered only to keep the one an order already has.
            'suppliers' => $type === 'purchase'
                ? Supplier::query()
                    ->where(fn ($query) => $query->where('is_active', true)->when($order?->supplier_id, fn ($q, $id) => $q->orWhere('id', $id)))
                    ->orderBy('name')
                    ->get(['id', 'name', 'gst_number'])
                : [],
            // Only the products the order already uses; the picker searches for the rest.
            'products' => $order === null ? [] : $this->productOptionsQuery()
                ->whereKey($order->items->pluck('product_id')->filter()->all())
                ->get(),
            'statuses' => Order::statusesFor($type),
            'branches' => BranchAccess::canChooseBranch() ? BranchAccess::options() : [],
            'defaultBranchId' => request()->user()?->branch_id,
        ];
    }

    /**
     * @return array{rows: array<int, array<string, mixed>>, totals: array<string, float>}
     */
    private function computeOrder(SaveOrderRequest $request): array
    {
        return $this->computeOrderTotals(
            $request->validated('items', []),
            (float) $request->validated('discount', 0),
            $request->validated('supply_type', 'intra'),
        );
    }

    private function orderType(Request $request): string
    {
        $type = match ($request->segment(1)) {
            'sales-orders' => 'sale',
            'purchase-orders' => 'purchase',
            default => '',
        };
        abort_unless(in_array($type, Order::TYPES, true), 404);

        return $type;
    }

    private function ensureType(Order $order, string $type): void
    {
        abort_unless($order->type === $type, 404);
    }

    private function resourceName(string $type): string
    {
        return $type === 'sale' ? 'sales-orders' : 'purchase-orders';
    }

    /**
     * A purchase order raised by a branch with its own series takes the next
     * number in it; every other order is numbered company-wide.
     */
    private function generateNumber(Order $order): string
    {
        if ($order->type === 'purchase') {
            $branch = Branch::query()->whereKey($order->branch_id)->lockForUpdate()->first();

            if ($branch?->hasPurchaseOrderSeries()) {
                $next = max(1, $branch->purchase_order_next_number);

                while (Order::query()->withoutGlobalScopes()->where('number', $branch->purchaseOrderNumber($next))->exists()) {
                    $next++;
                }

                $branch->update(['purchase_order_next_number' => $next + 1]);

                return $branch->purchaseOrderNumber($next);
            }
        }

        $prefix = $order->type === 'sale' ? 'SO' : 'PO';

        return "{$prefix}-".now()->format('Y').'-'.str_pad((string) $order->id, 5, '0', STR_PAD_LEFT);
    }
}
