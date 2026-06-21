<script setup lang="ts">
import { VisDonut, VisSingleContainer } from '@unovis/vue';
import { ChartContainer } from '@/components/ui/chart';

type DonutDatum = { label: string; value: number; color: string };

withDefaults(
    defineProps<{
        data: DonutDatum[];
        height?: number;
    }>(),
    {
        height: 220,
    },
);

const value = (d: DonutDatum) => d.value;
const color = (d: DonutDatum) => d.color;
</script>

<template>
    <div class="flex flex-col items-center gap-4 sm:flex-row">
        <ChartContainer
            :config="{}"
            :style="{ height: `${height}px`, width: `${height}px` }"
            class="shrink-0"
        >
            <VisSingleContainer :data="data" :height="height">
                <VisDonut
                    :value="value"
                    :color="color"
                    :corner-radius="2"
                    :pad-angle="0.02"
                />
            </VisSingleContainer>
        </ChartContainer>

        <ul class="grid w-full gap-2 text-sm">
            <li
                v-for="item in data"
                :key="item.label"
                class="flex items-center justify-between gap-2"
            >
                <span class="flex items-center gap-2">
                    <span
                        class="size-2.5 shrink-0 rounded-full"
                        :style="{ backgroundColor: item.color }"
                    />
                    <span class="capitalize">{{ item.label }}</span>
                </span>
                <span class="font-medium tabular-nums">{{ item.value }}</span>
            </li>
        </ul>
    </div>
</template>
