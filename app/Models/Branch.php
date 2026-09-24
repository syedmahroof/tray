<?php

namespace App\Models;

use App\Support\StoredImage;
use Database\Factories\BranchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $code
 * @property string|null $address
 * @property string|null $city
 * @property string|null $company_name
 * @property string|null $logo_path
 * @property-read string|null $logo_url
 * @property string|null $phone
 * @property string|null $mobile
 * @property string|null $email
 * @property string|null $website
 * @property string|null $gstin
 * @property string|null $bank_name
 * @property string|null $bank_account_number
 * @property string|null $bank_branch
 * @property string|null $bank_ifsc
 * @property string|null $quotation_terms
 * @property bool $is_active
 * @property-read Collection<int, Brand> $brands
 */
#[Fillable([
    'name', 'code', 'address', 'city', 'is_active',
    'company_name', 'logo_path', 'phone', 'mobile', 'email', 'website', 'gstin',
    'bank_name', 'bank_account_number', 'bank_branch', 'bank_ifsc', 'quotation_terms',
])]
class Branch extends Model
{
    /** @use HasFactory<BranchFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $appends = ['logo_url'];

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
     * The public URL of the branch's logo.
     *
     * @return Attribute<string|null, never>
     */
    protected function logoUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => StoredImage::url($this->attributes['logo_path'] ?? null));
    }

    /**
     * The company identity this branch prints on its paperwork. Each branch
     * stands on its own: a detail left blank is left off the page rather
     * than borrowed from another company, and the name falls back to the
     * branch's own.
     *
     * @return array{name: string, address: list<string>, phone: string|null, mobile: string|null, email: string|null, website: string|null, gstin: string|null, bank: array<string, string|null>|null}
     */
    public function letterhead(): array
    {
        $address = preg_split('/\r\n|\r|\n/', (string) $this->address, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if (filled($this->city)) {
            $address[] = $this->city;
        }

        return [
            'name' => filled($this->company_name) ? $this->company_name : $this->name,
            'address' => $address,
            'phone' => $this->phone,
            'mobile' => $this->mobile,
            'email' => $this->email,
            'website' => $this->website,
            'gstin' => $this->gstin,
            'bank' => $this->bankDetails(),
        ];
    }

    /**
     * The bank details to print on this branch's paperwork, or null when it
     * has no account on record.
     *
     * @return array{name: string, account: string|null, branch: string|null, ifsc: string|null, branch_ifsc: string|null}|null
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
            'branch' => $this->bank_branch,
            'ifsc' => $this->bank_ifsc,
            'branch_ifsc' => $branchAndIfsc === [] ? null : implode(' & ', $branchAndIfsc),
        ];
    }

    /**
     * The brands this branch deals in, shown under its quotations' footer.
     *
     * @return BelongsToMany<Brand, $this>
     */
    public function brands(): BelongsToMany
    {
        return $this->belongsToMany(Brand::class)->withTimestamps();
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
