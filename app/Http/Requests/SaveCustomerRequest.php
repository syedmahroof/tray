<?php

namespace App\Http\Requests;

use App\Models\ProductBranchPrice;
use App\Support\BranchAccess;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCustomerRequest extends FormRequest
{
    /**
     * The price type select cannot carry an empty value, so "No default" is
     * submitted as "none" and stored as null.
     */
    protected function prepareForValidation(): void
    {
        if ($this->input('rate_tier') === 'none') {
            $this->merge(['rate_tier' => null]);
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
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'gst_number' => ['nullable', 'string', 'max:20'],
            'rate_tier' => ['nullable', Rule::in(array_keys(ProductBranchPrice::RATE_TIERS))],
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
