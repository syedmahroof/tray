<script setup lang="ts">
import { Moon, Sun } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { useAppearance } from '@/composables/useAppearance';

const { resolvedAppearance, updateAppearance } = useAppearance();

const toggle = () => {
    updateAppearance(resolvedAppearance.value === 'dark' ? 'light' : 'dark');
};
</script>

<template>
    <Button
        variant="ghost"
        size="icon"
        class="size-9"
        :aria-label="
            resolvedAppearance === 'dark'
                ? 'Switch to light mode'
                : 'Switch to dark mode'
        "
        data-test="theme-toggle"
        @click="toggle"
    >
        <Sun v-if="resolvedAppearance === 'dark'" class="size-5" />
        <Moon v-else class="size-5" />
    </Button>
</template>
