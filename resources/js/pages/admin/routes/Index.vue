<script setup lang="ts">
import CategoryManager from '@/components/admin/CategoryManager.vue';
import { destroy, index, store, update } from '@/routes/routes';
import type { CategoryItem, Filters, Paginated } from '@/types';

defineProps<{
    routes: Paginated<CategoryItem>;
    filters: Filters;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Routes', href: index() }],
    },
});
</script>

<template>
    <CategoryManager
        title="Routes"
        description="Sales routes shared across every district"
        new-button-label="New route"
        :items="routes"
        :search-value="filters.search"
        :store-url="store().url"
        :update-url="(id: number) => update.url(id)"
        :destroy-url="(id: number) => destroy.url(id)"
    />
</template>
