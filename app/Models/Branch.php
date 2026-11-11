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
 * @property string|null $letterhead_path
 * @property-read string|null $letterhead_url
 * @property string|null $quotation_background_path
 * @property-read string|null $quotation_background_url
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
 * @property string|null $quotation_details
 * @property string|null $quotation_prefix
 * @property int $quotation_next_number
 * @property bool $is_active
 * @property-read Collection<int, Brand> $brands
 */
#[Fillable([
    'name', 'code', 'address', 'city', 'is_active',
    'company_name', 'logo_path', 'letterhead_path', 'quotation_background_path', 'phone', 'mobile', 'email', 'website', 'gstin',
    'bank_name', 'bank_account_number', 'bank_branch', 'bank_ifsc', 'quotation_terms', 'quotation_details',
    'quotation_prefix', 'quotation_next_number',
])]
class Branch extends Model
{
    /** @use HasFactory<BranchFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $appends = ['logo_url', 'letterhead_url', 'quotation_background_url'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'quotation_next_number' => 'integer',
        ];
    }

    /**
     * Whether this branch numbers its quotations in its own series.
     */
    public function hasQuotationSeries(): bool
    {
        return filled($this->quotation_prefix);
    }

    /**
     * Render a number in this branch's quotation series, for example
     * "BTCLT/QT/001". Numbers are padded to at least three digits.
     */
    public function quotationNumber(int $number): string
    {
        return $this->quotation_prefix.str_pad((string) $number, 3, '0', STR_PAD_LEFT);
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
     * The public URL of the branch's letterhead banner.
     *
     * @return Attribute<string|null, never>
     */
    protected function letterheadUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => StoredImage::url($this->attributes['letterhead_path'] ?? null));
    }

    /**
     * The public URL of the full-page background the branch's quotations are
     * printed on.
     *
     * @return Attribute<string|null, never>
     */
    protected function quotationBackgroundUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => StoredImage::url($this->attributes['quotation_background_path'] ?? null));
    }

    /**
     * The company identity this branch prints on its paperwork. Each branch
     * stands on its own: a detail left blank is left off the page rather
     * than borrowed from another company, and the name falls back to the
     * branch's own. The city can be left off where the address alone says
     * enough, as it does on a quotation.
     *
     * @return array{name: string, address: list<string>, phone: string|null, mobile: string|null, email: string|null, website: string|null, gstin: string|null, bank: array<string, string|null>|null}
     */
    public function letterhead(bool $withCity = true): array
    {
        // \R also catches the line separators text pasted from Word or a PDF carries.
        $address = array_values(array_filter(array_map('trim', preg_split('/\R/u', (string) $this->address) ?: []), 'filled'));

        if ($withCity && filled($this->city)) {
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
