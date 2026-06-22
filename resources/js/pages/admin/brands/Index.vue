<script setup lang="ts">
import CategoryManager from '@/components/admin/CategoryManager.vue';
import { destroy, index, store, update } from '@/routes/brands';
import type { CategoryItem, Filters, Paginated } from '@/types';

defineProps<{
    brands: Paginated<CategoryItem>;
    filters: Filters;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Brands', href: index() }],
    },
});
</script>

<template>
    <CategoryManager
        title="Brands"
        description="Brands used to classify products"
        new-button-label="New brand"
        :items="brands"
        :search-value="filters.search"
        :store-url="store().url"
        :update-url="(id: number) => update.url(id)"
        :destroy-url="(id: number) => destroy.url(id)"
    />
</template>
