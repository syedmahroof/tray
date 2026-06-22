<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    BarChart3,
    Briefcase,
    CalendarDays,
    Eye,
    Phone,
    Pencil,
    Plus,
    Search,
    Trash2,
    Users,
    X,
} from '@lucide/vue';
import { watchDebounced } from '@vueuse/core';
import { computed, ref } from 'vue';
import ConfirmDeleteModal from '@/components/ConfirmDeleteModal.vue';
import Heading from '@/components/Heading.vue';
import StatCard from '@/components/StatCard.vue';
import TablePagination from '@/components/TablePagination.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDate } from '@/lib/utils';
import {
    analytics,
    create,
    destroy,
    edit,
    index,
    show,
} from '@/routes/visit-reports';
import type {
    Filters,
    Paginated,
    VisitReportListItem,
    VisitReportTypeCount,
    NamedOption,
    VisitType,
} from '@/types';

const props = defineProps<{
    visitReports: Paginated<VisitReportListItem>;
    visitReportsByType: VisitReportTypeCount[];
    visitTypes: VisitType[];
    users: NamedOption[];
    projects: NamedOption[];
    filters: Filters & {
        visit_type?: string;
        user_id?: string | number;
        project_id?: string | number;
        created_from?: string;
        created_to?: string;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Visit Reports', href: index() }],
    },
});

const search = ref(props.filters.search ?? '');
const visitType = ref(props.filters.visit_type ?? 'all');
const userId = ref(
    props.filters.user_id ? String(props.filters.user_id) : 'all',
);
const projectId = ref(
    props.filters.project_id ? String(props.filters.project_id) : 'all',
);
const createdFrom = ref(props.filters.created_from ?? '');
const createdTo = ref(props.filters.created_to ?? '');

