<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    BarChart3,
    Building2,
    CalendarDays,
    CircleCheckBig,
    List,
    Loader,
    PencilRuler,
    TrendingUp,
    Users,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import DashboardBarChart from '@/components/charts/DashboardBarChart.vue';
import DashboardDonutChart from '@/components/charts/DashboardDonutChart.vue';
import Heading from '@/components/Heading.vue';
import StatCard from '@/components/StatCard.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { analytics, index } from '@/routes/projects';

const props = defineProps<{
    range: string;
    from: string | null;
    to: string | null;
    stats: {
        total: number;
        inRange: number;
        planning: number;
        ongoing: number;
        completed: number;
    };
    projectsByStatus: { status: string; count: number }[];
    projectsByCategory: { category: string; count: number }[];
    projectsByBuilder: { builder: string; count: number }[];
    projectsByStaff: { staff: string; count: number }[];
    projectTrends: { label: string; count: number }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Projects', href: index() },
            { title: 'Analytics', href: analytics() },
        ],
    },
});

const rangeOptions = [
    { label: 'Last 7 days', value: '7d' },
    { label: 'Last 30 days', value: '30d' },
    { label: 'This month', value: 'this_month' },
    { label: 'Last 3 months', value: '3m' },
    { label: 'Last 6 months', value: '6m' },
    { label: 'Last year', value: '1y' },
    { label: 'Custom', value: 'custom' },
];

const activeRange = ref(props.range);
const customFrom = ref(props.from ?? '');
const customTo = ref(props.to ?? '');

const applyRange = (range: string) => {
    activeRange.value = range;
    if (range !== 'custom') {
        router.get(analytics.url(), { range }, { preserveState: true, replace: true });
    }
};

const applyCustomRange = () => {
    router.get(
        analytics.url(),
        { range: 'custom', from: customFrom.value, to: customTo.value },
        { preserveState: true, replace: true },
    );
};

const statusColors: Record<string, string> = {
    planning: '#7c3aed',
    ongoing: '#f59e0b',
    completed: '#16a34a',
};

const statusDonutData = computed(() =>
    props.projectsByStatus.map((item) => ({
        label: item.status.charAt(0).toUpperCase() + item.status.slice(1),
        value: item.count,
        color: statusColors[item.status] ?? '#94a3b8',
    })),
);

const categoryBarData = computed(() =>
    props.projectsByCategory.map((item) => ({
        label: item.category,
        value: item.count,
    })),
);

const builderBarData = computed(() =>
    props.projectsByBuilder.map((item) => ({
        label: item.builder,
        value: item.count,
    })),
);

const staffBarData = computed(() =>
    props.projectsByStaff.map((item) => ({
        label: item.staff,
        value: item.count,
    })),
);

const trendData = computed(() =>
    props.projectTrends.map((item) => ({
        label: item.label,
        value: item.count,
    })),
);
</script>

<template>
    <Head title="Projects — Analytics" />

    <div class="flex flex-col space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <Heading
                variant="small"
                title="Projects Analytics"
                description="Performance insights, trends, and breakdowns across all projects"
            />

            <Button variant="outline" as-child>
                <Link :href="index()"><List /> List view</Link>
            </Button>
        </div>

        <!-- Date Range Presets -->
        <div class="flex flex-wrap items-center gap-2">
            <Button
                v-for="opt in rangeOptions.filter((o) => o.value !== 'custom')"
                :key="opt.value"
                :variant="activeRange === opt.value ? 'default' : 'outline'"
                size="sm"
                @click="applyRange(opt.value)"
            >
                {{ opt.label }}
            </Button>

            <!-- Custom Range -->
            <div
                class="ml-auto flex items-center gap-2"
                :class="{ 'opacity-50': activeRange !== 'custom' }"
            >
                <CalendarDays class="h-4 w-4 text-muted-foreground" />
                <Input
                    v-model="customFrom"
                    type="date"
                    class="w-[150px]"
                    aria-label="From date"
                    @focus="activeRange = 'custom'"
                />
                <span class="text-sm text-muted-foreground">to</span>
                <Input
                    v-model="customTo"
                    type="date"
                    class="w-[150px]"
                    aria-label="To date"
                    @focus="activeRange = 'custom'"
                />
                <Button size="sm" @click="applyCustomRange">Apply</Button>
            </div>
        </div>

        <!-- Stat Cards -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <StatCard
                label="Total projects"
                :value="stats.total"
                :icon="Building2"
                color="#4f46e5"
            />
            <StatCard
                label="In selected range"
                :value="stats.inRange"
                :icon="BarChart3"
                color="#0ea5e9"
            />
            <StatCard
                label="Planning"
                :value="stats.planning"
                :icon="PencilRuler"
                color="#7c3aed"
            />
            <StatCard
                label="Ongoing"
                :value="stats.ongoing"
                :icon="Loader"
                color="#f59e0b"
            />
            <StatCard
                label="Completed"
                :value="stats.completed"
                :icon="CircleCheckBig"
                color="#16a34a"
            />
        </div>

        <!-- Trend Chart -->
        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <TrendingUp class="h-5 w-5 text-[#4f46e5]" />
                    Project creation trend
                </CardTitle>
            </CardHeader>
            <CardContent>
                <DashboardBarChart :data="trendData" color="#4f46e5" />
            </CardContent>
        </Card>

        <!-- Second Row: Status + Category -->
        <div class="grid gap-4 lg:grid-cols-2">
            <Card>
                <CardHeader>
                    <CardTitle>Projects by status</CardTitle>
                </CardHeader>
                <CardContent>
                    <DashboardDonutChart :data="statusDonutData" />
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Projects by category</CardTitle>
                </CardHeader>
                <CardContent>
                    <DashboardBarChart :data="categoryBarData" color="#7c3aed" />
                </CardContent>
            </Card>
        </div>

        <!-- Third Row: Builder + Staff -->
        <div class="grid gap-4 lg:grid-cols-2">
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Building2 class="h-4 w-4 text-[#0ea5e9]" />
                        Projects by builder
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <DashboardBarChart :data="builderBarData" color="#0ea5e9" />
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Users class="h-4 w-4 text-[#f59e0b]" />
                        Projects by staff
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <DashboardBarChart :data="staffBarData" color="#f59e0b" />
                </CardContent>
            </Card>
        </div>
    </div>
</template>
