<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveUserRequest;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    /**
     * Display a listing of users.
     */
    public function index(Request $request): Response
    {
        $search = trim((string) $request->input('search', ''));

        return Inertia::render('admin/users/Index', [
            'users' => User::query()
                ->with('branch', 'roles')
                ->when($search !== '', function ($query) use ($search) {
                    $query->where(function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
                })
                ->orderBy('name')
                ->paginate(15)
                ->withQueryString(),
            'filters' => ['search' => $search],
        ]);
    }

    /**
     * Show the form for creating a new user.
     */
    public function create(): Response
    {
        return Inertia::render('admin/users/Create', [
            'branches' => Branch::query()->orderBy('name')->get(['id', 'name']),
            'brands' => Brand::query()->orderBy('name')->get(['id', 'name']),
            'roles' => Role::query()->orderBy('name')->pluck('name'),
        ]);
    }

    /**
     * Store a newly created user.
     */
    public function store(SaveUserRequest $request): RedirectResponse
    {
        $branchIds = $request->validated('branches', []);

        $user = User::create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => $request->validated('password'),
            'branch_id' => $branchIds[0] ?? null,
        ]);

        $user->syncRoles([$request->validated('role')]);
        $user->branches()->sync($branchIds);
        $user->brands()->sync($request->validated('brands', []));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User created.')]);

        return to_route('users.index');
    }

    /**
     * Show the form for editing the given user.
     */
    public function edit(User $user): Response
    {
        return Inertia::render('admin/users/Edit', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'branch_id' => $user->branch_id,
                'branch_ids' => $user->branches()->pluck('branches.id'),
                'brand_ids' => $user->brands()->pluck('brands.id'),
                'role' => $user->roles->pluck('name')->first(),
            ],
            'branches' => Branch::query()->orderBy('name')->get(['id', 'name']),
            'brands' => Brand::query()->orderBy('name')->get(['id', 'name']),
            'roles' => Role::query()->orderBy('name')->pluck('name'),
        ]);
    }

    /**
     * Update the given user.
     */
    public function update(SaveUserRequest $request, User $user): RedirectResponse
    {
        $branchIds = $request->validated('branches', []);

        $user->fill([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'branch_id' => $branchIds[0] ?? null,
        ]);

        if ($password = $request->validated('password')) {
            $user->password = $password;
        }

        $user->save();

        $user->syncRoles([$request->validated('role')]);
        $user->branches()->sync($branchIds);
        $user->brands()->sync($request->validated('brands', []));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User updated.')]);

        return to_route('users.index');
    }

    /**
     * Remove the given user.
     */
    public function destroy(User $user): RedirectResponse
    {
        if (auth()->user()->is($user)) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('You cannot delete your own account from here.')]);

            return back();
        }

        $user->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User deleted.')]);

        return to_route('users.index');
    }
}