const updateFilters = () => {
    router.get(
        window.location.pathname,
        {
            search: search.value || undefined,
            visit_type: visitType.value !== 'all' ? visitType.value : undefined,
            user_id: userId.value !== 'all' ? userId.value : undefined,
            project_id: projectId.value !== 'all' ? projectId.value : undefined,
            created_from: createdFrom.value || undefined,
            created_to: createdTo.value || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
};

const hasActiveFilters = computed(
    () =>
        search.value !== '' ||
        visitType.value !== 'all' ||
        userId.value !== 'all' ||
        projectId.value !== 'all' ||
        createdFrom.value !== '' ||
        createdTo.value !== '',
);

const clearFilters = () => {
    search.value = '';
    visitType.value = 'all';
    userId.value = 'all';
    projectId.value = 'all';
    createdFrom.value = '';
    createdTo.value = '';
    updateFilters();
};

watchDebounced(search, () => updateFilters(), { debounce: 300 });

const typeMeta: Record<string, { icon: typeof Search; color: string }> = {
    'Site Visit': { icon: Search, color: '#3b82f6' },
    'Client Meeting': { icon: Users, color: '#f59e0b' },
    'Follow-up': { icon: Phone, color: '#10b981' },
    Inspection: { icon: Briefcase, color: '#7c3aed' },
};

const statCards = computed(() =>
    props.visitReportsByType.map((item) => ({
        key: item.type,
        label: item.type,
        icon: typeMeta[item.type]?.icon ?? Briefcase,
        color: typeMeta[item.type]?.color ?? '#94a3b8',
        count: item.count,
    })),
);

const deleteDialogOpen = ref(false);
const visitReportToDelete = ref<VisitReportListItem | null>(null);

const confirmDelete = (visitReport: VisitReportListItem) => {
    visitReportToDelete.value = visitReport;
    deleteDialogOpen.value = true;
};

const linkedEntities = (visitReport: VisitReportListItem) => [
    ...visitReport.projects.map((p) => p.name),
    ...visitReport.customers.map((c) => c.name),
    ...visitReport.contacts.map((c) => c.name),
];
</script>

<template>
    <Head title="Visit Reports" />

    <div class="relative flex flex-col space-y-6">
        <!-- Light Infographics Background -->
        <div
            class="pointer-events-none absolute inset-0 -z-10 overflow-hidden opacity-[0.03] dark:opacity-[0.05]"
        >
            <svg
                class="absolute top-0 right-0 h-[400px] w-[600px] text-primary"
                viewBox="0 0 600 400"
                fill="none"
                xmlns="http://www.w3.org/2000/svg"
            >
                <!-- Grid lines -->
                <path
                    d="M 0 50 L 600 50 M 0 100 L 600 100 M 0 150 L 600 150 M 0 200 L 600 200 M 0 250 L 600 250 M 0 300 L 600 300 M 0 350 L 600 350"
                    stroke="currentColor"
                    stroke-width="1"
                />
                <path
                    d="M 50 0 L 50 400 M 100 0 L 100 400 M 150 0 L 150 400 M 200 0 L 200 400 M 250 0 L 250 400 M 300 0 L 300 400 M 350 0 L 350 400 M 400 0 L 400 400 M 450 0 L 450 400 M 500 0 L 500 400 M 550 0 L 550 400"
                    stroke="currentColor"
                    stroke-width="1"
                />

                <!-- Chart Line 1 -->
                <path
                    d="M 50 300 Q 150 250 250 150 T 450 100 T 550 50"
                    stroke="#0ea5e9"
                    stroke-width="3"
                    stroke-linecap="round"
                />
                <!-- Chart Line 2 -->
                <path
                    d="M 50 350 Q 120 200 220 280 T 420 180 T 550 120"
                    stroke="#10b981"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-dasharray="6,4"
                />

                <!-- Graphic Circles/Dots -->
                <circle cx="50" cy="300" r="6" fill="#0ea5e9" />
                <circle cx="250" cy="150" r="6" fill="#0ea5e9" />
                <circle cx="450" cy="100" r="6" fill="#0ea5e9" />
                <circle cx="550" cy="50" r="6" fill="#0ea5e9" />

                <circle cx="220" cy="280" r="4" fill="#10b981" />
                <circle cx="420" cy="180" r="4" fill="#10b981" />
                <circle cx="550" cy="120" r="4" fill="#10b981" />

                <!-- Bars -->
                <rect
                    x="80"
                    y="200"
                    width="20"
                    height="200"
                    rx="4"
                    fill="currentColor"
                    opacity="0.5"
                />
                <rect
                    x="180"
                    y="120"
                    width="20"
                    height="280"
                    rx="4"
                    fill="currentColor"
                    opacity="0.5"
                />
                <rect
                    x="280"
                    y="180"
                    width="20"
                    height="220"
                    rx="4"
                    fill="currentColor"
                    opacity="0.5"
                />
                <rect
                    x="380"
                    y="80"
                    width="20"
                    height="320"
                    rx="4"
                    fill="currentColor"
                    opacity="0.5"
                />
                <rect
                    x="480"
                    y="140"
                    width="20"
                    height="260"
                    rx="4"
                    fill="currentColor"
                    opacity="0.5"
                />
            </svg>
        </div>
        <div class="flex items-center justify-between">
            <Heading
                variant="small"
                title="Visit Reports"
                description="Track site visits, client meetings, and follow-ups"
            />

            <div class="flex items-center gap-2">
                <Button variant="outline" as-child>
                    <Link :href="analytics()"><BarChart3 /> Analytics</Link>
                </Button>
                <Button as-child>
                    <Link :href="create()"><Plus /> New visit report</Link>
                </Button>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
            <StatCard
                v-for="card in statCards"
                :key="card.key"
                :label="card.label"
                :value="card.count"
                :icon="card.icon"
                :color="card.color"
            />
        </div>

        <Card>
            <CardHeader class="border-b">
                <div
                    class="flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-center"
                >
                    <div class="relative w-full max-w-sm">
                        <Search
                            class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                        />
                        <Input
                            v-model="search"
                            type="search"
                            placeholder="Search visit reports…"
                            class="px-9"
                            data-test="search-input"
                        />
                    </div>

                    <!-- Visit Type Filter -->
                    <Select
                        v-model="visitType"
                        @update:model-value="updateFilters"
                    >
                        <SelectTrigger class="w-full sm:w-[160px]">
                            <SelectValue placeholder="Filter by Type" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Types</SelectItem>
                            <SelectItem
                                v-for="typeVal in visitTypes"
                                :key="typeVal"
                                :value="typeVal"
                            >
                                {{ typeVal }}
                            </SelectItem>
                        </SelectContent>
                    </Select>

                    <!-- Reported By Filter -->
                    <Select
                        v-model="userId"
                        @update:model-value="updateFilters"
                    >
                        <SelectTrigger class="w-full sm:w-[180px]">
                            <SelectValue placeholder="Filter by Reported By" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Reporters</SelectItem>
                            <SelectItem
                                v-for="user in users"
                                :key="user.id"
                                :value="String(user.id)"
                            >
                                {{ user.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>

                    <!-- Project Filter -->
                    <Select
                        v-model="projectId"
                        @update:model-value="updateFilters"
                    >
                        <SelectTrigger class="w-full sm:w-[200px]">
                            <SelectValue placeholder="Filter by Project" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Projects</SelectItem>
                            <SelectItem
                                v-for="project in projects"
                                :key="project.id"
                                :value="String(project.id)"
                            >
                                {{ project.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>

                    <!-- Created Date Range -->
                    <div class="flex items-center gap-1.5">
                        <CalendarDays class="h-4 w-4 text-[#16a34a]" />
                        <Input
                            v-model="createdFrom"
                            type="date"
                            class="w-[150px]"
                            aria-label="Created from"
                            @change="updateFilters"
                        />
                        <span class="text-sm text-muted-foreground">to</span>
                        <Input
                            v-model="createdTo"
                            type="date"
                            class="w-[150px]"
                            aria-label="Created to"
                            @change="updateFilters"
                        />
                    </div>

                    <!-- Clear Filters -->
                    <Button
                        v-if="hasActiveFilters"
                        variant="ghost"
                        size="sm"
                        class="text-muted-foreground"
                        data-test="clear-filters"
                        @click="clearFilters"
                    >
                        <X class="h-4 w-4" /> Clear
                    </Button>
                </div>
            </CardHeader>
            <CardContent>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead class="w-12">S.No.</TableHead>
                            <TableHead>Visit Date</TableHead>
                            <TableHead>Type</TableHead>
                            <TableHead>Objective</TableHead>
                            <TableHead>Linked to</TableHead>
                            <TableHead>Reported by</TableHead>
                            <TableHead>Created</TableHead>
                            <TableHead class="text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="(visitReport, index) in visitReports.data"
                            :key="visitReport.id"
                        >
                            <TableCell
                                class="font-medium text-muted-foreground"
                            >
                                {{ (visitReports.from ?? 1) + index }}
                            </TableCell>
                            <TableCell class="font-medium">
                                <Link
                                    :href="show(visitReport.id)"
                                    class="hover:underline"
                                >
                                    {{ formatDate(visitReport.visit_date) }}
                                </Link>
                            </TableCell>
                            <TableCell>{{ visitReport.visit_type }}</TableCell>
                            <TableCell>{{ visitReport.objective }}</TableCell>
                            <TableCell>
                                <div class="flex flex-wrap gap-1">
                                    <Badge
                                        v-for="name in linkedEntities(
                                            visitReport,
                                        )"
                                        :key="name"
                                        variant="secondary"
                                    >
                                        {{ name }}
                                    </Badge>
                                </div>
                            </TableCell>
                            <TableCell>{{ visitReport.user.name }}</TableCell>
                            <TableCell class="text-sm text-muted-foreground">
                                {{ formatDate(visitReport.created_at) }}
                            </TableCell>
                            <TableCell class="space-x-1.5 text-right">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    as-child
                                    class="bg-blue-50 text-blue-600 hover:bg-blue-100 hover:text-blue-800 dark:bg-blue-950/30 dark:text-blue-400 dark:hover:bg-blue-900/40 dark:hover:text-blue-300"
                                    :aria-label="`View visit report ${visitReport.id}`"
                                    :data-test="`view-visit-report-${visitReport.id}`"
                                >
                                    <Link :href="show(visitReport.id)">
                                        <Eye class="h-4 w-4" />
                                    </Link>
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    as-child
                                    class="bg-amber-50 text-amber-600 hover:bg-amber-100 hover:text-amber-800 dark:bg-amber-950/30 dark:text-amber-400 dark:hover:bg-amber-900/40 dark:hover:text-amber-300"
                                    :aria-label="`Edit visit report ${visitReport.id}`"
                                    :data-test="`edit-visit-report-${visitReport.id}`"
                                >
                                    <Link :href="edit(visitReport.id)">
                                        <Pencil class="h-4 w-4" />
                                    </Link>
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    class="bg-red-50 text-red-600 hover:bg-red-100 hover:text-red-800 dark:bg-red-950/30 dark:text-red-400 dark:hover:bg-red-900/40 dark:hover:text-red-300"
                                    :aria-label="`Delete visit report ${visitReport.id}`"
                                    :data-test="`delete-visit-report-${visitReport.id}`"
                                    @click="confirmDelete(visitReport)"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </TableCell>
                        </TableRow>
                        <TableRow v-if="visitReports.data.length === 0">
                            <TableCell
                                :colspan="8"
                                class="text-center text-muted-foreground"
                            >
                                No visit reports yet.
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </CardContent>
        </Card>

        <TablePagination :links="visitReports.links" />
    </div>

    <ConfirmDeleteModal
        :open="deleteDialogOpen"
        title="Delete visit report"
        description="This will permanently delete this visit report."
        :delete-url="
            visitReportToDelete ? destroy.url(visitReportToDelete.id) : null
        "
        @update:open="deleteDialogOpen = $event"
    />
</template>
