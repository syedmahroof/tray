<?php

namespace App\Http\Controllers;

use App\Actions\Activity\CreateReminder;
use App\Http\Requests\SaveReminderRequest;
use App\Models\Contact;
use App\Models\Enquiry;
use App\Models\Reminder;
use App\Support\ReminderNotifications;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class ReminderController extends Controller
{
    /**
     * Display the authenticated user's due and overdue reminders.
     */
    public function index(Request $request): Response
    {
        $search = trim((string) $request->input('search', ''));

        return Inertia::render('reminders/Index', [
            'reminders' => ReminderNotifications::dueQuery($request->user())
                ->with('remindable')
                ->when($search !== '', fn ($query) => $query->where('title', 'like', "%{$search}%"))
                ->paginate(15)
                ->withQueryString()
                ->through(fn (Reminder $reminder): array => [
                    'id' => $reminder->id,
                    'title' => $reminder->title,
                    'remind_at' => $reminder->remind_at->toIso8601String(),
                    'url' => ReminderNotifications::remindableUrl($reminder),
                    'subject' => ReminderNotifications::remindableSubject($reminder),
                ]),
            'filters' => ['search' => $search],
        ]);
    }

    /**
     * Add a reminder to the given contact.
     */
    public function storeForContact(SaveReminderRequest $request, Contact $contact, CreateReminder $action): RedirectResponse
    {
        $action->handle($contact, $request->user(), $request->validated('title'), Carbon::parse($request->validated('remind_at')));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Reminder added.')]);

        return back();
    }

    /**
     * Add a reminder to the given enquiry.
     */
    public function storeForEnquiry(SaveReminderRequest $request, Enquiry $enquiry, CreateReminder $action): RedirectResponse
    {
        $action->handle($enquiry, $request->user(), $request->validated('title'), Carbon::parse($request->validated('remind_at')));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Reminder added.')]);

        return back();
    }

    /**
     * Remove the given reminder.
     */
    public function destroy(Reminder $reminder): RedirectResponse
    {
        $reminder->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Reminder deleted.')]);

        return back();
    }
}
