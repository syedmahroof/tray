<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveCountryRequest;
use App\Models\Country;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CountryController extends Controller
{
    /**
     * Display a listing of countries.
     */
    public function index(Request $request): Response
    {
        $search = trim((string) $request->input('search', ''));

        return Inertia::render('admin/countries/Index', [
            'countries' => Country::query()
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
     * Show the form for creating a new country.
     */
    public function create(): Response
    {
        return Inertia::render('admin/countries/Create');
    }

    /**
     * Store a newly created country.
     */
    public function store(SaveCountryRequest $request): RedirectResponse
    {
        Country::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Country created.')]);

        return to_route('countries.index');
    }

    /**
     * Show the form for editing the given country.
     */
    public function edit(Country $country): Response
    {
        return Inertia::render('admin/countries/Edit', [
            'country' => $country,
        ]);
    }

    /**
     * Update the given country.
     */
    public function update(SaveCountryRequest $request, Country $country): RedirectResponse
    {
        $country->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Country updated.')]);

        return to_route('countries.index');
    }

    /**
     * Remove the given country.
     */
    public function destroy(Country $country): RedirectResponse
    {
        if ($country->states()->exists()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Cannot delete a country that still has states.')]);

            return back();
        }

        $country->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Country deleted.')]);

        return to_route('countries.index');
    }
}
