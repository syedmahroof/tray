<?php

use App\Models\Country;
use App\Models\District;
use App\Models\State;
use Database\Seeders\CountryStateDistrictSeeder;

test('it seeds india with its states and districts', function () {
    $this->seed(CountryStateDistrictSeeder::class);

    $india = Country::where('code', 'IN')->first();

    expect($india)->not->toBeNull();
    expect($india->name)->toBe('India');
    expect(State::where('country_id', $india->id)->count())->toBe(36);

    $karnataka = State::where('country_id', $india->id)->where('name', 'Karnataka')->first();

    expect($karnataka)->not->toBeNull();
    expect($karnataka->code)->toBe('KA');
    expect(District::where('state_id', $karnataka->id)->where('name', 'Bangalore Urban')->exists())->toBeTrue();
});

test('seeding twice does not create duplicate records', function () {
    $this->seed(CountryStateDistrictSeeder::class);
    $countryCount = Country::count();
    $stateCount = State::count();
    $districtCount = District::count();

    $this->seed(CountryStateDistrictSeeder::class);

    expect(Country::count())->toBe($countryCount);
    expect(State::count())->toBe($stateCount);
    expect(District::count())->toBe($districtCount);
});
