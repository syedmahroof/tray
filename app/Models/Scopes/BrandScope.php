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
     * Super Admins and Admins see every brand's records. Everyone else sees
     * only records of the brands assigned to them and records they created
     * themselves.
     */
    public function apply(Builder $builder, Model $model): void
    {
        $user = Auth::user();

        if (! $user || $user->hasAnyRole(['Super Admin', 'Admin'])) {
            return;
        }

        $brandIds = $user->accessibleBrandIds();

        $builder->where(function (Builder $query) use ($model, $brandIds, $user) {
            $query->where($model->qualifyColumn('created_by'), $user->getKey())
                ->when($brandIds !== [], fn (Builder $query) => $query->orWhereIn($model->qualifyColumn('brand_id'), $brandIds));
        });
    }
}
