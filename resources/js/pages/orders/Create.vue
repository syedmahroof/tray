<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import OrderForm from '@/components/OrderForm.vue';
import { orderRoutes, orderTitle } from '@/lib/orders';
import type {
    Branch,
    OrderPartyOption,
    OrderProductOption,
    OrderType,
} from '@/types';

const props = defineProps<{
    orderType: OrderType;
    customers: OrderPartyOption[];
    suppliers: OrderPartyOption[];
    prefill: { customer_id: number | null };
    products: OrderProductOption[];
    statuses: string[];
    branches: Branch[];
    defaultBranchId: number | null;
}>();

const routes = orderRoutes(props.orderType);
const title = orderTitle(props.orderType);

defineOptions({
    layout: (props: { orderType: OrderType }) => ({
        breadcrumbs: [
            {
                title: `${orderTitle(props.orderType)}s`,
                href: orderRoutes(props.orderType).index(),
            },
            {
                title: `New ${orderTitle(props.orderType)}`,
                href: orderRoutes(props.orderType).create(),
            },
        ],
    }),
});
</script>

<template>
    <Head :title="`New ${title}`" />
    <div class="space-y-6">
        <Link
            :href="routes.index()"
            class="inline-flex items-center gap-1 text-sm text-muted-foreground hover:underline"
        >
            <ArrowLeft class="size-4" /> Back to list</Link
        >
        <Heading
            variant="small"
            :title="`Create ${title}`"
            :description="`Add ${orderType === 'sale' ? 'customer' : 'supplier'} details and order line items`"
        />
        <OrderForm
            :order-type="orderType"
            :customers="customers"
            :suppliers="suppliers"
            :prefill="prefill"
            :products="products"
            :statuses="statuses"
            :branches="branches"
            :default-branch-id="defaultBranchId"
        />
    </div>
</template>
