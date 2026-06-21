<script setup lang="ts">
import CategoryManager from '@/components/admin/CategoryManager.vue';
import { destroy, index, store, update } from '@/routes/contact-types';
import type { CategoryItem, Filters, Paginated } from '@/types';

defineProps<{
    contactTypes: Paginated<CategoryItem>;
    filters: Filters;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Contact Types', href: index() }],
    },
});
</script>

<template>
    <CategoryManager
        title="Contact Types"
        description="Types used to classify contacts"
        new-button-label="New contact type"
        :items="contactTypes"
        :search-value="filters.search"
        :store-url="store().url"
        :update-url="(id: number) => update.url(id)"
        :destroy-url="(id: number) => destroy.url(id)"
    />
</template>
