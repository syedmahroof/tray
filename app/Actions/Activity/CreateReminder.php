<?php

namespace App\Actions\Activity;

use App\Models\Contact;
use App\Models\Enquiry;
use App\Models\Reminder;
use App\Models\User;
use Illuminate\Support\Carbon;

class CreateReminder
{
    /**
     * Add a reminder to the given remindable model.
     */
    public function handle(Contact|Enquiry $remindable, User $author, string $title, Carbon $remindAt): Reminder
    {
        return $remindable->reminders()->create([
            'user_id' => $author->id,
            'title' => $title,
            'remind_at' => $remindAt,
            'status' => 'pending',
        ]);
    }
}
