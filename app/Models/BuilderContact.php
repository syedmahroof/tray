<?php

namespace App\Models;

use Database\Factories\BuilderContactFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An additional person to reach at a builder, beside its main contact person.
 *
 * @property int $id
 * @property int $builder_id
 * @property string $name
 * @property string|null $role
 * @property string|null $phone
 * @property string|null $email
 * @property-read Builder $builder
 */
#[Fillable(['builder_id', 'name', 'role', 'phone', 'email'])]
class BuilderContact extends Model
{
    /** @use HasFactory<BuilderContactFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Builder, $this>
     */
    public function builder(): BelongsTo
    {
        return $this->belongsTo(Builder::class);
    }
}
