<script setup lang="ts">
import {
    AlertTriangle,
    Building2,
    Calendar,
    ChartColumn,
    Check,
    ClipboardList,
    Clock,
    FileText,
    Hammer,
    HardHat,
    Inbox,
    Layers,
    MapPin,
    Minus,
    Package,
    Phone,
    Route,
    Scale,
    Tag,
    Target,
    TrendingDown,
    TrendingUp,
    Trophy,
    UserCheck,
    UserPlus,
    Users,
    Wallet,
    X,
} from '@lucide/vue';
import type { Component } from 'vue';
import { computed } from 'vue';
import { Card, CardContent } from '@/components/ui/card';
import { formatAnalytics } from '@/lib/analytics';
import type { AnalyticsStat } from '@/types';

const props = defineProps<{
    stat: AnalyticsStat;
    previousLabel: string;
}>();

const icons: Record<string, Component> = {
    alert: AlertTriangle,
    building: Building2,
    calendar: Calendar,
    chart: ChartColumn,
    check: Check,
    clipboard: ClipboardList,
    clock: Clock,
    file: FileText,
    hammer: Hammer,
    'hard-hat': HardHat,
    inbox: Inbox,
    layers: Layers,
    'map-pin': MapPin,
    package: Package,
    phone: Phone,
    route: Route,
    scale: Scale,
    tag: Tag,
    target: Target,
    trophy: Trophy,
    'user-check': UserCheck,
    'user-plus': UserPlus,
    users: Users,
    wallet: Wallet,
    x: X,
};

const icon = computed(() => icons[props.stat.icon] ?? ChartColumn);

/** The change on the period before, in points for percentages. */
const change = computed(() => {
    const { value, previous, format } = props.stat;

    if (previous === null) {
        return null;
    }

    if (format === 'percent') {
        return { amount: value - previous, unit: 'pts' };
    }

    if (previous === 0) {
        return value === 0 ? { amount: 0, unit: '%' } : null;
    }

    return {
        amount: ((value - previous) / Math.abs(previous)) * 100,
        unit: '%',
    };
});

const direction = computed(() => {
    if (!change.value || Math.abs(change.value.amount) < 0.05) {
        return 'flat';
    }

    return change.value.amount > 0 ? 'up' : 'down';
});
</script>

<template>
    <Card class="relative overflow-hidden py-0">
        <CardContent class="flex flex-col gap-3 p-4">
            <div class="flex items-center justify-between gap-2">
                <p class="truncate text-xs font-medium text-muted-foreground">
                    {{ stat.label }}
                </p>
                <span
                    class="flex size-8 shrink-0 items-center justify-center rounded-lg"
                    :style="{
                        backgroundColor: `color-mix(in srgb, ${stat.color} 15%, transparent)`,
                        color: stat.color,
                    }"
                >
                    <component :is="icon" class="size-4" />
                </span>
            </div>

            <p class="text-2xl leading-none font-semibold tabular-nums">
                {{ formatAnalytics(stat.value, stat.format) }}
            </p>

            <p
                v-if="stat.previous !== null"
                class="flex items-center gap-1 text-xs"
                :title="`${previousLabel}: ${formatAnalytics(stat.previous, stat.format)}`"
            >
                <span
                    v-if="change"
                    class="inline-flex items-center gap-0.5 font-medium tabular-nums"
                    :class="{
                        'text-green-600 dark:text-green-400':
                            direction === 'up',
                        'text-red-600 dark:text-red-400': direction === 'down',
                        'text-muted-foreground': direction === 'flat',
                    }"
                >
                    <TrendingUp v-if="direction === 'up'" class="size-3.5" />
                    <TrendingDown
                        v-else-if="direction === 'down'"
                        class="size-3.5"
                    />
                    <Minus v-else class="size-3.5" />
                    {{ change.amount > 0 ? '+' : ''
                    }}{{ Number(change.amount.toFixed(1)) }}{{ change.unit }}
                </span>
                <span v-else class="font-medium text-green-600">New</span>
                <span class="truncate text-muted-foreground">
                    vs {{ formatAnalytics(stat.previous, stat.format) }} before
                </span>
            </p>
            <p v-else class="text-xs text-muted-foreground">All time</p>
        </CardContent>
    </Card>
</template>
