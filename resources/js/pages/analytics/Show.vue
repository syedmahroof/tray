<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import AnalyticsPeriodFilter from '@/components/analytics/AnalyticsPeriodFilter.vue';
import AnalyticsRankedBars from '@/components/analytics/AnalyticsRankedBars.vue';
import AnalyticsStatCard from '@/components/analytics/AnalyticsStatCard.vue';
import AnalyticsTrendChart from '@/components/analytics/AnalyticsTrendChart.vue';
import DashboardDonutChart from '@/components/charts/DashboardDonutChart.vue';
import Heading from '@/components/Heading.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatAnalytics } from '@/lib/analytics';
import { index as analyticsIndex, show } from '@/routes/analytics';
import type {
    AnalyticsChart,
    AnalyticsFormat,
    AnalyticsPeriod,
    AnalyticsReport,
} from '@/types';

const props = defineProps<{
    section: { key: string; title: string; description: string };
    sections: { key: string; title: string }[];
    period: AnalyticsPeriod;
    previousPeriod: AnalyticsPeriod;
    periods: { value: string; label: string }[];
    report: AnalyticsReport;
}>();

defineOptions({
    layout: (props: { section: { key: string; title: string } }) => ({
        breadcrumbs: [
            { title: 'Analytics', href: analyticsIndex() },
            { title: props.section.title, href: show(props.section.key) },
        ],
    }),
});

const trends = computed(() =>
    props.report.charts.filter((chart) => chart.type === 'trend'),
);

const breakdowns = computed(() =>
    props.report.charts.filter((chart) => chart.type !== 'trend'),
);

const hasData = (chart: AnalyticsChart) =>
    chart.data.some((datum) => datum.value > 0);

const numeric: AnalyticsFormat[] = ['number', 'money', 'percent'];

const previousLabel = computed(
    () => `${props.previousPeriod.from} to ${props.previousPeriod.to}`,
);

/** Keep the chosen period when switching section. */
const sectionHref = (key: string) =>
    show(key, {
        query:
            props.period.key === 'custom'
                ? {
                      period: 'custom',
                      from: props.period.from,
                      to: props.period.to,
                  }
                : { period: props.period.key },
    });
</script>

<template>
    <Head :title="`${section.title} Analytics`" />

    <div class="flex flex-col gap-6">
        <div
            class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between"
        >
            <div>
                <Heading
                    variant="small"
                    :title="`${section.title} Analytics`"
                    :description="section.description"
                />
                <p
                    class="mt-1 text-xs text-muted-foreground"
                    data-test="period-label"
                >
                    {{ period.label }} · {{ period.from }} to {{ period.to }}
                </p>
            </div>
            <AnalyticsPeriodFilter :period="period" :periods="periods" />
        </div>

        <!-- The other sections, one tap away -->
        <nav
            class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1 sm:mx-0 sm:flex-wrap sm:px-0"
            aria-label="Analytics sections"
        >
            <Link
                v-for="item in sections"
                :key="item.key"
                :href="sectionHref(item.key)"
                :data-test="`section-${item.key}`"
                class="shrink-0 rounded-full border px-3 py-1.5 text-sm whitespace-nowrap transition-colors"
                :class="
                    item.key === section.key
                        ? 'border-primary bg-primary text-primary-foreground'
                        : 'bg-card text-muted-foreground hover:bg-muted hover:text-foreground'
                "
                preserve-scroll
            >
                {{ item.title }}
            </Link>
        </nav>

        <div
            class="grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-5"
            data-test="analytics-stats"
        >
            <AnalyticsStatCard
                v-for="stat in report.stats"
                :key="stat.label"
                :stat="stat"
                :previous-label="previousLabel"
            />
        </div>

        <Card v-for="chart in trends" :key="chart.title">
            <CardHeader>
                <CardTitle class="text-base">{{ chart.title }}</CardTitle>
            </CardHeader>
            <CardContent>
                <AnalyticsTrendChart
                    v-if="hasData(chart)"
                    :data="chart.data"
                    :format="chart.format"
                />
                <p
                    v-else
                    class="py-16 text-center text-sm text-muted-foreground"
                >
                    Nothing recorded in this period.
                </p>
            </CardContent>
        </Card>

        <div class="grid gap-6 lg:grid-cols-2">
            <Card v-for="chart in breakdowns" :key="chart.title">
                <CardHeader>
                    <CardTitle class="text-base">{{ chart.title }}</CardTitle>
                </CardHeader>
                <CardContent>
                    <template v-if="hasData(chart)">
                        <DashboardDonutChart
                            v-if="chart.type === 'donut'"
                            :data="
                                chart.data.map((datum) => ({
                                    ...datum,
                                    color: datum.color ?? '#4f46e5',
                                }))
                            "
                            :height="180"
                        />
                        <AnalyticsRankedBars
                            v-else
                            :data="chart.data"
                            :format="chart.format"
                        />
                    </template>
                    <p
                        v-else
                        class="py-10 text-center text-sm text-muted-foreground"
                    >
                        Nothing recorded in this period.
                    </p>
                </CardContent>
            </Card>
        </div>

        <Card v-for="table in report.tables" :key="table.title" class="gap-0">
            <CardHeader class="border-b">
                <CardTitle class="text-base">{{ table.title }}</CardTitle>
            </CardHeader>
            <CardContent class="px-0">
                <Table v-if="table.rows.length">
                    <TableHeader>
                        <TableRow class="bg-muted/50 hover:bg-muted/50">
                            <TableHead
                                v-for="(column, index) in table.columns"
                                :key="column.key"
                                :class="[
                                    numeric.includes(column.format)
                                        ? 'text-right'
                                        : '',
                                    index === 0 ? 'pl-6' : '',
                                    index === table.columns.length - 1
                                        ? 'pr-6'
                                        : '',
                                ]"
                            >
                                {{ column.label }}
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="(row, rowIndex) in table.rows"
                            :key="rowIndex"
                        >
                            <TableCell
                                v-for="(column, index) in table.columns"
                                :key="column.key"
                                :class="[
                                    numeric.includes(column.format)
                                        ? 'text-right tabular-nums'
                                        : '',
                                    index === 0 ? 'pl-6 font-medium' : '',
                                    index === table.columns.length - 1
                                        ? 'pr-6'
                                        : '',
                                ]"
                            >
                                {{
                                    formatAnalytics(
                                        row[column.key],
                                        column.format,
                                    )
                                }}
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
                <p
                    v-else
                    class="py-10 text-center text-sm text-muted-foreground"
                >
                    Nothing recorded in this period.
                </p>
            </CardContent>
        </Card>
    </div>
</template>
