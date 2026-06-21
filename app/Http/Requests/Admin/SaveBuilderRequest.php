<?php

namespace App\Http\Requests\Admin;

use App\Support\BranchAccess;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveBuilderRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'country_id' => ['nullable', Rule::exists('countries', 'id')],
            'state_id' => ['nullable', Rule::exists('states', 'id')],
            'district_id' => ['nullable', Rule::exists('districts', 'id')],
            'branch_id' => [
                Rule::requiredIf(BranchAccess::canChooseBranch()), 'nullable',
                Rule::exists('branches', 'id'),
            ],
        ];
    }
}
