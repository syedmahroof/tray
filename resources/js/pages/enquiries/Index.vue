<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    CheckCircle2,
    Eye,
    LayoutGrid,
    Loader,
    Pencil,
    Plus,
    Sparkles,
    Trash2,
    XCircle,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import ConfirmDeleteModal from '@/components/ConfirmDeleteModal.vue';
import Heading from '@/components/Heading.vue';
import SearchInput from '@/components/SearchInput.vue';
import StatCard from '@/components/StatCard.vue';
import TablePagination from '@/components/TablePagination.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
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
    Paginated,
} from '@/types';

const props = defineProps<{
    enquiries: Paginated<EnquiryListItem>;
    statusCounts: EnquiryStatusCount[];
    filters: Filters;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Enquiries', href: index() }],
    },
});

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
                <div class="mb-4">
                    <SearchInput
                        :model-value="filters.search"
                        placeholder="Search enquiries…"
                    />
                </div>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Contact</TableHead>
                            <TableHead>Project</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead>Assigned to</TableHead>
                            <TableHead class="text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="enquiry in enquiries.data"
                            :key="enquiry.id"
                        >
                            <TableCell class="font-medium">
                                <Link
                                    :href="show(enquiry.id)"
                                    class="hover:underline"
                                >
                                    {{ enquiry.contact.name }}
                                </Link>
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
                            <TableCell class="text-right">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    as-child
                                    :aria-label="`View enquiry for ${enquiry.contact.name}`"
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
                                    :aria-label="`Edit enquiry for ${enquiry.contact.name}`"
                                    :data-test="`edit-enquiry-${enquiry.id}`"
                                >
                                    <Link :href="edit(enquiry.id)">
                                        <Pencil class="h-4 w-4" />
                                    </Link>
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    :aria-label="`Delete enquiry for ${enquiry.contact.name}`"
                                    :data-test="`delete-enquiry-${enquiry.id}`"
                                    @click="confirmDelete(enquiry)"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </TableCell>
                        </TableRow>
                        <TableRow v-if="enquiries.data.length === 0">
                            <TableCell
                                :colspan="5"
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
        :description="`This will permanently delete the enquiry for “${enquiryToDelete?.contact.name}”.`"
        :delete-url="enquiryToDelete ? destroy.url(enquiryToDelete.id) : null"
        @update:open="deleteDialogOpen = $event"
    />
</template>
