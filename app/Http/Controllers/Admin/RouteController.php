<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveRouteRequest;
use App\Models\Route;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RouteController extends Controller
{
    /**
     * Display a listing of routes.
     */
    public function index(Request $request): Response
    {
        $search = trim((string) $request->input('search', ''));

        return Inertia::render('admin/routes/Index', [
            'routes' => Route::query()
                ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
                ->orderBy('name')
                ->paginate(15)
                ->withQueryString(),
            'filters' => ['search' => $search],
        ]);
    }

    /**
     * Store a newly created route.
     */
    public function store(SaveRouteRequest $request): RedirectResponse
    {
        Route::create([
            ...$request->validated(),
            'is_active' => $request->boolean('is_active'),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Route created.')]);

        return to_route('routes.index');
    }

    /**
     * Update the given route.
     */
    public function update(SaveRouteRequest $request, Route $route): RedirectResponse
    {
        $route->update([
            ...$request->validated(),
            'is_active' => $request->boolean('is_active'),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Route updated.')]);

        return to_route('routes.index');
    }

    /**
     * Remove the given route.
     */
    public function destroy(Route $route): RedirectResponse
    {
        if ($this->isInUse($route)) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Cannot delete a route that is still in use.')]);

            return back();
        }

        $route->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Route deleted.')]);

        return to_route('routes.index');
    }

    /**
     * Determine whether any record still points at the given route.
     */
    private function isInUse(Route $route): bool
    {
        return $route->builders()->exists()
            || $route->customers()->exists()
            || $route->contacts()->exists()
            || $route->projects()->exists()
            || $route->visitReports()->exists();
    }
}
