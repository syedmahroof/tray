<?php

namespace App\Http\Requests\Admin;

use App\Models\ProductCategory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

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
            'image' => ['nullable', File::image()->max(2 * 1024)],
            'remove_image' => ['nullable', 'boolean'],
        ];
    }
}
