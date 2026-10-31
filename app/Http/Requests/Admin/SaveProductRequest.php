<?php

namespace App\Http\Requests\Admin;

use App\Models\ProductBranchPrice;
use App\Support\BranchAccess;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class SaveProductRequest extends FormRequest
{
    /**
     * Treat an empty brand selection as "no brand", and an empty code as
     * "generate one for me".
     */
    protected function prepareForValidation(): void
    {
        if ($this->input('brand_id') === '') {
            $this->merge(['brand_id' => null]);
        }

        if (trim((string) $this->input('code')) === '') {
            $this->merge(['code' => null]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'product_category_id' => ['required', Rule::exists('product_categories', 'id')],
            'brand_id' => ['nullable', Rule::exists('brands', 'id')],
            'code' => [
                'nullable', 'string', 'max:50',
                Rule::unique('products', 'code')->ignore($this->route('product')),
            ],
            'name' => ['required', 'string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:20'],
            'hsn_code' => ['nullable', 'string', 'max:20'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'taxable_amount' => ['nullable', 'numeric', 'min:0'],
            'tax_type' => ['nullable', 'string', 'max:50'],
            'tax_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'area_sqft' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', File::image()->max(2 * 1024)],
            'remove_image' => ['nullable', 'boolean'],

            'price_reason' => ['nullable', 'string', 'max:255'],
            'prices' => ['sometimes', 'array'],
            'prices.*.branch_id' => [
                'required', 'distinct',
                Rule::exists('branches', 'id'),
                Rule::in(BranchAccess::options()->pluck('id')->all()),
            ],
            'prices.*.remove' => ['nullable', 'boolean'],
            'prices.*.cost' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'prices.*.mrp' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'prices.*.rate_basis' => ['nullable', Rule::in(array_keys(ProductBranchPrice::RATE_BASES))],
            ...$this->tierRules(),
        ];
    }

    /**
     * Validation for the three rate tiers a branch price carries.
     *
     * @return array<string, list<string>>
     */
    private function tierRules(): array
    {
        $rules = [];

        foreach (['sr', 'pr', 'cr'] as $tier) {
            $rules["prices.*.{$tier}_discount"] = ['nullable', 'numeric', 'min:0', 'max:999.99'];
            $rules["prices.*.{$tier}_rate"] = ['nullable', 'numeric', 'min:0', 'max:9999999999'];
            $rules["prices.*.{$tier}_rate_with_tax"] = ['nullable', 'numeric', 'min:0', 'max:9999999999'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'prices.*.branch_id.distinct' => __('Each branch can only be priced once.'),
            'prices.*.branch_id.in' => __('You cannot price a branch you do not have access to.'),
        ];
    }
}
