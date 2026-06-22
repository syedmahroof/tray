<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class SaveUserRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'string', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($user instanceof User ? $user->id : null),
            ],
            'password' => [$this->isMethod('post') ? 'required' : 'nullable', 'string', Password::default(), 'confirmed'],
            'branches' => ['nullable', 'array'],
            'branches.*' => [Rule::exists('branches', 'id')],
            'brands' => ['nullable', 'array'],
            'brands.*' => [Rule::exists('brands', 'id')],
            'role' => ['required', 'string', Rule::exists('roles', 'name')],
        ];
    }
}
