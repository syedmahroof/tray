<?php

namespace App\Support;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

class BranchAccess
{
    /**
     * Determine if the authenticated user may choose which branch a record belongs to.
     */
    public static function canChooseBranch(): bool
    {
        return Auth::user()?->hasAnyRole(['Super Admin', 'Admin']) ?? false;
    }

    /**
     * Get the branches available for selection, for users who may choose one.
     *
     * @return Collection<int, Branch>
     */
    public static function options(): Collection
    {
        return Branch::query()->orderBy('name')->get(['id', 'name']);
    }
}
