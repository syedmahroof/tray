<?php

namespace App\Http\Requests\Admin;

use App\Models\Project;
use App\Support\BranchAccess;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveProjectRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'builder_id' => ['required', Rule::exists('builders', 'id')],
            'project_category_id' => ['required', Rule::exists('project_categories', 'id')],
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'country_id' => ['nullable', Rule::exists('countries', 'id')],
            'state_id' => ['nullable', Rule::exists('states', 'id')],
            'district_id' => ['nullable', Rule::exists('districts', 'id')],
            'status' => ['required', Rule::in(Project::STATUSES)],
            'description' => ['nullable', 'string'],
            'branch_id' => [
                Rule::requiredIf(BranchAccess::canChooseBranch()), 'nullable',
                Rule::exists('branches', 'id'),
            ],
            'owner_name' => ['nullable', 'string', 'max:255'],
            'owner_phone' => ['nullable', 'string', 'max:255'],
            'owner_email' => ['nullable', 'email', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'pincode' => ['nullable', 'string', 'max:255'],
            'expected_maturity' => ['nullable', 'date'],
            'preferred_material' => ['nullable', 'string', 'max:255'],
            'assignee_id' => ['nullable', Rule::exists('users', 'id')],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'contacts' => ['nullable', 'array'],
            'contacts.*' => ['required', Rule::exists('contacts', 'id')],
            'project_contacts' => ['nullable', 'array'],
            'project_contacts.*.name' => ['required', 'string', 'max:255'],
            'project_contacts.*.role' => ['nullable', 'string', 'max:255'],
            'project_contacts.*.phone' => ['nullable', 'string', 'max:255'],
            'project_contacts.*.email' => ['nullable', 'email', 'max:255'],
        ];
    }
}
