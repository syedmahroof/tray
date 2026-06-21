<script setup lang="ts">
import { VisAxis, VisGroupedBar, VisXYContainer } from '@unovis/vue';
import { ChartContainer } from '@/components/ui/chart';

type BarDatum = { label: string; value: number };

const props = withDefaults(
    defineProps<{
        data: BarDatum[];
        color?: string;
        height?: number;
    }>(),
    {
        color: '#2563eb',
        height: 220,
    },
);

const x = (_: BarDatum, i: number) => i;
const y = (d: BarDatum) => d.value;
const tickFormat = (tick: number) => props.data[tick]?.label ?? '';
</script>

<template>
    <ChartContainer
        :config="{}"
        :style="{ height: `${height}px` }"
        class="w-full"
    >
        <VisXYContainer :data="data" :height="height">
            <VisGroupedBar
                :x="x"
                :y="[y]"
                :color="[color]"
                :rounded-corners="4"
                :bar-padding="0.3"
            />
            <VisAxis
                type="x"
                :x="x"
                :tick-format="tickFormat"
                :tick-line="false"
                :domain-line="false"
                :grid-line="false"
            />
            <VisAxis
                type="y"
                :tick-format="() => ''"
                :tick-line="false"
                :domain-line="false"
                :grid-line="true"
                :num-ticks="4"
            />
        </VisXYContainer>
    </ChartContainer>
</template>
