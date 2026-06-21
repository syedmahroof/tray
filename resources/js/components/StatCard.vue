<script setup lang="ts">
import type { Component } from 'vue';
import { Card, CardContent } from '@/components/ui/card';

defineProps<{
    label: string;
    value: number;
    icon: Component;
    color: string;
}>();
</script>

<template>
    <Card>
        <CardContent class="flex items-center gap-4">
            <span
                class="flex size-10 shrink-0 items-center justify-center rounded-lg"
                :style="{
                    backgroundColor: `color-mix(in srgb, ${color} 15%, transparent)`,
                    color,
                }"
            >
                <component :is="icon" class="size-5" />
            </span>
            <div>
                <p class="text-2xl font-semibold tabular-nums">{{ value }}</p>
                <p class="text-sm text-muted-foreground">{{ label }}</p>
            </div>
        </CardContent>
    </Card>
</template>
