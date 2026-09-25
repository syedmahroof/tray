<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { CalendarRange } from '@lucide/vue';
import { ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { AnalyticsPeriod } from '@/types';

/**
 * Picks the period an analytics page reports on: a preset reloads straight
 * away, a custom range once both dates are set and applied.
 */
const props = defineProps<{
    period: AnalyticsPeriod;
    periods: { value: string; label: string }[];
}>();

const selected = ref(props.period.key);
const from = ref(props.period.from);
const to = ref(props.period.to);

watch(
    () => props.period,
    (period) => {
        selected.value = period.key;
        from.value = period.from;
        to.value = period.to;
    },
);

const reload = (query: Record<string, string>) => {
    router.get(window.location.pathname, query, {
        preserveScroll: true,
        preserveState: true,
    });
};

const choose = (value: unknown) => {
    selected.value = String(value);

    if (selected.value !== 'custom') {
        reload({ period: selected.value });
    }
};

const applyCustom = () => {
    if (from.value && to.value && from.value <= to.value) {
        reload({ period: 'custom', from: from.value, to: to.value });
    }
};
</script>

<template>
    <div class="flex flex-wrap items-center gap-2">
        <Select :model-value="selected" @update:model-value="choose">
            <SelectTrigger class="w-full sm:w-44" data-test="period-select">
                <CalendarRange class="h-4 w-4 text-muted-foreground" />
                <SelectValue placeholder="Period" />
            </SelectTrigger>
            <SelectContent>
                <SelectItem
                    v-for="option in periods"
                    :key="option.value"
                    :value="option.value"
                >
                    {{ option.label }}
                </SelectItem>
            </SelectContent>
        </Select>

        <template v-if="selected === 'custom'">
            <Input
                v-model="from"
                type="date"
                class="w-[calc(50%-0.25rem)] sm:w-40"
                :max="to || undefined"
                aria-label="From date"
                data-test="period-from"
            />
            <Input
                v-model="to"
                type="date"
                class="w-[calc(50%-0.25rem)] sm:w-40"
                :min="from || undefined"
                aria-label="To date"
                data-test="period-to"
            />
            <Button
                class="w-full sm:w-auto"
                :disabled="!from || !to || from > to"
                data-test="period-apply"
                @click="applyCustom"
            >
                Apply
            </Button>
        </template>
    </div>
</template>
