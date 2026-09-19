<?php

namespace App\Models;

use Database\Factories\BranchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $code
 * @property string|null $address
 * @property string|null $city
 * @property string|null $bank_name
 * @property string|null $bank_account_number
 * @property string|null $bank_branch
 * @property string|null $bank_ifsc
 * @property bool $is_active
 */
#[Fillable([
    'name', 'code', 'address', 'city', 'is_active',
    'bank_name', 'bank_account_number', 'bank_branch', 'bank_ifsc',
])]
class Branch extends Model
{
    /** @use HasFactory<BranchFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * The bank details to print on this branch's paperwork, or null when it
     * banks under the company's own account.
     *
     * @return array{name: string, account: string|null, branch_ifsc: string|null}|null
     */
    public function bankDetails(): ?array
    {
        if (blank($this->bank_name)) {
            return null;
        }

        $branchAndIfsc = array_filter([$this->bank_branch, $this->bank_ifsc]);

        return [
            'name' => $this->bank_name,
            'account' => $this->bank_account_number,
            'branch_ifsc' => $branchAndIfsc === [] ? null : implode(' & ', $branchAndIfsc),
        ];
    }

    /**
     * Get the users that belong to the branch.
     *
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
