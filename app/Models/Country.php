<?php

namespace App\Models;

use Database\Factories\CountryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string|null $code
 */
#[Fillable(['name', 'code'])]
class Country extends Model
{
    /** @use HasFactory<CountryFactory> */
    use HasFactory;

    /**
     * The CRM's home country, India, which every country picker and new
     * record defaults to.
     */
    public static function homeId(): ?int
    {
        $id = static::query()
            ->where('code', 'IN')
            ->orWhere('name', 'India')
            ->orderByRaw("code = 'IN' desc")
            ->value('id');

        return $id === null ? null : (int) $id;
    }

    /**
     * @return HasMany<State, $this>
     */
    public function states(): HasMany
    {
        return $this->hasMany(State::class);
    }
}
