<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Eye, Pencil, Plus, Search, Trash2, X } from '@lucide/vue';
import { watchDebounced } from '@vueuse/core';
import { computed, ref } from 'vue';
import ConfirmDeleteModal from '@/components/ConfirmDeleteModal.vue';
import Heading from '@/components/Heading.vue';
import TablePagination from '@/components/TablePagination.vue';
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
import { create, destroy, edit, index, show } from '@/routes/customers';
import type {
    CustomerListItem,
    Filters,
    NamedOption,
    Paginated,
} from '@/types';

const props = defineProps<{
    customers: Paginated<CustomerListItem>;
    users: NamedOption[];
    filters: Filters & {
        assigned_to?: string | number;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Customers', href: index() }],
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

const deleteDialogOpen = ref(false);
const customerToDelete = ref<CustomerListItem | null>(null);

const confirmDelete = (customer: CustomerListItem) => {
    customerToDelete.value = customer;
    deleteDialogOpen.value = true;
};
</script>

<template>
    <Head title="Customers" />

    <div class="flex flex-col space-y-6">
        <div class="flex items-center justify-between">
            <Heading
                variant="small"
                title="Customers"
                description="Manage your customers"
            />

            <Button as-child>
                <Link :href="create()"><Plus /> New customer</Link>
            </Button>
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
                            placeholder="Search customers…"
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
                            <TableHead>Name</TableHead>
                            <TableHead>Contact</TableHead>
                            <TableHead>Assigned to</TableHead>
                            <TableHead class="text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="(customer, index) in customers.data"
                            :key="customer.id"
                        >
                            <TableCell
                                class="font-medium text-muted-foreground"
                            >
                                {{ (customers.from ?? 1) + index }}
                            </TableCell>
                            <TableCell class="font-medium">
                                <Link
                                    :href="show(customer.id)"
                                    class="hover:underline"
                                >
                                    {{ customer.name }}
                                </Link>
                            </TableCell>
                            <TableCell>
                                <div>{{ customer.phone ?? '—' }}</div>
                                <div class="text-sm text-muted-foreground">
                                    {{ customer.email ?? '' }}
                                </div>
                            </TableCell>
                            <TableCell>{{
                                customer.assignee?.name ?? '—'
                            }}</TableCell>
                            <TableCell class="space-x-1.5 text-right">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    as-child
                                    class="bg-blue-50 text-blue-600 hover:bg-blue-100 hover:text-blue-800 dark:bg-blue-950/30 dark:text-blue-400 dark:hover:bg-blue-900/40 dark:hover:text-blue-300"
                                    :aria-label="`View ${customer.name}`"
                                    :data-test="`view-customer-${customer.id}`"
                                >
                                    <Link :href="show(customer.id)">
                                        <Eye class="h-4 w-4" />
                                    </Link>
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    as-child
                                    class="bg-amber-50 text-amber-600 hover:bg-amber-100 hover:text-amber-800 dark:bg-amber-950/30 dark:text-amber-400 dark:hover:bg-amber-900/40 dark:hover:text-amber-300"
                                    :aria-label="`Edit ${customer.name}`"
                                    :data-test="`edit-customer-${customer.id}`"
                                >
                                    <Link :href="edit(customer.id)">
                                        <Pencil class="h-4 w-4" />
                                    </Link>
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    class="bg-red-50 text-red-600 hover:bg-red-100 hover:text-red-800 dark:bg-red-950/30 dark:text-red-400 dark:hover:bg-red-900/40 dark:hover:text-red-300"
                                    :aria-label="`Delete ${customer.name}`"
                                    :data-test="`delete-customer-${customer.id}`"
                                    @click="confirmDelete(customer)"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </TableCell>
                        </TableRow>
                        <TableRow v-if="customers.data.length === 0">
                            <TableCell
                                :colspan="5"
                                class="text-center text-muted-foreground"
                            >
                                No customers yet.
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </CardContent>
        </Card>

        <TablePagination :links="customers.links" />
    </div>

    <ConfirmDeleteModal
        :open="deleteDialogOpen"
        title="Delete customer"
        :description="`This will permanently delete “${customerToDelete?.name}”.`"
        :delete-url="customerToDelete ? destroy.url(customerToDelete.id) : null"
        @update:open="deleteDialogOpen = $event"
    />
</template>
