<?php

namespace App\Http\Requests;

use App\Support\BranchAccess;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveContactRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'contact_type_id' => ['required', Rule::exists('contact_types', 'id')],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'country_id' => ['nullable', Rule::exists('countries', 'id')],
            'state_id' => ['nullable', Rule::exists('states', 'id')],
            'district_id' => ['nullable', Rule::exists('districts', 'id')],
            'assigned_to' => ['nullable', Rule::exists('users', 'id')],
            'branch_id' => [
                Rule::requiredIf(BranchAccess::canChooseBranch()), 'nullable',
                Rule::exists('branches', 'id'),
            ],
        ];
    }
}
