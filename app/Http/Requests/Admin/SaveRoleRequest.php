<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class SaveRoleRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $role = $this->route('role');

        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('roles', 'name')->ignore($role instanceof Role ? $role->id : null),
            ],
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::exists('permissions', 'name')],
        ];
    }
}
