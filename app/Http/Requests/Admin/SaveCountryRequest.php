<?php

namespace App\Http\Requests\Admin;

use App\Models\Country;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCountryRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $country = $this->route('country');

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'nullable', 'string', 'max:10',
                Rule::unique('countries', 'code')->ignore($country instanceof Country ? $country->id : null),
            ],
        ];
    }
}
