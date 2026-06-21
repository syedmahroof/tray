<?php

namespace App\Http\Requests;

use App\Models\Enquiry;
use App\Support\BranchAccess;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveEnquiryRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'contact_id' => ['required', Rule::exists('contacts', 'id')],
            'project_id' => ['nullable', Rule::exists('projects', 'id')],
            'product_id' => ['nullable', Rule::exists('products', 'id')],
            'assigned_to' => ['nullable', Rule::exists('users', 'id')],
            'status' => ['required', Rule::in(Enquiry::STATUSES)],
            'source' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string'],
            'branch_id' => [
                Rule::requiredIf(BranchAccess::canChooseBranch()), 'nullable',
                Rule::exists('branches', 'id'),
            ],
        ];
    }
}
