<script setup lang="ts">
import { cn } from '@/lib/utils';

/** A quotation's status as a coloured pill, one colour per status. */
const props = defineProps<{
    status: string;
}>();

const STATUS_CLASSES: Record<string, string> = {
    draft: 'border-slate-200 bg-slate-100 text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300',
    sent: 'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-900 dark:bg-sky-950 dark:text-sky-300',
    accepted:
        'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-300',
    rejected:
        'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-900 dark:bg-rose-950 dark:text-rose-300',
    expired:
        'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-300',
    revised:
        'border-violet-200 bg-violet-50 text-violet-700 dark:border-violet-900 dark:bg-violet-950 dark:text-violet-300',
};

const DOT_CLASSES: Record<string, string> = {
    draft: 'bg-slate-400',
    sent: 'bg-sky-500',
    accepted: 'bg-emerald-500',
    rejected: 'bg-rose-500',
    expired: 'bg-amber-500',
    revised: 'bg-violet-500',
};
</script>

<template>
    <span
        :class="
            cn(
                'inline-flex w-fit shrink-0 items-center gap-1.5 rounded-full border px-2 py-0.5 text-xs font-medium whitespace-nowrap capitalize',
                STATUS_CLASSES[props.status] ?? STATUS_CLASSES.draft,
            )
        "
    >
        <span
            :class="
                cn(
                    'size-1.5 rounded-full',
                    DOT_CLASSES[props.status] ?? DOT_CLASSES.draft,
                )
            "
        />
        {{ props.status }}
    </span>
</template>
