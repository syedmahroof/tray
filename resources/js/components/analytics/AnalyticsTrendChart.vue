<script setup lang="ts">
import {
    VisArea,
    VisAxis,
    VisCrosshair,
    VisLine,
    VisTooltip,
    VisXYContainer,
} from '@unovis/vue';
import { ChartContainer } from '@/components/ui/chart';
import { compactMoney, formatAnalytics } from '@/lib/analytics';
import type { AnalyticsDatum, AnalyticsFormat } from '@/types';

/**
 * A value over the period, with a crosshair that reads out the bucket under
 * the pointer.
 */
const props = withDefaults(
    defineProps<{
        data: AnalyticsDatum[];
        format: AnalyticsFormat;
        color?: string;
        height?: number;
    }>(),
    { color: '#4f46e5', height: 240 },
);

const x = (_: AnalyticsDatum, index: number) => index;
const y = (datum: AnalyticsDatum) => datum.value;

const xTick = (tick: number) => props.data[Math.round(tick)]?.label ?? '';
const yTick = (tick: number) =>
    props.format === 'money' ? compactMoney(tick) : String(tick);

const escapeHtml = (value: string) =>
    value.replace(/[&<>"']/g, (character) => `&#${character.charCodeAt(0)};`);

const tooltip = (datum: AnalyticsDatum) =>
    `<div style="font-size:12px;"><div style="opacity:.7;">${escapeHtml(datum.label)}</div><strong style="font-variant-numeric:tabular-nums;">${formatAnalytics(datum.value, props.format)}</strong></div>`;
</script>

<template>
    <ChartContainer
        :config="{}"
        :style="{ height: `${height}px` }"
        class="w-full"
    >
        <VisXYContainer :data="data" :height="height" :padding="{ top: 8 }">
            <VisArea :x="x" :y="y" :color="color" :opacity="0.15" />
            <VisLine :x="x" :y="y" :color="color" :line-width="2" />
            <VisAxis
                type="x"
                :x="x"
                :tick-format="xTick"
                :num-ticks="Math.min(data.length, 6)"
                :tick-line="false"
                :domain-line="false"
                :grid-line="false"
            />
            <VisAxis
                type="y"
                :tick-format="yTick"
                :num-ticks="4"
                :tick-line="false"
                :domain-line="false"
                :grid-line="true"
            />
            <VisCrosshair :template="tooltip" :color="color" />
            <VisTooltip />
        </VisXYContainer>
    </ChartContainer>
</template>
