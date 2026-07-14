<?php

namespace App\Http\Requests\Admin;

use App\Support\BranchAccess;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveProductRequest extends FormRequest
{
    /**
     * Treat an empty brand selection as "no brand".
     */
    protected function prepareForValidation(): void
    {
        if ($this->input('brand_id') === '') {
            $this->merge(['brand_id' => null]);
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
            'name' => ['required', 'string', 'max:255'],
            'hsn_code' => ['nullable', 'string', 'max:20'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'taxable_amount' => ['nullable', 'numeric', 'min:0'],
            'tax_type' => ['nullable', 'string', 'max:50'],
            'tax_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'area_sqft' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'branch_id' => [
                Rule::requiredIf(BranchAccess::canChooseBranch()), 'nullable',
                Rule::exists('branches', 'id'),
            ],
        ];
    }
}
