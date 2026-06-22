<?php

namespace App\Support;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

class BranchAccess
{
    /**
     * Determine if the authenticated user may choose which branch a record belongs to.
     *
     * Admins may always choose; everyone else only when they have access to more
     * than one branch (otherwise the record is stamped with their only branch).
     */
    public static function canChooseBranch(): bool
    {
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        if ($user->hasAnyRole(['Super Admin', 'Admin'])) {
            return true;
        }

        return count($user->accessibleBranchIds()) > 1;
    }

    /**
     * Get the branches available for selection, scoped to what the user may access.
     *
     * @return Collection<int, Branch>
     */
    public static function options(): Collection
    {
        $user = Auth::user();

        $query = Branch::query()->orderBy('name');

        if ($user && ! $user->hasAnyRole(['Super Admin', 'Admin'])) {
            $query->whereIn('id', $user->accessibleBranchIds());
        }

        return $query->get(['id', 'name']);
    }
}
