<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Check,
    ChevronDown,
    Download,
    Pencil,
    Printer,
} from '@lucide/vue';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
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
import type { OrderDetail, OrderType } from '@/types';

const props = defineProps<{
    orderType: OrderType;
    order: OrderDetail;
    statuses: string[];
}>();
const routes = orderRoutes(props.orderType);
const resource = orderResource(props.orderType);
const permissions = computed(() => usePage().props.auth.permissions);
const canUpdate = computed(() =>
    permissions.value.includes(`${resource}.update`),
);
const partyName = computed(() =>
    props.orderType === 'sale'
        ? props.order.customer?.name
        : props.order.supplier?.name,
);
const gstin = computed(
    () =>
        props.order.gstin ||
        (props.orderType === 'sale'
            ? props.order.customer?.gst_number
            : props.order.supplier?.gst_number),
);
const changeStatus = (status: string) => {
    if (status !== props.order.status) {
        router.patch(
            routes.status.url(props.order.id),
            { status },
            { preserveScroll: true },
        );
    }
};

defineOptions({
    layout: (props: { orderType: OrderType; order: OrderDetail }) => ({
        breadcrumbs: [
            {
                title: `${orderTitle(props.orderType)}s`,
                href: orderRoutes(props.orderType).index(),
            },
            {
                title: props.order.number,
                href: orderRoutes(props.orderType).show(props.order.id),
            },
        ],
    }),
});
</script>

<template>
    <Head :title="order.number" />
    <div class="space-y-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <Link
                    :href="routes.index()"
                    class="mb-2 inline-flex items-center gap-1 text-sm text-muted-foreground hover:underline"
                >
                    <ArrowLeft class="size-4" /> Back to list</Link
                >
                <Heading
                    variant="small"
                    :title="order.number"
                    :description="`Created ${formatDate(order.created_at)}`"
                />
            </div>
            <div class="flex flex-wrap justify-end gap-2">
                <DropdownMenu v-if="canUpdate">
                    <DropdownMenuTrigger as-child>
                        <Button variant="outline">
                            <Badge
                                :variant="variant(order.status)"
                                class="capitalize"
                                >{{ label(order.status) }}</Badge
                            >
                            <ChevronDown class="size-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuLabel>Change status</DropdownMenuLabel>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            v-for="status in statuses"
                            :key="status"
                            class="capitalize"
                            @select="changeStatus(status)"
                        >
                            <Check
                                class="size-4"
                                :class="
                                    status === order.status
                                        ? 'opacity-100'
                                        : 'opacity-0'
                                "
                            />{{ label(status) }}</DropdownMenuItem
                        >
                    </DropdownMenuContent>
                </DropdownMenu>
                <Button variant="outline" as-child>
                    <a
                        :href="routes.print.url(order.id)"
                        target="_blank"
                        rel="noopener"
                    >
                        <Printer class="size-4" /> Print
                    </a>
                </Button>
                <Button variant="outline" as-child>
                    <a :href="routes.pdf.url(order.id)">
                        <Download class="size-4" /> PDF
                    </a>
                </Button>
                <Button v-if="canUpdate" as-child>
                    <Link :href="routes.edit(order.id)">
                        <Pencil class="size-4" /> Edit</Link
                    >
                </Button>
            </div>
        </div>
        <div class="grid gap-6 lg:grid-cols-3">
            <Card class="lg:col-span-2">
                <CardHeader>
                    <CardTitle>Items</CardTitle>
                </CardHeader>
                <CardContent class="overflow-x-auto px-0">
                    <Table class="min-w-[42rem]">
                        <TableHeader>
                            <TableRow>
                                <TableHead class="pl-6">Description</TableHead>
                                <TableHead>HSN</TableHead>
                                <TableHead class="text-right">Qty</TableHead>
                                <TableHead class="text-right"
                                    >Unit price</TableHead
                                >
                                <TableHead class="text-right">GST</TableHead>
                                <TableHead class="pr-6 text-right"
                                    >Amount</TableHead
                                >
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow
                                v-for="item in order.items"
                                :key="item.id"
                            >
                                <TableCell class="pl-6">
                                    <div class="font-medium">
                                        {{ item.description }}
                                    </div>
                                    <div
                                        v-if="item.product"
                                        class="text-xs text-muted-foreground"
                                    >
                                        {{ item.product.name }}
                                    </div>
                                </TableCell>
                                <TableCell>{{
                                    item.hsn_code ?? '—'
                                }}</TableCell>
                                <TableCell class="text-right tabular-nums">{{
                                    item.quantity
                                }}</TableCell>
                                <TableCell class="text-right tabular-nums">{{
                                    money(item.unit_price)
                                }}</TableCell>
                                <TableCell class="text-right tabular-nums"
                                    >{{ item.tax_percentage }}%</TableCell
                                >
                                <TableCell
                                    class="pr-6 text-right tabular-nums"
                                    >{{
                                        money(
                                            Number(item.quantity) *
                                                Number(item.unit_price) +
                                                Number(item.tax_amount),
                                        )
                                    }}</TableCell
                                >
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>
            <div class="space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle>{{
                            orderType === 'sale' ? 'Customer' : 'Supplier'
                        }}</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-1 text-sm">
                        <p class="font-medium">{{ partyName ?? '—' }}</p>
                        <p
                            v-if="order.supplier?.contact_person"
                            class="text-muted-foreground"
                        >
                            {{ order.supplier.contact_person }}
                        </p>
                        <p
                            v-if="order.supplier?.phone"
                            class="text-muted-foreground"
                        >
                            {{ order.supplier.phone }}
                        </p>
                        <p
                            v-if="order.supplier?.email"
                            class="text-muted-foreground"
                        >
                            {{ order.supplier.email }}
                        </p>
                        <p v-if="gstin" class="text-muted-foreground">
                            GSTIN: {{ gstin }}
                        </p>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle>Summary</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-3 text-sm">
                        <div class="flex justify-between">
                            <span>Order date</span>
                            <span>{{ formatDate(order.order_date) }}</span>
                        </div>
                        <div
                            v-if="order.expected_date"
                            class="flex justify-between"
                        >
                            <span>Expected</span>
                            <span>{{ formatDate(order.expected_date) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Subtotal</span>
                            <span>{{ money(order.subtotal) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Discount</span>
                            <span>{{ money(order.discount) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>GST</span>
                            <span>{{ money(order.tax_amount) }}</span>
                        </div>
                        <div
                            class="flex justify-between border-t pt-3 text-base font-semibold"
                        >
                            <span>Total</span>
                            <span>{{ money(order.total) }}</span>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
        <Card v-if="order.notes || order.terms">
            <CardHeader>
                <CardTitle>Notes and terms</CardTitle>
            </CardHeader>
            <CardContent class="grid gap-6 md:grid-cols-2">
                <div v-if="order.notes">
                    <h3 class="mb-1 text-sm font-medium">Notes</h3>
                    <p
                        class="text-sm whitespace-pre-line text-muted-foreground"
                    >
                        {{ order.notes }}
                    </p>
                </div>
                <div v-if="order.terms">
                    <h3 class="mb-1 text-sm font-medium">Terms</h3>
                    <p
                        class="text-sm whitespace-pre-line text-muted-foreground"
                    >
                        {{ order.terms }}
                    </p>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
