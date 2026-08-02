<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    CheckCircle2,
    Eye,
    LayoutGrid,
    Loader,
    Pencil,
    Plus,
    Search,
    Sparkles,
    Trash2,
    X,
    XCircle,
} from '@lucide/vue';
import { watchDebounced } from '@vueuse/core';
import { computed, ref } from 'vue';
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
import { create, destroy, edit, index, kanban, show } from '@/routes/enquiries';
import type {
    EnquiryListItem,
    EnquiryStatusCount,
    Filters,
    NamedOption,
    Paginated,
} from '@/types';

const props = defineProps<{
    enquiries: Paginated<EnquiryListItem>;
    statusCounts: EnquiryStatusCount[];
    users: NamedOption[];
    filters: Filters & {
        assigned_to?: string | number;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Enquiries', href: index() }],
    },
});

const search = ref(props.filters.search ?? '');
const assignedTo = ref(
    props.filters.assigned_to ? String(props.filters.assigned_to) : 'all',
);

const updateFilters = () => {
    router.get(
        window.location.pathname,
        {
            search: search.value || undefined,
            assigned_to:
                assignedTo.value !== 'all' ? assignedTo.value : undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
};

const hasActiveFilters = computed(
    () => search.value !== '' || assignedTo.value !== 'all',
);

const clearFilters = () => {
    search.value = '';
    assignedTo.value = 'all';
    updateFilters();
};

watchDebounced(search, () => updateFilters(), { debounce: 300 });

const statusVariant = (status: string) => {
    if (status === 'converted') {
        return 'default' as const;
    }

    if (status === 'lost') {
        return 'destructive' as const;
    }

    if (status === 'in_progress') {
        return 'outline' as const;
    }

    return 'secondary' as const;
};

const statusMeta: Record<
    string,
    { label: string; icon: typeof Sparkles; color: string }
> = {
    new: { label: 'New', icon: Sparkles, color: '#3b82f6' },
    in_progress: { label: 'In progress', icon: Loader, color: '#f59e0b' },
    converted: { label: 'Converted', icon: CheckCircle2, color: '#10b981' },
    lost: { label: 'Lost', icon: XCircle, color: '#ef4444' },
};

const statCards = computed(() =>
    props.statusCounts.map((item) => ({
        key: item.status,
        label: statusMeta[item.status]?.label ?? item.status,
        icon: statusMeta[item.status]?.icon ?? Sparkles,
        color: statusMeta[item.status]?.color ?? '#94a3b8',
        count: item.count,
    })),
);

const deleteDialogOpen = ref(false);
const enquiryToDelete = ref<EnquiryListItem | null>(null);

const confirmDelete = (enquiry: EnquiryListItem) => {
    enquiryToDelete.value = enquiry;
    deleteDialogOpen.value = true;
};
</script>

<template>
    <Head title="Enquiries" />

    <div class="flex flex-col space-y-6">
        <div class="flex items-center justify-between">
            <Heading
                variant="small"
                title="Enquiries"
                description="Track and follow up on customer enquiries"
            />

            <div class="flex items-center gap-2">
                <Button variant="outline" as-child>
                    <Link :href="kanban()"><LayoutGrid /> Kanban view</Link>
                </Button>
                <Button as-child>
                    <Link :href="create()"><Plus /> New enquiry</Link>
                </Button>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
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
                <div
                    class="mb-4 flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-center"
                >
                    <div class="relative w-full max-w-sm">
                        <Search
                            class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                        />
                        <Input
                            v-model="search"
                            type="search"
                            placeholder="Search enquiries…"
                            class="px-9"
                            data-test="search-input"
                        />
                    </div>

                    <!-- Assigned To Filter -->
                    <Select
                        v-model="assignedTo"
                        @update:model-value="updateFilters"
                    >
                        <SelectTrigger class="w-full sm:w-[180px]">
                            <SelectValue placeholder="Filter by Assignee" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Assignees</SelectItem>
                            <SelectItem
                                v-for="user in users"
                                :key="user.id"
                                :value="String(user.id)"
                            >
                                {{ user.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>

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
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead class="w-12">S.No.</TableHead>
                            <TableHead>Customer / Contact</TableHead>
                            <TableHead>Project</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead>Assigned to</TableHead>
                            <TableHead class="text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="(enquiry, index) in enquiries.data"
                            :key="enquiry.id"
                        >
                            <TableCell
                                class="font-medium text-muted-foreground"
                            >
                                {{ (enquiries.from ?? 1) + index }}
                            </TableCell>
                            <TableCell class="font-medium">
                                <Link
                                    :href="show(enquiry.id)"
                                    class="hover:underline"
                                >
                                    {{
                                        enquiry.customer?.name ??
                                        enquiry.contact?.name ??
                                        '—'
                                    }}
                                </Link>
                                <span
                                    v-if="enquiry.customer && enquiry.contact"
                                    class="mt-0.5 block text-xs text-muted-foreground"
                                >
                                    c/o {{ enquiry.contact.name }}
                                </span>
                            </TableCell>
                            <TableCell>{{
                                enquiry.project?.name ?? '—'
                            }}</TableCell>
                            <TableCell>
                                <Badge
                                    :variant="statusVariant(enquiry.status)"
                                    class="capitalize"
                                >
                                    {{ enquiry.status.replace('_', ' ') }}
                                </Badge>
                            </TableCell>
                            <TableCell>{{
                                enquiry.assignee?.name ?? '—'
                            }}</TableCell>
                            <TableCell class="space-x-1.5 text-right">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    as-child
                                    class="bg-blue-50 text-blue-600 hover:bg-blue-100 hover:text-blue-800 dark:bg-blue-950/30 dark:text-blue-400 dark:hover:bg-blue-900/40 dark:hover:text-blue-300"
                                    :aria-label="`View enquiry for ${enquiry.customer?.name ?? enquiry.contact?.name}`"
                                    :data-test="`view-enquiry-${enquiry.id}`"
                                >
                                    <Link :href="show(enquiry.id)">
                                        <Eye class="h-4 w-4" />
                                    </Link>
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    as-child
                                    class="bg-amber-50 text-amber-600 hover:bg-amber-100 hover:text-amber-800 dark:bg-amber-950/30 dark:text-amber-400 dark:hover:bg-amber-900/40 dark:hover:text-amber-300"
                                    :aria-label="`Edit enquiry for ${enquiry.customer?.name ?? enquiry.contact?.name}`"
                                    :data-test="`edit-enquiry-${enquiry.id}`"
                                >
                                    <Link :href="edit(enquiry.id)">
                                        <Pencil class="h-4 w-4" />
                                    </Link>
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    class="bg-red-50 text-red-600 hover:bg-red-100 hover:text-red-800 dark:bg-red-950/30 dark:text-red-400 dark:hover:bg-red-900/40 dark:hover:text-red-300"
                                    :aria-label="`Delete enquiry for ${enquiry.customer?.name ?? enquiry.contact?.name}`"
                                    :data-test="`delete-enquiry-${enquiry.id}`"
                                    @click="confirmDelete(enquiry)"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </TableCell>
                        </TableRow>
                        <TableRow v-if="enquiries.data.length === 0">
                            <TableCell
                                :colspan="6"
                                class="text-center text-muted-foreground"
                            >
                                No enquiries yet.
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </CardContent>
        </Card>

        <TablePagination :links="enquiries.links" />
    </div>

    <ConfirmDeleteModal
        :open="deleteDialogOpen"
        title="Delete enquiry"
        :description="`This will permanently delete the enquiry for “${enquiryToDelete?.customer?.name ?? enquiryToDelete?.contact?.name}”.`"
        :delete-url="enquiryToDelete ? destroy.url(enquiryToDelete.id) : null"
        @update:open="deleteDialogOpen = $event"
    />
</template>
