<?php

namespace App\Http\Requests\Admin;

use App\Models\District;
use App\Models\Location;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveLocationRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $district = $this->route('district');
        $location = $this->route('location');

        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('locations', 'name')
                    ->where('district_id', $district instanceof District ? $district->id : null)
                    ->ignore($location instanceof Location ? $location->id : null),
            ],
            'pincode' => ['nullable', 'string', 'max:10'],
        ];
    }
}
