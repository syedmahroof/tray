<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveRoleRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    /**
     * Display a listing of roles.
     */
    public function index(Request $request): Response
    {
        $search = trim((string) $request->input('search', ''));

        return Inertia::render('admin/roles/Index', [
            'roles' => Role::query()
                ->withCount(['permissions', 'users'])
                ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
                ->orderBy('name')
                ->paginate(15)
                ->withQueryString(),
            'filters' => ['search' => $search],
        ]);
    }

    /**
     * Show the form for creating a new role.
     */
    public function create(): Response
    {
        return Inertia::render('admin/roles/Create', [
            'permissionGroups' => $this->permissionGroups(),
        ]);
    }

    /**
     * Store a newly created role.
     */
    public function store(SaveRoleRequest $request): RedirectResponse
    {
        $role = Role::create(['name' => $request->validated('name')]);
        $role->syncPermissions($request->validated('permissions', []));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role created.')]);

        return to_route('roles.index');
    }

    /**
     * Show the form for editing the given role.
     */
    public function edit(Role $role): Response
    {
        return Inertia::render('admin/roles/Edit', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name'),
            ],
            'permissionGroups' => $this->permissionGroups(),
        ]);
    }

    /**
     * Update the given role.
     */
    public function update(SaveRoleRequest $request, Role $role): RedirectResponse
    {
        $role->update(['name' => $request->validated('name')]);
        $role->syncPermissions($request->validated('permissions', []));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role updated.')]);

        return to_route('roles.index');
    }

    /**
     * Remove the given role.
     */
    public function destroy(Role $role): RedirectResponse
    {
        if ($role->users()->exists()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Cannot delete a role that still has users assigned to it.')]);

            return back();
        }

        $role->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role deleted.')]);

        return to_route('roles.index');
    }

    /**
     * Group every available permission by its resource name.
     *
     * @return Collection<string, Collection<int, string>>
     */
    private function permissionGroups(): Collection
    {
        return Permission::query()
            ->orderBy('id')
            ->get()
            ->groupBy(fn (Permission $permission): string => Str::before($permission->name, '.'))
            ->map(fn (Collection $permissions): Collection => $permissions
                ->map(fn (Permission $permission): string => $permission->name)
                ->values());
    }
}
