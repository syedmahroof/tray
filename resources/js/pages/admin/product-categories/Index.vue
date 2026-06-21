<script setup lang="ts">
import CategoryManager from '@/components/admin/CategoryManager.vue';
import { destroy, index, store, update } from '@/routes/product-categories';
import type { CategoryItem, Filters, Paginated } from '@/types';

defineProps<{
    productCategories: Paginated<CategoryItem>;
    filters: Filters;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Product Categories', href: index() }],
    },
});
</script>

<template>
    <CategoryManager
        title="Product Categories"
        description="Categories used to classify products"
        new-button-label="New product category"
        :items="productCategories"
        :search-value="filters.search"
        :store-url="store().url"
        :update-url="(id: number) => update.url(id)"
        :destroy-url="(id: number) => destroy.url(id)"
    />
</template>
