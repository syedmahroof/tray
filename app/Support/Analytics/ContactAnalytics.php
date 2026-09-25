<?php

namespace App\Support\Analytics;

use App\Models\Contact;
use Illuminate\Database\Eloquent\Collection;

/**
 * How the contact book is growing, and who looks after it.
 */
class ContactAnalytics extends AnalyticsSection
{
    public function title(): string
    {
        return 'Contacts';
    }

    public function description(): string
    {
        return 'New contacts by type, owner and location.';
    }

    public function report(AnalyticsPeriod $period): AnalyticsReport
    {
        $current = $this->contacts($period);
        $previous = $this->contacts($period->previous());

        $withPhone = fn (Collection $items): float => $this->percent($items->whereNotNull('phone')->where('phone', '!=', '')->count(), $items->count());
        $assigned = fn (Collection $items): float => $this->percent($items->whereNotNull('assigned_to')->count(), $items->count());

        return (new AnalyticsReport)
            ->stat('New contacts', $current->count(), $previous->count(), icon: 'user-plus', color: '#2563eb')
            ->stat('Total contacts', Contact::query()->count(), icon: 'users', color: '#4f46e5')
            ->stat('With a phone number', $withPhone($current), $withPhone($previous), 'percent', 'phone', '#16a34a')
            ->stat('Assigned to someone', $assigned($current), $assigned($previous), 'percent', 'user-check', '#d97706')
            ->chart('New contacts over time', 'trend', $period->trend(
                $current->map(fn (Contact $contact): array => ['date' => $contact->created_at, 'value' => 1]),
            ))
            ->chart('New contacts by type', 'donut', $this->countBy($current, fn (Contact $c) => $c->contactType->name))
            ->chart('New contacts by owner', 'bar', $this->countBy($current, fn (Contact $c) => $c->assignee?->name, 10))
            ->chart('New contacts by location', 'bar', $this->countBy($current, fn (Contact $c) => $c->location?->name ?? 'No location', 10))
            ->table('Contacts by type', [
                'name' => 'Type',
                'new' => ['New in period', 'number'],
                'total' => ['All time', 'number'],
            ], Contact::query()
                ->with('contactType:id,name')
                ->get(['id', 'contact_type_id'])
                ->groupBy(fn (Contact $c): string => $c->contactType->name)
                ->map(fn (Collection $group, string $name): array => [
                    'name' => $name,
                    'new' => $current->filter(fn (Contact $c): bool => $c->contactType->name === $name)->count(),
                    'total' => $group->count(),
                ])
                ->sortByDesc('total')
                ->values()
                ->all());
    }

    /**
     * @return Collection<int, Contact>
     */
    private function contacts(AnalyticsPeriod $period): Collection
    {
        return $this->within(Contact::query(), $period)
            ->with(['contactType:id,name', 'assignee:id,name', 'location:id,name'])
            ->get(['id', 'contact_type_id', 'phone', 'assigned_to', 'location_id', 'created_at']);
    }
}
