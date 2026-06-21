<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowLeft, Package, Pencil, Tag, BadgeCheck } from '@lucide/vue';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { edit, index, show } from '@/routes/products';
import type { Product, NamedOption, Branch } from '@/types';

type ProductDetail = Product & {
    product_category: NamedOption;
    branch: Branch;
};

const props = defineProps<{
    product: ProductDetail;
}>();

defineOptions({
    layout: (props: { product: ProductDetail }) => ({
        breadcrumbs: [
            { title: 'Products', href: index() },
            { title: props.product.name, href: show(props.product.id) },
        ],
    }),
});

const permissions = computed(() => usePage().props.auth.permissions);
</script>

<template>
    <Head :title="product.name" />

    <div class="flex flex-col space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <Link
                    :href="index()"
                    class="mb-2 inline-flex items-center gap-1 text-sm text-muted-foreground hover:underline"
                >
                    <ArrowLeft class="h-4 w-4" /> Back to List
                </Link>
                <Heading
                    variant="small"
                    :title="product.name"
                    :description="`Category: ${product.product_category.name}`"
                />
            </div>

            <Button v-if="permissions.includes('products.update')" as-child>
                <Link :href="edit(product.id)"><Pencil /> Edit</Link>
            </Button>
        </div>

        <div class="grid gap-6 md:grid-cols-2">
            <!-- Details -->
            <div class="rounded-lg border p-4 space-y-4">
                <div class="flex items-center gap-2 border-b pb-2">
                    <Tag class="h-5 w-5 text-muted-foreground" />
                    <h3 class="font-semibold text-base">Product Details</h3>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-sm text-muted-foreground">Category</p>
                        <p class="text-sm font-medium">{{ product.product_category.name }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-muted-foreground">Branch</p>
                        <p class="text-sm font-medium">{{ product.branch.name }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-muted-foreground">Price</p>
                        <p class="text-sm font-medium">
                            {{ product.price ? `₹${parseFloat(product.price).toLocaleString()}` : '—' }}
                        </p>
                    </div>
                    <div>
                        <p class="text-sm text-muted-foreground">Area (Sqft)</p>
                        <p class="text-sm font-medium">
                            {{ product.area_sqft ? `${parseFloat(product.area_sqft).toLocaleString()} sqft` : '—' }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Description -->
            <div class="rounded-lg border p-4 space-y-4">
                <div class="flex items-center gap-2 border-b pb-2">
                    <Package class="h-5 w-5 text-muted-foreground" />
                    <h3 class="font-semibold text-base">Description</h3>
                </div>
                <p class="text-sm font-medium whitespace-pre-wrap">
                    {{ product.description ?? 'No description provided.' }}
                </p>
            </div>
        </div>
    </div>
</template>
