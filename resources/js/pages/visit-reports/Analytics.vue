<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { CalendarClock, ClipboardCheck, List } from '@lucide/vue';
import { computed } from 'vue';
import DashboardBarChart from '@/components/charts/DashboardBarChart.vue';
import DashboardDonutChart from '@/components/charts/DashboardDonutChart.vue';
import Heading from '@/components/Heading.vue';
import StatCard from '@/components/StatCard.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { analytics, index } from '@/routes/visit-reports';
import type { VisitReportMonthCount, VisitReportTypeCount } from '@/types';

const props = defineProps<{
    stats: {
        total: number;
        thisMonth: number;
        upcomingFollowUps: number;
    };
    visitReportsByType: VisitReportTypeCount[];
    visitReportsByMonth: VisitReportMonthCount[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Visit Reports', href: index() },
            { title: 'Analytics', href: analytics() },
        ],
    },
});

const typeColors: Record<string, string> = {
    'Site Visit': '#3b82f6',
    'Client Meeting': '#f59e0b',
    'Follow-up': '#10b981',
    Inspection: '#7c3aed',
    Other: '#94a3b8',
};

const donutData = computed(() =>
    props.visitReportsByType.map((item) => ({
        label: item.type,
        value: item.count,
        color: typeColors[item.type] ?? '#94a3b8',
    })),
);

const monthlyData = computed(() =>
    props.visitReportsByMonth.map((item) => ({
        label: item.month,
        value: item.count,
    })),
);
</script>

<template>
    <Head title="Visit Reports — Analytics" />

    <div class="flex flex-col space-y-6">
        <div class="flex items-center justify-between">
            <Heading
                variant="small"
                title="Visit Reports Analytics"
                description="Trends across site visits, meetings, and follow-ups"
            />

            <Button variant="outline" as-child>
                <Link :href="index()"><List /> List view</Link>
            </Button>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <StatCard
                label="Total visit reports"
                :value="stats.total"
                :icon="ClipboardCheck"
                color="#0ea5e9"
            />
            <StatCard
                label="This month"
                :value="stats.thisMonth"
                :icon="CalendarClock"
                color="#7c3aed"
            />
            <StatCard
                label="Upcoming follow-ups"
                :value="stats.upcomingFollowUps"
                :icon="CalendarClock"
                color="#d97706"
            />
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <Card>
                <CardHeader>
                    <CardTitle>Visit reports by type</CardTitle>
                </CardHeader>
                <CardContent>
                    <DashboardDonutChart :data="donutData" />
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Visit reports per month</CardTitle>
                </CardHeader>
                <CardContent>
                    <DashboardBarChart :data="monthlyData" color="#0ea5e9" />
                </CardContent>
            </Card>
        </div>
    </div>
</template>
