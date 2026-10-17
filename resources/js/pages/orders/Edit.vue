<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import OrderForm from '@/components/OrderForm.vue';
import { orderRoutes, orderTitle } from '@/lib/orders';
import type {
    Branch,
    OrderPartyOption,
    OrderDetail,
    OrderProductOption,
    OrderType,
} from '@/types';

const props = defineProps<{
    orderType: OrderType;
    order: OrderDetail;
    customers: OrderPartyOption[];
    suppliers: OrderPartyOption[];
    products: OrderProductOption[];
    statuses: string[];
    branches: Branch[];
    defaultBranchId: number | null;
}>();

const routes = orderRoutes(props.orderType);

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
            {
                title: 'Edit',
                href: orderRoutes(props.orderType).edit(props.order.id),
            },
        ],
    }),
});
</script>

<template>
    <Head :title="`Edit ${order.number}`" />
    <div class="space-y-6">
        <Link
            :href="routes.show(order.id)"
            class="inline-flex items-center gap-1 text-sm text-muted-foreground hover:underline"
        >
            <ArrowLeft class="size-4" /> Back</Link
        >
        <Heading
            variant="small"
            :title="`Edit ${order.number}`"
            description="Update order details and line items"
        />
        <OrderForm
            :order-type="orderType"
            :order="order"
            :customers="customers"
            :suppliers="suppliers"
            :products="products"
            :statuses="statuses"
            :branches="branches"
            :default-branch-id="defaultBranchId"
        />
    </div>
</template>
