<script setup lang="ts">
import { Monitor, Moon, Sun } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useAppearance } from '@/composables/useAppearance';

const { appearance, resolvedAppearance, updateAppearance } = useAppearance();

const options = [
    { value: 'light', label: 'Light', icon: Sun },
    { value: 'dark', label: 'Dark', icon: Moon },
    { value: 'system', label: 'System', icon: Monitor },
] as const;
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button
                variant="ghost"
                size="icon"
                class="size-9"
                aria-label="Toggle theme"
                data-test="theme-toggle"
            >
                <Sun v-if="resolvedAppearance === 'light'" class="size-5" />
                <Moon v-else class="size-5" />
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="w-40">
            <DropdownMenuItem
                v-for="option in options"
                :key="option.value"
                :class="{ 'bg-accent': appearance === option.value }"
                @click="updateAppearance(option.value)"
            >
                <component :is="option.icon" class="mr-2 h-4 w-4" />
                {{ option.label }}
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
