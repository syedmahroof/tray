<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveStateRequest;
use App\Models\Country;
use App\Models\State;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StateController extends Controller
{
    /**
     * Display a listing of states for the given country.
     */
    public function index(Request $request, Country $country): Response
    {
        $search = trim((string) $request->input('search', ''));

        return Inertia::render('admin/states/Index', [
            'country' => $country,
            'states' => $country->states()
                ->when($search !== '', function ($query) use ($search) {
                    $query->where(function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%");
                    });
                })
                ->orderBy('name')
                ->paginate(15)
                ->withQueryString(),
            'filters' => ['search' => $search],
        ]);
    }

    /**
     * Show the form for creating a new state.
     */
    public function create(Country $country): Response
    {
        return Inertia::render('admin/states/Create', [
            'country' => $country,
        ]);
    }

    /**
     * Store a newly created state.
     */
    public function store(SaveStateRequest $request, Country $country): RedirectResponse
    {
        $country->states()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('State created.')]);

        return to_route('countries.states.index', $country);
    }

    /**
     * Show the form for editing the given state.
     */
    public function edit(Country $country, State $state): Response
    {
        return Inertia::render('admin/states/Edit', [
            'country' => $country,
            'state' => $state,
        ]);
    }

    /**
     * Update the given state.
     */
    public function update(SaveStateRequest $request, Country $country, State $state): RedirectResponse
    {
        $state->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('State updated.')]);

        return to_route('countries.states.index', $country);
    }

    /**
     * Remove the given state.
     */
    public function destroy(Country $country, State $state): RedirectResponse
    {
        if ($state->districts()->exists()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Cannot delete a state that still has districts.')]);

            return back();
        }

        $state->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('State deleted.')]);

        return to_route('countries.states.index', $country);
    }
}
