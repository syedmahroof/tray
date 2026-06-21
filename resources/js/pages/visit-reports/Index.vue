<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    BarChart3,
    Briefcase,
    Eye,
    Phone,
    Pencil,
    Plus,
    Search,
    Trash2,
    Users,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { watchDebounced } from '@vueuse/core';
import ConfirmDeleteModal from '@/components/ConfirmDeleteModal.vue';
import Heading from '@/components/Heading.vue';
import StatCard from '@/components/StatCard.vue';
import TablePagination from '@/components/TablePagination.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
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
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Visit Reports', href: index() }],
    },
});

const search = ref(props.filters.search ?? '');
const visitType = ref(props.filters.visit_type ?? 'all');
const userId = ref(props.filters.user_id ? String(props.filters.user_id) : 'all');
const projectId = ref(props.filters.project_id ? String(props.filters.project_id) : 'all');

const updateFilters = () => {
    router.get(
        window.location.pathname,
        {
            search: search.value || undefined,
            visit_type: visitType.value !== 'all' ? visitType.value : undefined,
            user_id: userId.value !== 'all' ? userId.value : undefined,
            project_id: projectId.value !== 'all' ? projectId.value : undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        }
    );
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

    <div class="flex flex-col space-y-6">
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

        <div
            class="grid gap-4 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6 xl:grid-cols-6"
        >
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
            <CardContent>
                <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:flex-wrap">
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
                    <Select v-model="visitType" @update:model-value="updateFilters">
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
                    <Select v-model="userId" @update:model-value="updateFilters">
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
                    <Select v-model="projectId" @update:model-value="updateFilters">
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
                </div>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Visit Date</TableHead>
                            <TableHead>Type</TableHead>
                            <TableHead>Objective</TableHead>
                            <TableHead>Linked to</TableHead>
                            <TableHead>Reported by</TableHead>
                            <TableHead class="text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="visitReport in visitReports.data"
                            :key="visitReport.id"
                        >
                            <TableCell class="font-medium">
                                <Link
                                    :href="show(visitReport.id)"
                                    class="hover:underline"
                                >
                                    {{ visitReport.visit_date }}
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
                            <TableCell class="text-right">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    as-child
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
                                :colspan="6"
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
