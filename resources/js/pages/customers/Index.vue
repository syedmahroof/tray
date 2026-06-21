<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Eye, Pencil, Plus, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import ConfirmDeleteModal from '@/components/ConfirmDeleteModal.vue';
import Heading from '@/components/Heading.vue';
import SearchInput from '@/components/SearchInput.vue';
import TablePagination from '@/components/TablePagination.vue';
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
import { create, destroy, edit, index, show } from '@/routes/customers';
import type { CustomerListItem, Filters, Paginated } from '@/types';

defineProps<{
    customers: Paginated<CustomerListItem>;
    filters: Filters;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Customers', href: index() }],
    },
});

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
                <div class="mb-4">
                    <SearchInput
                        :model-value="filters.search"
                        placeholder="Search customers…"
                    />
                </div>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Name</TableHead>
                            <TableHead>Contact</TableHead>
                            <TableHead>Assigned to</TableHead>
                            <TableHead class="text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="customer in customers.data"
                            :key="customer.id"
                        >
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
                            <TableCell class="text-right">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    as-child
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
                                :colspan="4"
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
