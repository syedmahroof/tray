<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\Enquiry;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\Reminder;
use App\Models\User;
use App\Models\VisitReport;
use App\Support\ReminderNotifications;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();

        // High-level counts
        $counts = [
            'customers' => Customer::query()->count(),
            'contacts' => Contact::query()->count(),
            'enquiries' => Enquiry::query()->count(),
            'projects' => Project::query()->count(),
            'quotations' => Quotation::query()->count(),
            'visit_reports' => VisitReport::query()->count(),
        ];

        // Enquiries by Status for Chart
        $enquiriesByStatus = $this->enquiriesByStatus();

        // Enquiries by Month for Chart
        $enquiriesByMonth = $this->enquiriesByMonth();

        return response()->json([
            'counts' => $counts,
            'charts' => [
                'enquiriesByStatus' => $enquiriesByStatus,
                'enquiriesByMonth' => $enquiriesByMonth,
            ],
            'followups' => $this->followups($user),
        ]);
    }

    /**
     * Pending reminders that need attention today: those due at some point
     * today, plus anything still open from an earlier day.
     *
     * @return array{items: array<int, array<string, mixed>>, today_count: int, overdue_count: int}
     */
    private function followups(User $user): array
    {
        $endOfToday = Carbon::now()->endOfDay();
        $startOfToday = Carbon::now()->startOfDay();

        $reminders = Reminder::query()
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->where('remind_at', '<=', $endOfToday)
            ->with('remindable')
            ->orderBy('remind_at')
            ->get();

        $items = $reminders->map(fn (Reminder $reminder): array => [
            'id' => $reminder->id,
            'title' => $reminder->title,
            'remind_at' => $reminder->remind_at->toIso8601String(),
            'subject' => ReminderNotifications::remindableSubject($reminder),
            'is_overdue' => $reminder->remind_at->lessThan($startOfToday),
        ])->all();

        return [
            'items' => $items,
            'today_count' => count(array_filter($items, fn (array $i): bool => ! $i['is_overdue'])),
            'overdue_count' => count(array_filter($items, fn (array $i): bool => $i['is_overdue'])),
        ];
    }

    private function enquiriesByStatus(): array
    {
        $counts = Enquiry::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return array_map(
            fn (string $status): array => ['status' => $status, 'count' => $counts->get($status, 0)],
            Enquiry::STATUSES,
        );
    }

    private function enquiriesByMonth(): array
    {
        $start = Carbon::now()->startOfMonth()->subMonths(5);

        $counts = Enquiry::query()
            ->where('created_at', '>=', $start)
            ->pluck('created_at')
            ->map(fn (CarbonInterface $createdAt): string => $createdAt->format('Y-m'))
            ->countBy()
            ->all();

        return collect(range(0, 5))
            ->map(function (int $offset) use ($start, $counts): array {
                $month = $start->copy()->addMonths($offset);

                return [
                    'month' => $month->format('M'),
                    'count' => $counts[$month->format('Y-m')] ?? 0,
                ];
            })
            ->all();
    }
}
