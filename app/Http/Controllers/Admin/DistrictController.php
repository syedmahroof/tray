<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveDistrictRequest;
use App\Models\District;
use App\Models\State;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DistrictController extends Controller
{
    /**
     * Display a listing of districts for the given state.
     */
    public function index(Request $request, State $state): Response
    {
        $search = trim((string) $request->input('search', ''));

        return Inertia::render('admin/districts/Index', [
            'state' => $state->load('country'),
            'districts' => $state->districts()
                ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
                ->orderBy('name')
                ->paginate(15)
                ->withQueryString(),
            'filters' => ['search' => $search],
        ]);
    }

    /**
     * Show the form for creating a new district.
     */
    public function create(State $state): Response
    {
        return Inertia::render('admin/districts/Create', [
            'state' => $state->load('country'),
        ]);
    }

    /**
     * Store a newly created district.
     */
    public function store(SaveDistrictRequest $request, State $state): RedirectResponse
    {
        $state->districts()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('District created.')]);

        return to_route('states.districts.index', $state);
    }

    /**
     * Show the form for editing the given district.
     */
    public function edit(State $state, District $district): Response
    {
        return Inertia::render('admin/districts/Edit', [
            'state' => $state->load('country'),
            'district' => $district,
        ]);
    }

    /**
     * Update the given district.
     */
    public function update(SaveDistrictRequest $request, State $state, District $district): RedirectResponse
    {
        $district->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('District updated.')]);

        return to_route('states.districts.index', $state);
    }

    /**
     * Remove the given district.
     */
    public function destroy(State $state, District $district): RedirectResponse
    {
        $district->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('District deleted.')]);

        return to_route('states.districts.index', $state);
    }
}
