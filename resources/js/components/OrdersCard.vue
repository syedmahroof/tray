<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ClipboardList, Plus } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    formatMoney,
    orderRoutes,
    orderStatusLabel,
    orderStatusVariant,
    orderTitle,
} from '@/lib/orders';
import { formatDate } from '@/lib/utils';
import type { OrderSummary, OrderType } from '@/types';

const props = defineProps<{
    orderType: OrderType;
    orders: OrderSummary[];
    /** Wayfinder URL to the create form, pre-filled for this profile. */
    createHref?: string;
    canCreate?: boolean;
}>();

const routes = orderRoutes(props.orderType);
</script>

<template>
    <Card>
        <CardHeader
            class="flex flex-row items-center justify-between border-b pb-3"
        >
            <CardTitle class="flex items-center gap-2 text-base font-semibold">
                <ClipboardList class="h-5 w-5 text-[#16a34a]" />
                {{ orderTitle(orderType) }}s
                <span class="text-sm font-normal text-muted-foreground"
                    >({{ orders.length }})</span
                >
            </CardTitle>
            <Button
                v-if="canCreate && createHref"
                variant="outline"
                size="sm"
                as-child
            >
                <Link :href="createHref"><Plus class="h-4 w-4" /> New</Link>
            </Button>
        </CardHeader>
        <CardContent class="pt-4">
            <Table v-if="orders.length">
                <TableHeader>
                    <TableRow>
                        <TableHead>Number</TableHead>
                        <TableHead>Date</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead class="text-right">Total</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="order in orders" :key="order.id">
                        <TableCell>
                            <Link
                                :href="routes.show(order.id)"
                                class="font-medium hover:underline"
                            >
                                {{ order.number }}
                            </Link>
                        </TableCell>
                        <TableCell>{{
                            formatDate(order.order_date)
                        }}</TableCell>
                        <TableCell>
                            <Badge
                                :variant="orderStatusVariant(order.status)"
                                class="capitalize"
                            >
                                {{ orderStatusLabel(order.status) }}
                            </Badge>
                        </TableCell>
                        <TableCell class="text-right tabular-nums">{{
                            formatMoney(order.total)
                        }}</TableCell>
                    </TableRow>
                </TableBody>
            </Table>
            <p v-else class="py-6 text-center text-sm text-muted-foreground">
                No orders yet.
            </p>
        </CardContent>
    </Card>
</template>
