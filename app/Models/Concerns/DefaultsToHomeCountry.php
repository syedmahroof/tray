<?php

namespace App\Models\Concerns;

use App\Models\Country;

trait DefaultsToHomeCountry
{
    /**
     * Boot the trait: a record saved without a country is placed in the
     * CRM's home country, India.
     */
    protected static function bootDefaultsToHomeCountry(): void
    {
        static::creating(function ($model) {
            if (blank($model->country_id)) {
                $model->country_id = Country::homeId();
            }
        });
    }
}
