<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveLocationRequest;
use App\Models\District;
use App\Models\Location;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LocationController extends Controller
{
    /**
     * Display a listing of locations for the given district.
     */
    public function index(Request $request, District $district): Response
    {
        $search = trim((string) $request->input('search', ''));

        return Inertia::render('admin/locations/Index', [
            'district' => $district->load('state.country'),
            'locations' => $district->locations()
                ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
                ->orderBy('name')
                ->paginate(15)
                ->withQueryString(),
            'filters' => ['search' => $search],
        ]);
    }

    /**
     * Show the form for creating a new location.
     */
    public function create(District $district): Response
    {
        return Inertia::render('admin/locations/Create', [
            'district' => $district->load('state.country'),
        ]);
    }

    /**
     * Store a newly created location.
     */
    public function store(SaveLocationRequest $request, District $district): RedirectResponse
    {
        $district->locations()->create([
            ...$request->validated(),
            'is_active' => $request->boolean('is_active'),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Location created.')]);

        return to_route('districts.locations.index', $district);
    }

    /**
     * Show the form for editing the given location.
     */
    public function edit(District $district, Location $location): Response
    {
        return Inertia::render('admin/locations/Edit', [
            'district' => $district->load('state.country'),
            'location' => $location,
        ]);
    }

    /**
     * Update the given location.
     */
    public function update(SaveLocationRequest $request, District $district, Location $location): RedirectResponse
    {
        $location->update([
            ...$request->validated(),
            'is_active' => $request->boolean('is_active'),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Location updated.')]);

        return to_route('districts.locations.index', $district);
    }

    /**
     * Remove the given location.
     */
    public function destroy(District $district, Location $location): RedirectResponse
    {
        if ($this->isInUse($location)) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Cannot delete a location that is still in use.')]);

            return back();
        }

        $location->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Location deleted.')]);

        return to_route('districts.locations.index', $district);
    }

    /**
     * Determine whether any record still points at the given location.
     */
    private function isInUse(Location $location): bool
    {
        return $location->builders()->exists()
            || $location->customers()->exists()
            || $location->contacts()->exists()
            || $location->projects()->exists()
            || $location->visitReports()->exists();
    }
}
