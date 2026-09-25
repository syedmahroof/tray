<script setup lang="ts">
import { computed } from 'vue';
import { formatAnalytics } from '@/lib/analytics';
import type { AnalyticsDatum, AnalyticsFormat } from '@/types';

/**
 * Categories compared as horizontal bars, so long names stay readable on any
 * screen. Hovering a bar shows its exact figure and share.
 */
const props = defineProps<{
    data: AnalyticsDatum[];
    format: AnalyticsFormat;
}>();

const largest = computed(() =>
    Math.max(0, ...props.data.map((datum) => datum.value)),
);

const total = computed(() =>
    props.data.reduce((sum, datum) => sum + datum.value, 0),
);

const share = (value: number) =>
    total.value > 0
        ? `${Number(((value / total.value) * 100).toFixed(1))}%`
        : '0%';
</script>

<template>
    <ul class="space-y-3">
        <li
            v-for="datum in data"
            :key="datum.label"
            class="group"
            :title="`${datum.label}: ${formatAnalytics(datum.value, format)} (${share(datum.value)})`"
        >
            <div class="mb-1 flex items-baseline justify-between gap-3 text-sm">
                <span class="truncate">{{ datum.label }}</span>
                <span class="shrink-0 font-medium tabular-nums">
                    {{ formatAnalytics(datum.value, format) }}
                </span>
            </div>
            <div class="h-2 overflow-hidden rounded-full bg-muted">
                <div
                    class="h-full rounded-full transition-[width,opacity] duration-500 group-hover:opacity-80"
                    :style="{
                        width: `${largest > 0 ? Math.max(2, (datum.value / largest) * 100) : 0}%`,
                        backgroundColor: datum.color ?? '#4f46e5',
                    }"
                />
            </div>
        </li>
    </ul>
</template>
