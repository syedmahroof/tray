<?php

namespace App\Support;

use App\Models\Contact;
use App\Models\Enquiry;
use App\Models\Reminder;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class ReminderNotifications
{
    /**
     * @return Builder<Reminder>
     */
    public static function dueQuery(User $user): Builder
    {
        return Reminder::query()
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->where('remind_at', '<=', Carbon::now()->endOfDay())
            ->orderBy('remind_at');
    }

    /**
     * @return array{items: array<int, array{id: int, title: string, remind_at: string, url: string|null, subject: string|null}>, total: int}
     */
    public static function forUser(User $user, int $limit = 5): array
    {
        $reminders = static::dueQuery($user)->with('remindable')->limit($limit)->get();

        return [
            'items' => $reminders->map(fn (Reminder $reminder): array => [
                'id' => $reminder->id,
                'title' => $reminder->title,
                'remind_at' => $reminder->remind_at->toIso8601String(),
                'url' => static::remindableUrl($reminder),
                'subject' => static::remindableSubject($reminder),
            ])->all(),
            'total' => static::dueQuery($user)->count(),
        ];
    }

    public static function remindableUrl(Reminder $reminder): ?string
    {
        return match (true) {
            $reminder->remindable instanceof Contact => route('contacts.show', $reminder->remindable),
            $reminder->remindable instanceof Enquiry => route('enquiries.show', $reminder->remindable),
            default => null,
        };
    }

    public static function remindableSubject(Reminder $reminder): ?string
    {
        return match (true) {
            $reminder->remindable instanceof Contact => $reminder->remindable->name,
            $reminder->remindable instanceof Enquiry => "Enquiry #{$reminder->remindable->id}",
            default => null,
        };
    }
}
