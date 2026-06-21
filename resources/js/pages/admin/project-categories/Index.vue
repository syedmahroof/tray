<script setup lang="ts">
import CategoryManager from '@/components/admin/CategoryManager.vue';
import { destroy, index, store, update } from '@/routes/project-categories';
import type { CategoryItem, Filters, Paginated } from '@/types';

defineProps<{
    projectCategories: Paginated<CategoryItem>;
    filters: Filters;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Project Categories', href: index() }],
    },
});
</script>

<template>
    <CategoryManager
        title="Project Categories"
        description="Categories used to classify projects"
        new-button-label="New project category"
        :items="projectCategories"
        :search-value="filters.search"
        :store-url="store().url"
        :update-url="(id: number) => update.url(id)"
        :destroy-url="(id: number) => destroy.url(id)"
    />
</template>
