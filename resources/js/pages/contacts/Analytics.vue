<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    BarChart3,
    CalendarDays,
    List,
    TrendingUp,
    Users,
    UserRoundX,
    UserRoundCheck,
    GitBranch,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import DashboardBarChart from '@/components/charts/DashboardBarChart.vue';
import DashboardDonutChart from '@/components/charts/DashboardDonutChart.vue';
import Heading from '@/components/Heading.vue';
import StatCard from '@/components/StatCard.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { analytics, index } from '@/routes/contacts';

const props = defineProps<{
    range: string;
    from: string | null;
    to: string | null;
    stats: {
        total: number;
        inRange: number;
        unassigned: number;
        newThisMonth: number;
    };
    contactsByType: { type: string; count: number }[];
    contactsByBranch: { branch: string; count: number }[];
    contactsByStaff: { staff: string; count: number }[];
    contactTrends: { label: string; count: number }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Contacts', href: index() },
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

const typeDonutData = computed(() =>
    props.contactsByType.map((item) => ({
        label: item.type,
        value: item.count,
        color: '#2563eb', // Let's use standard chart library behavior or assign colors dynamically
    }))
);

const branchBarData = computed(() =>
    props.contactsByBranch.map((item) => ({
        label: item.branch,
        value: item.count,
    }))
);

const staffBarData = computed(() =>
    props.contactsByStaff.map((item) => ({
        label: item.staff,
        value: item.count,
    }))
);

const trendBarData = computed(() =>
    props.contactTrends.map((item) => ({
        label: item.label,
        value: item.count,
    }))
);
</script>

<template>
    <Head title="Contacts — Analytics" />

    <div class="flex flex-col space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <Heading
                variant="small"
                title="Contacts Analytics"
                description="Breakdowns and growth trends across your contact repository"
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
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <StatCard
                label="Total contacts"
                :value="stats.total"
                :icon="Users"
                color="#4f46e5"
            />
            <StatCard
                label="Filtered in range"
                :value="stats.inRange"
                :icon="BarChart3"
                color="#0ea5e9"
            />
            <StatCard
                label="Unassigned in range"
                :value="stats.unassigned"
                :icon="UserRoundX"
                color="#ea580c"
            />
            <StatCard
                label="New this month"
                :value="stats.newThisMonth"
                :icon="UserRoundCheck"
                color="#16a34a"
            />
        </div>

        <!-- Trend Chart -->
        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <TrendingUp class="h-5 w-5 text-[#4f46e5]" />
                    Contact Creation Trend
                </CardTitle>
            </CardHeader>
            <CardContent>
                <DashboardBarChart :data="trendBarData" color="#4f46e5" />
            </CardContent>
        </Card>

        <!-- Second Row: Type + Branch -->
        <div class="grid gap-4 lg:grid-cols-2">
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Users class="h-4 w-4 text-[#db2777]" />
                        Contacts by Type
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <DashboardDonutChart :data="typeDonutData" />
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <GitBranch class="h-4 w-4 text-[#16a34a]" />
                        Contacts by Branch
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <DashboardBarChart :data="branchBarData" color="#16a34a" />
                </CardContent>
            </Card>
        </div>

        <!-- Third Row: Staff -->
        <div class="grid gap-4">
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Users class="h-4 w-4 text-[#ea580c]" />
                        Contacts by Staff / Assignee
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <DashboardBarChart :data="staffBarData" color="#ea580c" />
                </CardContent>
            </Card>
        </div>
    </div>
</template>
