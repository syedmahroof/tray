<?php

namespace App\Http\Controllers;

use App\Http\Requests\Admin\SaveDistrictRequest;
use App\Http\Requests\Admin\SaveLocationRequest;
use App\Http\Requests\Admin\SaveStateRequest;
use App\Models\Country;
use App\Models\District;
use App\Models\Location;
use App\Models\State;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocationLookupController extends Controller
{
    /**
     * List the states for the given country, for populating cascading selects.
     */
    public function states(Request $request): JsonResponse
    {
        $states = State::query()
            ->where('country_id', $request->integer('country_id'))
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json($states);
    }

    /**
     * List the districts for the given state, for populating cascading selects.
     */
    public function districts(Request $request): JsonResponse
    {
        $districts = District::query()
            ->where('state_id', $request->integer('state_id'))
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json($districts);
    }

    /**
     * List the locations for the given district, for populating cascading selects.
     */
    public function locations(Request $request): JsonResponse
    {
        $locations = Location::query()
            ->where('district_id', $request->integer('district_id'))
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json($locations);
    }

    /**
     * Store a state from a cascading select, returning it for the picker.
     */
    public function storeState(SaveStateRequest $request, Country $country): JsonResponse
    {
        $state = $country->states()->create($request->validated());

        return response()->json($state->only(['id', 'name']), 201);
    }

    /**
     * Store a district from a cascading select, returning it for the picker.
     */
    public function storeDistrict(SaveDistrictRequest $request, State $state): JsonResponse
    {
        $district = $state->districts()->create($request->validated());

        return response()->json($district->only(['id', 'name']), 201);
    }

    /**
     * Store a location from a cascading select, returning it for the picker.
     */
    public function storeLocation(SaveLocationRequest $request, District $district): JsonResponse
    {
        $location = $district->locations()->create([
            ...$request->validated(),
            'is_active' => true,
        ]);

        return response()->json($location->only(['id', 'name']), 201);
    }
}
