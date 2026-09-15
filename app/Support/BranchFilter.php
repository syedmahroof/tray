<?php

namespace App\Support;

use App\Models\Branch;
use Illuminate\Support\Collection;

class BranchFilter
{
    /**
     * Resolve the branches a comparison should show.
     *
     * The requested branches are narrowed to the ones the user may actually
     * see; asking for none (or only for branches they cannot access) falls
     * back to every branch available to them.
     *
     * @param  list<int|string>|null  $requested
     * @return Collection<int, Branch>
     */
    public static function resolve(?array $requested): Collection
    {
        $available = BranchAccess::options();

        if ($requested === null || $requested === []) {
            return $available;
        }

        $ids = array_map('intval', $requested);
        $selected = $available->whereIn('id', $ids);

        return $selected->isEmpty() ? $available : $selected->values();
    }
}
