<?php

namespace App\Http\Controllers;

use App\Models\District;
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
}
