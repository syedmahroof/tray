<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    CalendarDays,
    CircleCheckBig,
    FileText,
    List,
    Trophy,
    TrendingUp,
    Users,
    Wallet,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import DashboardBarChart from '@/components/charts/DashboardBarChart.vue';
import DashboardDonutChart from '@/components/charts/DashboardDonutChart.vue';
import Heading from '@/components/Heading.vue';
import StatCard from '@/components/StatCard.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { analytics, index } from '@/routes/quotations';

const props = defineProps<{
    range: string;
    from: string | null;
    to: string | null;
    stats: {
        total: number;
        quotedValue: number;
        acceptedValue: number;
        winRate: number;
    };
    statusBreakdown: { status: string; count: number; value: number }[];
    byCreator: { staff: string; count: number; value: number }[];
    byBranch: { branch: string; value: number }[];
    trend: { label: string; value: number }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Quotations', href: index() },
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
];

const activeRange = ref(props.range);
const customFrom = ref(props.from ?? '');
const customTo = ref(props.to ?? '');

const applyRange = (range: string) => {
    activeRange.value = range;
    router.get(
        analytics.url(),
        { range },
        { preserveState: true, replace: true },
    );
};

const applyCustomRange = () => {
    router.get(
        analytics.url(),
        { range: 'custom', from: customFrom.value, to: customTo.value },
        { preserveState: true, replace: true },
    );
};

const money = (value: number) =>
    `₹${Number(value).toLocaleString(undefined, { maximumFractionDigits: 0 })}`;

const statusColors: Record<string, string> = {
    draft: '#6b7280',
    sent: '#0ea5e9',
    accepted: '#16a34a',
    rejected: '#dc2626',
    expired: '#f59e0b',
    revised: '#8b5cf6',
};

const statusDonutData = computed(() =>
    props.statusBreakdown
        .filter((row) => row.count > 0)
        .map((row) => ({
            label: row.status,
            value: row.count,
            color: statusColors[row.status] ?? '#6b7280',
        })),
);

const valueByStatusData = computed(() =>
    props.statusBreakdown
        .filter((row) => row.value > 0)
        .map((row) => ({ label: row.status, value: row.value })),
);

const creatorBarData = computed(() =>
    props.byCreator.map((row) => ({ label: row.staff, value: row.value })),
);

const branchBarData = computed(() =>
    props.byBranch.map((row) => ({ label: row.branch, value: row.value })),
);

const trendBarData = computed(() =>
    props.trend.map((row) => ({ label: row.label, value: row.value })),
);
</script>

<template>
    <Head title="Quotations — Analytics" />

    <div class="flex flex-col space-y-6">
        <div class="flex items-center justify-between">
            <Heading
                variant="small"
                title="Quotation Analytics"
                description="Pipeline value, win rate, and trends across your quotations"
            />

            <Button variant="outline" as-child>
                <Link :href="index()"><List /> List view</Link>
            </Button>
        </div>

        <!-- Date range presets -->
        <div class="flex flex-wrap items-center gap-2">
            <Button
                v-for="opt in rangeOptions"
                :key="opt.value"
                :variant="activeRange === opt.value ? 'default' : 'outline'"
                size="sm"
                @click="applyRange(opt.value)"
            >
                {{ opt.label }}
            </Button>

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

        <!-- Stat cards -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <StatCard
                label="Quotations in range"
                :value="stats.total"
                :icon="FileText"
                color="#4f46e5"
            />
            <StatCard
                label="Quoted value"
                :value="money(stats.quotedValue)"
                :icon="Wallet"
                color="#0ea5e9"
            />
            <StatCard
                label="Accepted value"
                :value="money(stats.acceptedValue)"
                :icon="CircleCheckBig"
                color="#16a34a"
            />
            <StatCard
                label="Win rate"
                :value="`${stats.winRate}%`"
                :icon="Trophy"
                color="#f59e0b"
            />
        </div>

        <!-- Trend -->
        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <TrendingUp class="h-5 w-5 text-[#4f46e5]" />
                    Quoted Value Trend
                </CardTitle>
            </CardHeader>
            <CardContent>
                <DashboardBarChart :data="trendBarData" color="#4f46e5" />
            </CardContent>
        </Card>

        <!-- Status mix -->
        <div class="grid gap-4 lg:grid-cols-2">
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <FileText class="h-4 w-4 text-[#0ea5e9]" />
                        Quotations by Status
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <DashboardDonutChart
                        v-if="statusDonutData.length"
                        :data="statusDonutData"
                    />
                    <p
                        v-else
                        class="py-8 text-center text-sm text-muted-foreground"
                    >
                        No quotations in this range.
                    </p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Wallet class="h-4 w-4 text-[#16a34a]" />
                        Value by Status
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <DashboardBarChart
                        v-if="valueByStatusData.length"
                        :data="valueByStatusData"
                        color="#16a34a"
                    />
                    <p
                        v-else
                        class="py-8 text-center text-sm text-muted-foreground"
                    >
                        No quotations in this range.
                    </p>
                </CardContent>
            </Card>
        </div>

        <!-- Staff + Branch -->
        <div class="grid gap-4 lg:grid-cols-2">
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Users class="h-4 w-4 text-[#db2777]" />
                        Quoted Value by Staff
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <DashboardBarChart
                        v-if="creatorBarData.length"
                        :data="creatorBarData"
                        color="#db2777"
                    />
                    <p
                        v-else
                        class="py-8 text-center text-sm text-muted-foreground"
                    >
                        No data in this range.
                    </p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <FileText class="h-4 w-4 text-[#4f46e5]" />
                        Quoted Value by Branch
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <DashboardBarChart
                        v-if="branchBarData.length"
                        :data="branchBarData"
                        color="#4f46e5"
                    />
                    <p
                        v-else
                        class="py-8 text-center text-sm text-muted-foreground"
                    >
                        No data in this range.
                    </p>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
