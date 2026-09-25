<script setup lang="ts">
import { GroupedBar } from '@unovis/ts';
import {
    VisAxis,
    VisGroupedBar,
    VisTooltip,
    VisXYContainer,
} from '@unovis/vue';
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

const escapeHtml = (value: string) =>
    value.replace(
        /[&<>"']/g,
        (character) =>
            ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;',
            })[character] ?? character,
    );

/** Names the bar under the pointer and the count it stands for. */
const tooltipTriggers = {
    [GroupedBar.selectors.bar]: (d: BarDatum) =>
        `<div style="display:flex;align-items:center;gap:8px;font-size:12px;">
            <span style="width:10px;height:10px;border-radius:2px;background:${props.color};"></span>
            <span>${escapeHtml(d.label)}</span>
            <strong style="margin-left:8px;font-variant-numeric:tabular-nums;">${d.value.toLocaleString()}</strong>
        </div>`,
};
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
            <VisTooltip :triggers="tooltipTriggers" />
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
