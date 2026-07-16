<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Builder;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\Enquiry;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\Reminder;
use App\Models\VisitReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AnalyticsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'overview'     => $this->overview(),
            'enquiries'    => $this->enquiriesAnalytics(),
            'quotations'   => $this->quotationsAnalytics(),
            'projects'     => $this->projectsAnalytics(),
            'visits'       => $this->visitReportsAnalytics(),
            'monthly'      => $this->monthlyActivity(),
        ]);
    }

    public function module(Request $request, string $module): JsonResponse
    {
        return match ($module) {
            'customers'     => response()->json($this->customersDetail()),
            'contacts'      => response()->json($this->contactsDetail()),
            'enquiries'     => response()->json($this->enquiriesDetail()),
            'quotations'    => response()->json($this->quotationsDetail()),
            'projects'      => response()->json($this->projectsDetail()),
            'builders'      => response()->json($this->buildersDetail()),
            'visit-reports' => response()->json($this->visitsDetail()),
            'reminders'     => response()->json($this->remindersDetail()),
            default         => response()->json(['error' => 'Unknown module'], 404),
        };
    }

    // ─── Overview ──────────────────────────────────────────────────────────────

    private function overview(): array
    {
        return [
            'customers'     => Customer::count(),
            'contacts'      => Contact::count(),
            'enquiries'     => Enquiry::count(),
            'quotations'    => Quotation::count(),
            'projects'      => Project::count(),
            'builders'      => Builder::count(),
            'visit_reports' => VisitReport::count(),
            'reminders'     => Reminder::count(),
        ];
    }

    // ─── Monthly Activity (last 6 months across all entities) ──────────────────

    private function monthlyActivity(): array
    {
        $start = Carbon::now()->startOfMonth()->subMonths(5);
        $months = collect(range(0, 5))->map(fn($i) => $start->copy()->addMonths($i));

        $enquiryCounts = $this->monthlyCounts(Enquiry::class, $start);
        $visitCounts   = $this->monthlyCounts(VisitReport::class, $start);
        $quotationCounts = $this->monthlyCounts(Quotation::class, $start);

        return $months->map(fn($m) => [
            'month'      => $m->format('M'),
            'enquiries'  => $enquiryCounts[$m->format('Y-m')] ?? 0,
            'visits'     => $visitCounts[$m->format('Y-m')] ?? 0,
            'quotations' => $quotationCounts[$m->format('Y-m')] ?? 0,
        ])->values()->all();
    }

    private function monthlyCounts(string $model, Carbon $start): array
    {
        return $model::query()
            ->where('created_at', '>=', $start)
            ->pluck('created_at')
            ->map(fn($d) => Carbon::parse($d)->format('Y-m'))
            ->countBy()
            ->all();
    }

    // ─── Module analytics ──────────────────────────────────────────────────────

    private function enquiriesAnalytics(): array
    {
        $byStatus = Enquiry::selectRaw('status, count(*) as count')->groupBy('status')->pluck('count', 'status');
        return [
            'total'     => Enquiry::count(),
            'by_status' => collect(Enquiry::STATUSES)->map(fn($s) => ['label' => $s, 'value' => $byStatus->get($s, 0)])->values()->all(),
        ];
    }

    private function quotationsAnalytics(): array
    {
        $byStatus = Quotation::selectRaw('status, count(*) as count')->groupBy('status')->pluck('count', 'status');
        $statuses = ['draft', 'sent', 'approved', 'rejected', 'expired'];
        return [
            'total'     => Quotation::count(),
            'by_status' => collect($statuses)->map(fn($s) => ['label' => $s, 'value' => $byStatus->get($s, 0)])->values()->all(),
        ];
    }

    private function projectsAnalytics(): array
    {
        $byStatus = Project::selectRaw('status, count(*) as count')->groupBy('status')->pluck('count', 'status');
        return [
            'total'     => Project::count(),
            'by_status' => collect(Project::STATUSES)->map(fn($s) => ['label' => $s, 'value' => $byStatus->get($s, 0)])->values()->all(),
        ];
    }

    private function visitReportsAnalytics(): array
    {
        $byType = VisitReport::selectRaw('visit_type, count(*) as count')->groupBy('visit_type')->pluck('count', 'visit_type');
        return [
            'total'   => VisitReport::count(),
            'by_type' => $byType->map(fn($v, $k) => ['label' => $k, 'value' => $v])->values()->all(),
        ];
    }

    // ─── Per-module detail analytics ───────────────────────────────────────────

    private function customersDetail(): array
    {
        $recent = Customer::latest()->take(5)->get(['id', 'name', 'phone', 'email', 'created_at']);
        return ['total' => Customer::count(), 'this_month' => Customer::whereMonth('created_at', now()->month)->count(), 'recent' => $recent];
    }

    private function contactsDetail(): array
    {
        $byType = Contact::with('contactType')->get()->groupBy('contact_type_id')
            ->map(fn($g) => ['label' => $g->first()?->contactType?->name ?? 'Unknown', 'value' => $g->count()])
            ->values()->all();
        return ['total' => Contact::count(), 'this_month' => Contact::whereMonth('created_at', now()->month)->count(), 'by_type' => $byType];
    }

    private function enquiriesDetail(): array
    {
        $byStatus = Enquiry::selectRaw('status, count(*) as count')->groupBy('status')->pluck('count', 'status');
        return [
            'total'      => Enquiry::count(),
            'this_month' => Enquiry::whereMonth('created_at', now()->month)->count(),
            'by_status'  => collect(Enquiry::STATUSES)->map(fn($s) => ['label' => $s, 'value' => $byStatus->get($s, 0)])->values()->all(),
            'monthly'    => $this->monthlyCounts(Enquiry::class, Carbon::now()->startOfMonth()->subMonths(5)),
        ];
    }

    private function quotationsDetail(): array
    {
        $byStatus = Quotation::selectRaw('status, count(*) as count')->groupBy('status')->pluck('count', 'status');
        $statuses = ['draft', 'sent', 'approved', 'rejected', 'expired'];
        $totalValue = Quotation::sum('total');
        return [
            'total'       => Quotation::count(),
            'this_month'  => Quotation::whereMonth('created_at', now()->month)->count(),
            'total_value' => $totalValue,
            'by_status'   => collect($statuses)->map(fn($s) => ['label' => $s, 'value' => $byStatus->get($s, 0)])->values()->all(),
        ];
    }

    private function projectsDetail(): array
    {
        $byStatus = Project::selectRaw('status, count(*) as count')->groupBy('status')->pluck('count', 'status');
        return [
            'total'      => Project::count(),
            'this_month' => Project::whereMonth('created_at', now()->month)->count(),
            'by_status'  => collect(Project::STATUSES)->map(fn($s) => ['label' => $s, 'value' => $byStatus->get($s, 0)])->values()->all(),
        ];
    }

    private function buildersDetail(): array
    {
        return [
            'total'      => Builder::count(),
            'this_month' => Builder::whereMonth('created_at', now()->month)->count(),
            'by_status'  => [
                ['label' => 'active', 'value' => Builder::where('is_active', true)->count()],
                ['label' => 'inactive', 'value' => Builder::where('is_active', false)->count()],
            ],
            'recent'     => Builder::latest()->take(5)->get(['id', 'name', 'phone', 'email', 'created_at']),
        ];
    }

    private function visitsDetail(): array
    {
        $byType = VisitReport::selectRaw('visit_type, count(*) as count')->groupBy('visit_type')->pluck('count', 'visit_type');
        return [
            'total'      => VisitReport::count(),
            'this_month' => VisitReport::whereMonth('created_at', now()->month)->count(),
            'by_type'    => $byType->map(fn($v, $k) => ['label' => $k, 'value' => $v])->values()->all(),
        ];
    }

    private function remindersDetail(): array
    {
        $byStatus = Reminder::selectRaw('status, count(*) as count')->groupBy('status')->pluck('count', 'status');
        return [
            'total'      => Reminder::count(),
            'pending'    => Reminder::where('status', 'pending')->count(),
            'completed'  => Reminder::where('status', 'completed')->count(),
            'by_status'  => $byStatus->map(fn($v, $k) => ['label' => $k, 'value' => $v])->values()->all(),
        ];
    }
}
