<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * @implements Scope<Model>
 */
class BrandScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * Super Admins and Admins see every brand's records. Other users are
     * restricted to the brands assigned to them; a user with no assigned
     * brands is not restricted at all. Records without a brand stay visible
     * to everyone.
     */
    public function apply(Builder $builder, Model $model): void
    {
        $user = Auth::user();

        if (! $user || $user->hasAnyRole(['Super Admin', 'Admin'])) {
            return;
        }

        $brandIds = $user->accessibleBrandIds();

        if ($brandIds === []) {
            return;
        }

        $builder->where(function (Builder $query) use ($model, $brandIds) {
            $query->whereIn($model->qualifyColumn('brand_id'), $brandIds)
                ->orWhereNull($model->qualifyColumn('brand_id'));
        });
    }
}
