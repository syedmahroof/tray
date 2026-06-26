<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';

const brand = computed(() => usePage().props.brand);
</script>

<template>
    <div
        class="flex items-center justify-center"
        :class="brand?.logo ? 'h-8 w-auto' : 'aspect-square size-8'"
    >
        <img
            v-if="brand?.logo"
            :src="brand.logo"
            :alt="brand.name"
            class="h-8 w-auto max-w-[140px] object-contain"
        />
        <AppLogoIcon v-else class="size-8" />
    </div>
    <div class="ml-2 grid flex-1 text-left text-sm" v-if="!brand?.logo">
        <span
            class="mb-0.5 truncate leading-tight font-semibold text-neutral-900 dark:text-neutral-100"
            >{{ brand?.name ?? 'Build Tech' }}</span
        >
        <span
            v-if="brand?.subtitle"
            class="truncate text-xs text-muted-foreground"
            >{{ brand.subtitle }}</span
        >
    </div>
</template>
