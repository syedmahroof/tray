<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    CircleCheckBig,
    Download,
    Eye,
    FileText,
    Pencil,
    Plus,
    Search,
    Send,
    Trash2,
    User,
    X,
    PencilLine,
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
import { create, destroy, edit, index, pdf, show } from '@/routes/quotations';
import type {
    Filters,
    NamedOption,
    Paginated,
    QuotationListItem,
} from '@/types';

const props = defineProps<{
    quotations: Paginated<QuotationListItem>;
    statuses: string[];
    users: NamedOption[];
    stats: {
        total: number;
        draft: number;
        sent: number;
        accepted: number;
    };
    filters: Filters & {
        status?: string;
        created_by?: string | number;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Quotations', href: index() }],
    },
});

const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? 'all');
const createdBy = ref(
    props.filters.created_by ? String(props.filters.created_by) : 'all',
);

const updateFilters = () => {
    router.get(
        window.location.pathname,
        {
            search: search.value || undefined,
            status: status.value !== 'all' ? status.value : undefined,
            created_by: createdBy.value !== 'all' ? createdBy.value : undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

const hasActiveFilters = computed(
    () =>
        search.value !== '' ||
        status.value !== 'all' ||
        createdBy.value !== 'all',
);

const clearFilters = () => {
    search.value = '';
    status.value = 'all';
    createdBy.value = 'all';
    updateFilters();
};

watchDebounced(search, () => updateFilters(), { debounce: 300 });

const statCards = computed(() => [
    {
        key: 'total',
        label: 'Total Quotations',
        value: props.stats.total,
        icon: FileText,
        color: '#4f46e5',
    },
    {
        key: 'draft',
        label: 'Draft',
        value: props.stats.draft,
        icon: PencilLine,
        color: '#6b7280',
    },
    {
        key: 'sent',
        label: 'Sent',
        value: props.stats.sent,
        icon: Send,
        color: '#0ea5e9',
    },
    {
        key: 'accepted',
        label: 'Accepted',
        value: props.stats.accepted,
        icon: CircleCheckBig,
        color: '#16a34a',
    },
]);

const statusVariant = (value: string) => {
    if (value === 'accepted') {
        return 'default' as const;
    }

    if (value === 'rejected' || value === 'expired') {
        return 'destructive' as const;
    }

    if (value === 'sent') {
        return 'outline' as const;
    }

    return 'secondary' as const;
};

const money = (value: string) =>
    `₹${Number(value).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

const deleteDialogOpen = ref(false);
const quotationToDelete = ref<QuotationListItem | null>(null);

const confirmDelete = (quotation: QuotationListItem) => {
    quotationToDelete.value = quotation;
    deleteDialogOpen.value = true;
};
</script>

<template>
    <Head title="Quotations" />

    <div class="flex flex-col space-y-6">
        <div class="flex items-center justify-between">
            <Heading
                variant="small"
                title="Quotations"
                description="Create and manage price quotations"
            />

            <Button as-child>
                <Link :href="create()"><Plus /> New quotation</Link>
            </Button>
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
                            placeholder="Search number or contact…"
                            class="px-9"
                            data-test="search-input"
                        />
                    </div>

                    <Select
                        v-model="status"
                        @update:model-value="updateFilters"
                    >
                        <SelectTrigger class="w-full sm:w-[160px]">
                            <SelectValue placeholder="All Statuses" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Statuses</SelectItem>
                            <SelectItem
                                v-for="stat in statuses"
                                :key="stat"
                                :value="stat"
                                class="capitalize"
                            >
                                {{ stat }}
                            </SelectItem>
                        </SelectContent>
                    </Select>

                    <Select
                        v-model="createdBy"
                        @update:model-value="updateFilters"
                    >
                        <SelectTrigger
                            class="flex w-full items-center gap-1.5 sm:w-[170px]"
                        >
                            <User class="h-4 w-4 text-[#0ea5e9]" />
                            <SelectValue placeholder="Created by" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Creators</SelectItem>
                            <SelectItem
                                v-for="user in users"
                                :key="user.id"
                                :value="String(user.id)"
                            >
                                {{ user.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>

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
                            <TableHead>Number</TableHead>
                            <TableHead>Contact</TableHead>
                            <TableHead>Date</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead class="text-right">Total</TableHead>
                            <TableHead>Created</TableHead>
                            <TableHead class="text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="quotation in quotations.data"
                            :key="quotation.id"
                        >
                            <TableCell class="font-medium">
                                <Link
                                    :href="show(quotation.id)"
                                    class="font-semibold text-foreground hover:underline"
                                >
                                    {{ quotation.number }}
                                </Link>
                            </TableCell>
                            <TableCell>{{
                                quotation.contact?.name ?? '—'
                            }}</TableCell>
                            <TableCell>{{
                                formatDate(quotation.quotation_date)
                            }}</TableCell>
                            <TableCell>
                                <Badge
                                    :variant="statusVariant(quotation.status)"
                                    class="capitalize"
                                >
                                    {{ quotation.status }}
                                </Badge>
                            </TableCell>
                            <TableCell class="text-right tabular-nums">{{
                                money(quotation.total)
                            }}</TableCell>
                            <TableCell>
                                <div class="text-sm">
                                    {{ formatDate(quotation.created_at) }}
                                </div>
                                <div class="text-xs text-muted-foreground">
                                    {{ quotation.creator?.name ?? '—' }}
                                </div>
                            </TableCell>
                            <TableCell class="space-x-1.5 text-right">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    as-child
                                    :aria-label="`View ${quotation.number}`"
                                    :data-test="`view-quotation-${quotation.id}`"
                                >
                                    <Link :href="show(quotation.id)">
                                        <Eye class="h-4 w-4" />
                                    </Link>
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    as-child
                                    :aria-label="`Download ${quotation.number}`"
                                >
                                    <a :href="pdf.url(quotation.id)">
                                        <Download class="h-4 w-4" />
                                    </a>
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    as-child
                                    :aria-label="`Edit ${quotation.number}`"
                                    :data-test="`edit-quotation-${quotation.id}`"
                                >
                                    <Link :href="edit(quotation.id)">
                                        <Pencil class="h-4 w-4" />
                                    </Link>
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    :aria-label="`Delete ${quotation.number}`"
                                    :data-test="`delete-quotation-${quotation.id}`"
                                    @click="confirmDelete(quotation)"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </TableCell>
                        </TableRow>
                        <TableRow v-if="quotations.data.length === 0">
                            <TableCell
                                :colspan="7"
                                class="text-center text-muted-foreground"
                            >
                                No quotations yet.
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </CardContent>
        </Card>

        <TablePagination :links="quotations.links" />
    </div>

    <ConfirmDeleteModal
        :open="deleteDialogOpen"
        title="Delete quotation"
        :description="`This will permanently delete “${quotationToDelete?.number}”.`"
        :delete-url="
            quotationToDelete ? destroy.url(quotationToDelete.id) : null
        "
        @update:open="deleteDialogOpen = $event"
    />
</template>
