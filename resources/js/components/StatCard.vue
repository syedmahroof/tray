<script setup lang="ts">
import type { Component } from 'vue';
import { Card, CardContent } from '@/components/ui/card';

defineProps<{
    label: string;
    value: number | string;
    icon: Component;
    color: string;
}>();
</script>

<template>
    <Card class="relative overflow-hidden py-0">
        <!-- Light background infographic -->
        <component
            :is="icon"
            class="pointer-events-none absolute -right-3 -bottom-3 size-20 opacity-[0.08]"
            :style="{ color }"
            aria-hidden="true"
        />

        <CardContent class="flex items-center gap-3 p-4">
            <span
                class="flex size-9 shrink-0 items-center justify-center rounded-lg"
                :style="{
                    backgroundColor: `color-mix(in srgb, ${color} 15%, transparent)`,
                    color,
                }"
            >
                <component :is="icon" class="size-5" />
            </span>
            <div class="min-w-0">
                <p class="text-xl leading-none font-semibold tabular-nums">
                    {{ value }}
                </p>
                <p class="mt-1 truncate text-xs text-muted-foreground">
                    {{ label }}
                </p>
            </div>
        </CardContent>
    </Card>
</template>
