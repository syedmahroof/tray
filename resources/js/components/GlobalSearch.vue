<script setup lang="ts">
import { onMounted, onUnmounted } from 'vue';
import { useGlobalSearch } from '@/composables/useGlobalSearch';
import { CommandDialog } from '@/components/ui/command';
import GlobalSearchContent from '@/components/GlobalSearchContent.vue';

const { isOpen, toggle } = useGlobalSearch();

// Keyboard listener for Cmd+K / Ctrl+K
const handleKeyDown = (e: KeyboardEvent) => {
    if (e.key === 'k' && (e.metaKey || e.ctrlKey)) {
        e.preventDefault();
        toggle();
    }
};

onMounted(() => {
    document.addEventListener('keydown', handleKeyDown);
});

onUnmounted(() => {
    document.removeEventListener('keydown', handleKeyDown);
});
</script>

<template>
    <CommandDialog v-slot="slotProps" v-model:open="isOpen">
        <GlobalSearchContent v-bind="slotProps" />
    </CommandDialog>
</template>
