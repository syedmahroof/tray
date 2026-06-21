<?php

namespace App\Models;

use Database\Factories\ReminderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $remindable_type
 * @property int $remindable_id
 * @property int $user_id
 * @property string $title
 * @property Carbon $remind_at
 * @property string $status
 * @property-read User $user
 */
#[Fillable(['user_id', 'title', 'remind_at', 'status'])]
class Reminder extends Model
{
    /** @use HasFactory<ReminderFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'remind_at' => 'datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function remindable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
