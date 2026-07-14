<?php

namespace App\Http\Requests;

use App\Models\Quotation;
use App\Support\BranchAccess;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveQuotationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'customer_id' => ['required', Rule::exists('customers', 'id')],
            'contact_id' => ['nullable', Rule::exists('contacts', 'id')],
            'project_id' => ['nullable', Rule::exists('projects', 'id')],
            'enquiry_id' => ['nullable', Rule::exists('enquiries', 'id')],
            'builder_id' => ['nullable', Rule::exists('builders', 'id')],
            'gstin' => ['nullable', 'string', 'max:20'],
            'supply_type' => ['required', Rule::in(Quotation::SUPPLY_TYPES)],
            'quotation_date' => ['required', 'date'],
            'valid_until' => ['nullable', 'date'],
            'status' => ['required', Rule::in(Quotation::STATUSES)],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'terms' => ['nullable', 'string'],
            'branch_id' => [
                Rule::requiredIf(BranchAccess::canChooseBranch()), 'nullable',
                Rule::exists('branches', 'id'),
            ],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['nullable', Rule::exists('products', 'id')],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.hsn_code' => ['nullable', 'string', 'max:20'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.tax_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
