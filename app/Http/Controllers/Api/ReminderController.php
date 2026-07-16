<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reminder;
use App\Support\ReminderNotifications;
use Illuminate\Http\Request;

class ReminderController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->input('search', ''));

        $reminders = ReminderNotifications::dueQuery($request->user())
            ->with('remindable')
            ->when($search !== '', fn ($query) => $query->where('title', 'like', "%{$search}%"))
            ->paginate(15);

        // We can optionally format the response similar to the web version
        $reminders->getCollection()->transform(function (Reminder $reminder) {
            return [
                'id' => $reminder->id,
                'title' => $reminder->title,
                'remind_at' => $reminder->remind_at->toIso8601String(),
                'subject' => ReminderNotifications::remindableSubject($reminder),
            ];
        });

        return response()->json($reminders);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'remind_at' => 'required|date',
            'status' => 'required|string|in:pending,completed,dismissed',
        ]);

        $validated['user_id'] = $request->user()?->id ?? 1;
        $validated['remindable_type'] = 'App\Models\User';
        $validated['remindable_id'] = $validated['user_id'];

        $reminder = Reminder::create($validated);
        return response()->json($reminder, 201);
    }

    public function update(Request $request, Reminder $reminder)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'remind_at' => 'required|date',
            'status' => 'required|string|in:pending,completed,dismissed',
        ]);

        $reminder->update($validated);
        return response()->json($reminder);
    }

    public function destroy(Reminder $reminder)
    {
        $reminder->delete();
        return response()->json(['message' => 'Reminder deleted successfully']);
    }
}
