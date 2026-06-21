<?php

namespace App\Http\Requests\Admin;

use App\Support\BranchAccess;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveProductRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'product_category_id' => ['required', Rule::exists('product_categories', 'id')],
            'name' => ['required', 'string', 'max:255'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'area_sqft' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'branch_id' => [
                Rule::requiredIf(BranchAccess::canChooseBranch()), 'nullable',
                Rule::exists('branches', 'id'),
            ],
        ];
    }
}
