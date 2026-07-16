<?php

namespace App\Http\Requests;

use App\Models\VisitReport;
use App\Support\BranchAccess;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveVisitReportRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'visit_date' => ['required', 'date'],
            'visit_type' => ['required', Rule::in(VisitReport::VISIT_TYPES)],
            'objective' => ['required', 'string', 'max:255'],
            'report' => ['nullable', 'string'],
            'next_meeting_date' => ['nullable', 'date'],
            'next_call_date' => ['nullable', 'date'],
            'project_ids' => ['array'],
            'project_ids.*' => [Rule::exists('projects', 'id')],
            'customer_ids' => ['array'],
            'customer_ids.*' => [Rule::exists('customers', 'id')],
            'contact_ids' => ['array'],
            'contact_ids.*' => [Rule::exists('contacts', 'id')],
            'builder_ids' => ['array'],
            'builder_ids.*' => [Rule::exists('builders', 'id')],
            'branch_id' => [
                Rule::requiredIf(BranchAccess::canChooseBranch()), 'nullable',
                Rule::exists('branches', 'id'),
            ],
        ];
    }

    /**
     * Configure additional validation: at least one entity must be linked.
     */
    public function withValidator(Validator $validator): void
    {
        logger()->info('SaveVisitReportRequest payload:', $this->all());
        $validator->after(function (Validator $validator) {
            $hasAnyEntity = filled($this->input('project_ids'))
                || filled($this->input('customer_ids'))
                || filled($this->input('contact_ids'))
                || filled($this->input('builder_ids'));

            if (! $hasAnyEntity) {
                $validator->errors()->add('project_ids', __('Select at least one project, customer, contact, or builder.'));
            }
        });
    }
}
