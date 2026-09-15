<?php

namespace App\Http\Requests\Admin;

use App\Models\Brand;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class SaveBrandRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $brand = $this->route('brand');

        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('brands', 'name')->ignore($brand instanceof Brand ? $brand->id : null),
            ],
            'logo' => ['nullable', File::image()->max(2 * 1024)],
            'remove_logo' => ['nullable', 'boolean'],
        ];
    }
}
