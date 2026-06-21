<?php

namespace App\Http\Requests\Admin;

use App\Models\Branch;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveBranchRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $branch = $this->route('branch');

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required', 'string', 'max:50',
                Rule::unique('branches', 'code')->ignore($branch instanceof Branch ? $branch->id : null),
            ],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
        ];
    }
}
