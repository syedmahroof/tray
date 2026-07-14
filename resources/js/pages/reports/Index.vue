<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    ClipboardList,
    CircleCheckBig,
    FileText,
    IndianRupee,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import DashboardBarChart from '@/components/charts/DashboardBarChart.vue';
import DashboardDonutChart from '@/components/charts/DashboardDonutChart.vue';
import Heading from '@/components/Heading.vue';
import StatCard from '@/components/StatCard.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { index } from '@/routes/reports';

const props = defineProps<{
    stats: {
        enquiries: number;
        converted: number;
        quotations: number;
        quotedValue: number;
    };
    mostEnquiredProducts: Array<{ id: number; name: string; count: number }>;
    enquiriesByStatus: Array<{ status: string; count: number }>;
    quotationsByStatus: Array<{
        status: string;
        count: number;
        value: number;
    }>;
    topBuilders: Array<{ builder: string; count: number }>;
    staffPerformance: Array<{
        id: number;
        name: string;
        enquiries: number;
        visits: number;
        projects: number;
        quotations: number;
        quotedValue: number;
    }>;
    filters: {
        date_filter: string;
        start_date: string | null;
        end_date: string | null;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Reports', href: index() }],
    },
});

const money = (value: number) =>
    `₹${value.toLocaleString(undefined, { maximumFractionDigits: 0 })}`;

const conversionRate = computed(() =>
    props.stats.enquiries > 0
        ? Math.round((props.stats.converted / props.stats.enquiries) * 100)
        : 0,
);

const statCards = computed(() => [
    {
        key: 'enquiries',
        label: 'Total Enquiries',
        value: props.stats.enquiries,
        icon: ClipboardList,
        color: '#d97706',
    },
    {
        key: 'converted',
        label: `Converted (${conversionRate.value}%)`,
        value: props.stats.converted,
        icon: CircleCheckBig,
        color: '#16a34a',
    },
    {
        key: 'quotations',
        label: 'Quotations',
        value: props.stats.quotations,
        icon: FileText,
        color: '#4f46e5',
    },
]);

const enquiryStatusColors: Record<string, string> = {
    new: '#3b82f6',
    in_progress: '#f59e0b',
    converted: '#10b981',
    lost: '#ef4444',
};

const productBars = computed(() =>
    props.mostEnquiredProducts.map((p) => ({
        label: p.name,
        value: p.count,
    })),
);

const enquiryDonut = computed(() =>
    props.enquiriesByStatus.map((row) => ({
        label: row.status.replace('_', ' '),
        value: row.count,
        color: enquiryStatusColors[row.status] ?? '#94a3b8',
    })),
);

const builderBars = computed(() =>
    props.topBuilders.map((b) => ({ label: b.builder, value: b.count })),
);

const filterDate = ref(props.filters.date_filter || 'all');
const filterStartDate = ref(props.filters.start_date || '');
const filterEndDate = ref(props.filters.end_date || '');

