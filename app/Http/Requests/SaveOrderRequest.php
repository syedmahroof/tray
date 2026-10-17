<?php

namespace App\Http\Requests;

use App\Models\Customer;
use App\Models\Order;
use App\Support\BranchAccess;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $isPurchase = $this->orderType() === 'purchase';

        return [
            'customer_id' => [Rule::excludeIf($isPurchase), 'required', Rule::exists('customers', 'id')],
            'supplier_id' => [Rule::excludeIf(! $isPurchase), 'required', Rule::exists('suppliers', 'id')],
            'gstin' => ['nullable', 'string', 'max:20'],
            'supply_type' => ['required', Rule::in(Order::SUPPLY_TYPES)],
            'order_date' => ['required', 'date'],
            'expected_date' => ['nullable', 'date'],
            'status' => ['required', Rule::in(Order::STATUSES)],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'terms' => ['nullable', 'string'],
            'branch_id' => [
                Rule::requiredIf(BranchAccess::canChooseBranch()), 'nullable',
                Rule::in(BranchAccess::options()->modelKeys()),
            ],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['nullable', Rule::exists('products', 'id')],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.hsn_code' => ['nullable', 'string', 'max:20'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.tax_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    /**
     * Apply validation that depends on the selected branch.
     *
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $customerId = $this->integer('customer_id');

            if (! $customerId) {
                return;
            }

            $branchId = $this->integer('branch_id') ?: $this->user()?->branch_id;

            if ($branchId && ! Customer::query()->whereKey($customerId)->where('branch_id', $branchId)->exists()) {
                $validator->errors()->add('customer_id', __('The selected customer is not available in this branch.'));
            }
        }];
    }

    private function orderType(): string
    {
        return $this->segment(1) === 'purchase-orders' ? 'purchase' : 'sale';
    }
}
