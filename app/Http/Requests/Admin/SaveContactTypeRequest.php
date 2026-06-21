<?php

namespace App\Http\Requests\Admin;

use App\Models\ContactType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveContactTypeRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $contactType = $this->route('contact_type');

        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('contact_types', 'name')->ignore($contactType instanceof ContactType ? $contactType->id : null),
            ],
        ];
    }
}
