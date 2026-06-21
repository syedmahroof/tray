<?php

namespace App\Http\Requests\Admin;

use App\Models\ProductCategory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveProductCategoryRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $category = $this->route('product_category');

        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('product_categories', 'name')->ignore($category instanceof ProductCategory ? $category->id : null),
            ],
        ];
    }
}
