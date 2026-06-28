<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['user_id', 'auditable_type', 'auditable_id', 'action', 'description', 'changes'])]
class AuditLog extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'changes' => 'array',
        ];
    }

    /**
     * Get the parent auditable model (e.g. Contact).
     *
     * @return MorphTo<Model, $this>
     */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the user who performed the action.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
