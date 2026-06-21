<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Search, X } from '@lucide/vue';
import { watchDebounced } from '@vueuse/core';
import { ref } from 'vue';
import { Input } from '@/components/ui/input';

const props = withDefaults(
    defineProps<{
        modelValue?: string;
        placeholder?: string;
    }>(),
    {
        modelValue: '',
        placeholder: 'Search…',
    },
);

const search = ref(props.modelValue);

const visit = (value: string): void => {
    router.get(
        window.location.pathname,
        { search: value || undefined },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
};

watchDebounced(search, (value) => visit(value.trim()), { debounce: 300 });

const clear = (): void => {
    search.value = '';
};
</script>

<template>
    <div class="relative w-full max-w-sm">
        <Search
            class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
        />
        <Input
            v-model="search"
            type="search"
            :placeholder="placeholder"
            class="px-9"
            data-test="search-input"
        />
        <button
            v-if="search"
            type="button"
            aria-label="Clear search"
            class="absolute top-1/2 right-3 -translate-y-1/2 text-muted-foreground hover:text-foreground"
            @click="clear"
        >
            <X class="size-4" />
        </button>
    </div>
</template>
