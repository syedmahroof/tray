<?php

namespace App\Http\Requests\Admin;

use App\Models\Branch;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class SaveBranchRequest extends FormRequest
{
    /**
     * A blank next number leaves the branch's series where it is.
     */
    protected function prepareForValidation(): void
    {
        if (trim((string) $this->input('quotation_next_number')) === '') {
            $this->request->remove('quotation_next_number');
        }
    }

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
            'company_name' => ['nullable', 'string', 'max:255'],
            'logo' => ['nullable', File::image()->max(2 * 1024)],
            'remove_logo' => ['nullable', 'boolean'],
            'letterhead' => ['nullable', File::image()->max(2 * 1024)],
            'remove_letterhead' => ['nullable', 'boolean'],
            'phone' => ['nullable', 'string', 'max:50'],
            'mobile' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'gstin' => ['nullable', 'string', 'max:20'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'bank_account_number' => ['nullable', 'string', 'max:50'],
            'bank_branch' => ['nullable', 'string', 'max:255'],
            'bank_ifsc' => ['nullable', 'string', 'max:20'],
            'quotation_terms' => ['nullable', 'string', 'max:5000'],
            'quotation_prefix' => ['nullable', 'string', 'max:50'],
            'quotation_next_number' => ['sometimes', 'integer', 'min:1', 'max:99999999'],
            'brands' => ['nullable', 'array'],
            'brands.*' => [Rule::exists('brands', 'id')],
        ];
    }
}
