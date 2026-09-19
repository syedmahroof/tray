<?php

namespace App\Http\Requests\Admin;

use App\Models\Route;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveRouteRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $route = $this->route('route');

        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('routes', 'name')->ignore($route instanceof Route ? $route->id : null),
            ],
        ];
    }
}