const applyFilters = () => {
    router.get(
        index(),
        {
            date_filter: filterDate.value,
            start_date: filterStartDate.value || undefined,
            end_date: filterEndDate.value || undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

watch(filterDate, (newVal) => {
    if (newVal !== 'custom') {
        applyFilters();
    }
});
</script>

<template>
    <Head title="Reports" />

    <div class="flex flex-col space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <Heading
                variant="small"
                title="Reports"
                description="Sales performance, product demand, and staff activity"
            />

            <div class="flex items-center gap-2">
                <Select v-model="filterDate">
                    <SelectTrigger class="w-[180px]">
                        <SelectValue placeholder="Select Date Range" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="last_7_days">Last 7 days</SelectItem>
                        <SelectItem value="last_30_days">Last 30 days</SelectItem>
                        <SelectItem value="this_month">This month</SelectItem>
                        <SelectItem value="last_3_months">Last 3 months</SelectItem>
                        <SelectItem value="last_6_months">Last 6 months</SelectItem>
                        <SelectItem value="last_year">Last year</SelectItem>
                        <SelectItem value="all">All</SelectItem>
                        <SelectItem value="custom">Custom</SelectItem>
                    </SelectContent>
                </Select>

                <div v-if="filterDate === 'custom'" class="flex items-center gap-2">
                    <Input type="date" v-model="filterStartDate" @change="applyFilters" class="w-[150px]" />
                    <span class="text-sm text-muted-foreground">to</span>
                    <Input type="date" v-model="filterEndDate" @change="applyFilters" class="w-[150px]" />
                </div>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <StatCard
                v-for="card in statCards"
                :key="card.key"
                :label="card.label"
                :value="card.value"
                :icon="card.icon"
                :color="card.color"
            />
            <Card class="relative overflow-hidden py-0">
                <IndianRupee
                    class="pointer-events-none absolute -right-3 -bottom-3 size-20 text-[#0ea5e9] opacity-[0.08]"
                    aria-hidden="true"
                />
                <CardContent class="flex items-center gap-3 p-4">
                    <span
                        class="flex size-9 shrink-0 items-center justify-center rounded-lg"
                        style="
                            background-color: color-mix(
                                in srgb,
                                #0ea5e9 15%,
                                transparent
                            );
                            color: #0ea5e9;
                        "
                    >
                        <IndianRupee class="size-5" />
                    </span>
                    <div class="min-w-0">
                        <p class="text-xl leading-none font-semibold">
                            {{ money(stats.quotedValue) }}
                        </p>
                        <p class="mt-1 truncate text-xs text-muted-foreground">
                            Quoted Value
                        </p>
                    </div>
                </CardContent>
            </Card>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <!-- Most enquired products -->
            <Card>
                <CardHeader class="border-b">
                    <CardTitle>Most Enquired Products</CardTitle>
                </CardHeader>
                <CardContent class="pt-6">
                    <DashboardBarChart
                        v-if="productBars.length"
                        :data="productBars"
                        color="#7c3aed"
                    />
                    <Table class="mt-4">
                        <TableHeader>
                            <TableRow>
                                <TableHead>Product</TableHead>
                                <TableHead class="text-right">
                                    Enquiries
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow
                                v-for="product in mostEnquiredProducts"
                                :key="product.id"
                            >
                                <TableCell>{{ product.name }}</TableCell>
                                <TableCell
                                    class="text-right font-medium tabular-nums"
                                >
                                    {{ product.count }}
                                </TableCell>
                            </TableRow>
                            <TableRow v-if="!mostEnquiredProducts.length">
                                <TableCell
                                    :colspan="2"
                                    class="text-center text-muted-foreground"
                                >
                                    No enquiry data yet.
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>

            <!-- Enquiries by status -->
            <Card>
                <CardHeader class="border-b">
                    <CardTitle>Enquiries by Status</CardTitle>
                </CardHeader>
                <CardContent class="pt-6">
                    <DashboardDonutChart :data="enquiryDonut" />
                    <div class="mt-4 grid grid-cols-2 gap-2">
                        <div
                            v-for="row in enquiriesByStatus"
                            :key="row.status"
                            class="flex items-center justify-between rounded-md border px-3 py-1.5 text-sm"
                        >
                            <span class="flex items-center gap-2 capitalize">
                                <span
                                    class="inline-block size-2.5 rounded-full"
                                    :style="{
                                        backgroundColor:
                                            enquiryStatusColors[row.status] ??
                                            '#94a3b8',
                                    }"
                                />
                                {{ row.status.replace('_', ' ') }}
                            </span>
                            <span class="font-medium tabular-nums">{{
                                row.count
                            }}</span>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <!-- Quotations by status -->
            <Card>
                <CardHeader class="border-b">
                    <CardTitle>Quotations by Status</CardTitle>
                </CardHeader>
                <CardContent class="pt-6">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Status</TableHead>
                                <TableHead class="text-right">Count</TableHead>
                                <TableHead class="text-right">Value</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow
                                v-for="row in quotationsByStatus"
                                :key="row.status"
                            >
                                <TableCell class="capitalize">
                                    {{ row.status }}
                                </TableCell>
                                <TableCell class="text-right tabular-nums">
                                    {{ row.count }}
                                </TableCell>
                                <TableCell class="text-right tabular-nums">
                                    {{ money(row.value) }}
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>

            <!-- Top builders -->
            <Card>
                <CardHeader class="border-b">
                    <CardTitle>Top Builders by Projects</CardTitle>
                </CardHeader>
                <CardContent class="pt-6">
                    <DashboardBarChart
                        v-if="builderBars.length"
                        :data="builderBars"
                        color="#ea580c"
                    />
                    <p
                        v-else
                        class="py-8 text-center text-sm text-muted-foreground"
                    >
                        No project data yet.
                    </p>
                </CardContent>
            </Card>
        </div>

        <!-- Staff performance -->
        <Card>
            <CardHeader class="border-b">
                <CardTitle>Staff-wise Performance</CardTitle>
            </CardHeader>
            <CardContent>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Staff</TableHead>
                            <TableHead class="text-right">Enquiries</TableHead>
                            <TableHead class="text-right">
                                Visit Reports
                            </TableHead>
                            <TableHead class="text-right">Projects</TableHead>
                            <TableHead class="text-right">Quotations</TableHead>
                            <TableHead class="text-right">
                                Quoted Value
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="staff in staffPerformance"
                            :key="staff.id"
                        >
                            <TableCell class="font-medium">
                                {{ staff.name }}
                            </TableCell>
                            <TableCell class="text-right tabular-nums">
                                {{ staff.enquiries }}
                            </TableCell>
                            <TableCell class="text-right tabular-nums">
                                {{ staff.visits }}
                            </TableCell>
                            <TableCell class="text-right tabular-nums">
                                {{ staff.projects }}
                            </TableCell>
                            <TableCell class="text-right tabular-nums">
                                {{ staff.quotations }}
                            </TableCell>
                            <TableCell class="text-right tabular-nums">
                                {{ money(staff.quotedValue) }}
                            </TableCell>
                        </TableRow>
                        <TableRow v-if="!staffPerformance.length">
                            <TableCell
                                :colspan="6"
                                class="text-center text-muted-foreground"
                            >
                                No staff activity yet.
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </CardContent>
        </Card>
    </div>
</template>
