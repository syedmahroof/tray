<?php

namespace App\Models\Concerns;

use App\Models\Note;
use App\Models\Reminder;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasActivity
{
    /**
     * Boot the trait: remove the record's activity log when it is deleted.
     */
    protected static function bootHasActivity(): void
    {
        static::deleting(function ($model) {
            $model->notes()->delete();
            $model->reminders()->delete();
        });
    }

    /**
     * @return MorphMany<Note, $this>
     */
    public function notes(): MorphMany
    {
        return $this->morphMany(Note::class, 'notable');
    }

    /**
     * @return MorphMany<Reminder, $this>
     */
    public function reminders(): MorphMany
    {
        return $this->morphMany(Reminder::class, 'remindable');
    }
}
