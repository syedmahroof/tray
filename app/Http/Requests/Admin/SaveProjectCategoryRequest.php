<?php

namespace App\Http\Requests\Admin;

use App\Models\ProjectCategory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveProjectCategoryRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $category = $this->route('project_category');

        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('project_categories', 'name')->ignore($category instanceof ProjectCategory ? $category->id : null),
            ],
        ];
    }
}
