<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * @implements Scope<Model>
 */
class BranchScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * Super Admins and Admins see records across every branch; everyone
     * else is restricted to the records belonging to their own branch.
     */
    public function apply(Builder $builder, Model $model): void
    {
        $user = Auth::user();

        if (! $user || $user->hasAnyRole(['Super Admin', 'Admin'])) {
            return;
        }

        $builder->where($model->qualifyColumn('branch_id'), $user->branch_id);
    }
}
