<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    CheckCheck,
    ClipboardList,
    Eye,
    FilePen,
    Pencil,
    Plus,
    Search,
    Send,
    Trash2,
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
import {
    formatMoney as money,
    orderResource,
    orderRoutes,
    orderStatusLabel as label,
    orderStatusVariant as variant,
    orderTitle,
} from '@/lib/orders';
import { formatDate } from '@/lib/utils';
import type { Filters, OrderListItem, OrderType, Paginated } from '@/types';

const props = defineProps<{
    orderType: OrderType;
    orders: Paginated<OrderListItem>;
    statuses: string[];
    stats: {
        total: number;
        draft: number;
        confirmed: number;
        fulfilled: number;
    };
    filters: Filters & { status?: string };
}>();
const routes = orderRoutes(props.orderType);
const title = `${orderTitle(props.orderType)}s`;
const resource = orderResource(props.orderType);
const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? 'all');
const permissions = computed(() => usePage().props.auth.permissions);
const canUpdate = computed(() =>
    permissions.value.includes(`${resource}.update`),
);
const canDelete = computed(() =>
    permissions.value.includes(`${resource}.delete`),
);
const updateFilters = () =>
    router.get(
        routes.index.url(),
        {
            search: search.value || undefined,
            status: status.value === 'all' ? undefined : status.value,
        },
        { preserveScroll: true, preserveState: true, replace: true },
    );
watchDebounced(search, updateFilters, { debounce: 300 });
const clearFilters = () => {
    search.value = '';
    status.value = 'all';
    updateFilters();
};
const hasActiveFilters = computed(
    () => search.value !== '' || status.value !== 'all',
);
const orderToDelete = ref<OrderListItem | null>(null);

defineOptions({
    layout: (props: { orderType: OrderType }) => ({
        breadcrumbs: [
            {
                title: `${orderTitle(props.orderType)}s`,
                href: orderRoutes(props.orderType).index(),
            },
        ],
    }),
});
</script>

<template>
    <Head :title="title" />
    <div class="space-y-6">
        <div class="flex items-center justify-between gap-4">
            <Heading
                variant="small"
                :title="title"
                :description="
                    orderType === 'sale'
                        ? 'Confirm and fulfil customer orders'
                        : 'Plan and track supplier orders'
                "
            />
            <Button as-child>
                <Link :href="routes.create()">
                    <Plus class="size-4" /> New order</Link
                >
            </Button>
        </div>
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <StatCard
                label="Total"
                :value="stats.total"
                :icon="ClipboardList"
                color="#4f46e5"
            />
            <StatCard
                label="Draft"
                :value="stats.draft"
                :icon="FilePen"
                color="#64748b"
            />
            <StatCard
                label="Confirmed"
                :value="stats.confirmed"
                :icon="Send"
                color="#0ea5e9"
            />
            <StatCard
                label="Fulfilled"
                :value="stats.fulfilled"
                :icon="CheckCheck"
                color="#16a34a"
            />
        </div>
        <Card>
            <CardHeader class="border-b">
                <div class="flex flex-col gap-3 sm:flex-row">
                    <div class="relative w-full sm:max-w-sm">
                        <Search
                            class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                        />
                        <Input
                            v-model="search"
                            type="search"
                            :placeholder="`Search number or ${orderType === 'sale' ? 'customer' : 'supplier'}…`"
                            class="px-9"
                        />
                    </div>
                    <Select
                        v-model="status"
                        @update:model-value="updateFilters"
                    >
                        <SelectTrigger class="w-full sm:w-52">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All statuses</SelectItem>
                            <SelectItem
                                v-for="item in statuses"
                                :key="item"
                                :value="item"
                                class="capitalize"
                                >{{ label(item) }}</SelectItem
                            >
                        </SelectContent>
                    </Select>
                    <Button
                        v-if="hasActiveFilters"
                        variant="ghost"
                        size="sm"
                        @click="clearFilters"
                    >
                        <X class="size-4" /> Clear</Button
                    >
                </div>
            </CardHeader>
            <CardContent>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Number</TableHead>
                            <TableHead>{{
                                orderType === 'sale' ? 'Customer' : 'Supplier'
                            }}</TableHead>
                            <TableHead>Date</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead class="text-right">Total</TableHead>
                            <TableHead class="text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="order in orders.data" :key="order.id">
                            <TableCell class="font-semibold">
                                <Link
                                    :href="routes.show(order.id)"
                                    class="hover:underline"
                                    >{{ order.number }}</Link
                                >
                            </TableCell>
                            <TableCell>{{
                                orderType === 'sale'
                                    ? (order.customer?.name ?? '—')
                                    : (order.supplier?.name ?? '—')
                            }}</TableCell>
                            <TableCell>{{
                                formatDate(order.order_date)
                            }}</TableCell>
                            <TableCell>
                                <Badge
                                    :variant="variant(order.status)"
                                    class="capitalize"
                                    >{{ label(order.status) }}</Badge
                                >
                            </TableCell>
                            <TableCell class="text-right tabular-nums">{{
                                money(order.total)
                            }}</TableCell>
                            <TableCell class="space-x-1 text-right">
                                <Button variant="ghost" size="icon" as-child>
                                    <Link :href="routes.show(order.id)">
                                        <Eye class="size-4" />
                                        <span class="sr-only">View</span>
                                    </Link>
                                </Button>
                                <Button
                                    v-if="canUpdate"
                                    variant="ghost"
                                    size="icon"
                                    as-child
                                >
                                    <Link :href="routes.edit(order.id)">
                                        <Pencil class="size-4" />
                                        <span class="sr-only">Edit</span>
                                    </Link>
                                </Button>
                                <Button
                                    v-if="canDelete"
                                    variant="ghost"
                                    size="icon"
                                    @click="orderToDelete = order"
                                >
                                    <Trash2 class="size-4" />
                                    <span class="sr-only">Delete</span>
                                </Button>
                            </TableCell>
                        </TableRow>
                        <TableRow v-if="orders.data.length === 0">
                            <TableCell
                                :colspan="6"
                                class="py-10 text-center text-muted-foreground"
                                >No orders yet.</TableCell
                            >
                        </TableRow>
                    </TableBody>
                </Table>
            </CardContent>
        </Card>
        <TablePagination :links="orders.links" />
    </div>
    <ConfirmDeleteModal
        :open="Boolean(orderToDelete)"
        :title="`Delete ${orderType === 'sale' ? 'sales' : 'purchase'} order`"
        :description="`This will permanently delete “${orderToDelete?.number}”.`"
        :delete-url="
            orderToDelete ? routes.destroy.url(orderToDelete.id) : null
        "
        @update:open="
            (open) => {
                if (!open) orderToDelete = null;
            }
        "
    />
</template>
