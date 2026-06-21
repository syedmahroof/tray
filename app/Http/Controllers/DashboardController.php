<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Builder;
use App\Models\Contact;
use App\Models\ContactType;
use App\Models\Enquiry;
use App\Models\Product;
use App\Models\Project;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Dashboard', [
            'counts' => [
                'contacts' => $user->can('contacts.view') ? Contact::query()->count() : null,
                'enquiries' => $user->can('enquiries.view') ? Enquiry::query()->count() : null,
                'projects' => $user->can('projects.view') ? Project::query()->count() : null,
                'products' => $user->can('products.view') ? Product::query()->count() : null,
                'builders' => $user->can('builders.view') ? Builder::query()->count() : null,
                'branches' => $user->can('branches.view') ? Branch::query()->count() : null,
                'users' => $user->can('users.view') ? User::query()->count() : null,
            ],
            'enquiriesByStatus' => $user->can('enquiries.view') ? $this->enquiriesByStatus() : null,
            'enquiriesByMonth' => $user->can('enquiries.view') ? $this->enquiriesByMonth() : null,
            'contactsByType' => $user->can('contacts.view') ? $this->contactsByType() : null,
        ]);
    }

    /**
     * @return array<int, array{status: string, count: int}>
     */
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

    /**
     * @return array<int, array{month: string, count: int}>
     */
    private function enquiriesByMonth(): array
    {
        $start = Carbon::now()->startOfMonth()->subMonths(5);

        /** @var array<string, int> $counts */
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

    /**
     * @return array<int, array{type: string, count: int}>
     */
    private function contactsByType(): array
    {
        return ContactType::query()
            ->withCount('contacts')
            ->orderByDesc('contacts_count')
            ->get()
            ->map(fn (ContactType $contactType): array => [
                'type' => $contactType->name,
                'count' => $contactType->contacts_count,
            ])
            ->all();
    }
}
