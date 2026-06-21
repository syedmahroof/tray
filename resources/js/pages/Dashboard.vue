<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    Building,
    Building2,
    ClipboardList,
    HardHat,
    IdCard,
    Package,
    Users,
} from '@lucide/vue';
import { computed } from 'vue';
import DashboardBarChart from '@/components/charts/DashboardBarChart.vue';
import DashboardDonutChart from '@/components/charts/DashboardDonutChart.vue';
import Heading from '@/components/Heading.vue';
import StatCard from '@/components/StatCard.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { dashboard } from '@/routes';

const props = defineProps<{
    counts: {
        contacts: number | null;
        enquiries: number | null;
        projects: number | null;
        products: number | null;
        builders: number | null;
        branches: number | null;
        users: number | null;
    };
    enquiriesByStatus: { status: string; count: number }[] | null;
    enquiriesByMonth: { month: string; count: number }[] | null;
    contactsByType: { type: string; count: number }[] | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
    },
});

const statCards = computed(() =>
    (
        [
            {
                key: 'contacts',
                label: 'Contacts',
                icon: IdCard,
                color: '#2563eb',
            },
            {
                key: 'enquiries',
                label: 'Enquiries',
                icon: ClipboardList,
                color: '#d97706',
            },
            {
                key: 'projects',
                label: 'Projects',
                icon: Building,
                color: '#7c3aed',
            },
            {
                key: 'products',
                label: 'Products',
                icon: Package,
                color: '#059669',
            },
            {
                key: 'builders',
                label: 'Builders',
                icon: HardHat,
                color: '#ea580c',
            },
            {
                key: 'branches',
                label: 'Branches',
                icon: Building2,
                color: '#0891b2',
            },
            { key: 'users', label: 'Users', icon: Users, color: '#db2777' },
        ] as const
    ).filter((card) => props.counts[card.key] !== null),
);

const statusColors: Record<string, string> = {
    new: '#3b82f6',
    in_progress: '#f59e0b',
    converted: '#10b981',
    lost: '#ef4444',
};

const donutData = computed(
    () =>
        props.enquiriesByStatus?.map((item) => ({
            label: item.status.replace('_', ' '),
            value: item.count,
            color: statusColors[item.status] ?? '#94a3b8',
        })) ?? [],
);

const monthlyData = computed(
    () =>
        props.enquiriesByMonth?.map((item) => ({
            label: item.month,
            value: item.count,
        })) ?? [],
);

const contactsByTypeData = computed(
    () =>
        props.contactsByType?.map((item) => ({
            label: item.type,
            value: item.count,
        })) ?? [],
);
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex flex-col gap-6">
        <Heading
            title="Dashboard"
            description="An overview of your CRM activity"
        />

        <div
            class="grid gap-4 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6 xl:grid-cols-6"
        >
            <StatCard
                v-for="card in statCards"
                :key="card.key"
                :label="card.label"
                :value="counts[card.key]!"
                :icon="card.icon"
                :color="card.color"
            />
        </div>

        <div
            v-if="enquiriesByStatus || enquiriesByMonth"
            class="grid gap-4 lg:grid-cols-2"
        >
            <Card v-if="enquiriesByStatus">
                <CardHeader>
                    <CardTitle>Enquiries by status</CardTitle>
                </CardHeader>
                <CardContent>
                    <DashboardDonutChart :data="donutData" />
                </CardContent>
            </Card>

            <Card v-if="enquiriesByMonth">
                <CardHeader>
                    <CardTitle>Enquiries per month</CardTitle>
                </CardHeader>
                <CardContent>
                    <DashboardBarChart :data="monthlyData" color="#2563eb" />
                </CardContent>
            </Card>
        </div>

        <Card v-if="contactsByType">
            <CardHeader>
                <CardTitle>Contacts by type</CardTitle>
            </CardHeader>
            <CardContent>
                <DashboardBarChart :data="contactsByTypeData" color="#7c3aed" />
            </CardContent>
        </Card>
    </div>
</template>
