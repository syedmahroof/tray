<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import { computed, nextTick, ref } from 'vue';
import { money, percent } from '@/lib/products';
import type { BranchPriceCells } from '@/types';

/**
 * One figure in the price list grid, edited in place the way a spreadsheet
 * cell is: focus to edit, Enter or ↓ to save and move down, ↑ to move up,
 * Tab to move across, Esc to cancel. Each cell saves through its own HTTP
 * request, so quick edits never cancel one another.
 */
const props = defineProps<{
    value: string | null;
    kind: 'money' | 'percent';
    /** The figure this cell edits, e.g. `sr_rate`. */
    field: string;
    url: string;
    /** Other figures to save with this one so the row stays consistent. */
    companions?: (value: number | null) => Record<string, number | null>;
    /** Position in the grid, for moving between cells with the keyboard. */
    gridRow: number;
    gridCol: string;
}>();

const emit = defineEmits<{ saved: [cells: BranchPriceCells] }>();

const http = useHttp<Record<string, number | null>, BranchPriceCells>({});

const input = ref<HTMLInputElement | null>(null);
const editing = ref(false);
const draft = ref('');
const status = ref<'idle' | 'saved' | 'error'>('idle');
const errorMessage = ref<string | null>(null);

const shown = computed(() => {
    if (editing.value) {
        return draft.value;
    }

    return props.kind === 'percent' ? percent(props.value) : money(props.value);
});

/** Read a typed figure, tolerating grouping commas and symbols. */
const parse = (text: string): number | null | undefined => {
    const cleaned = text.replace(/[,%₹\s—]/g, '');

    if (cleaned === '') {
        return null;
    }

    const number = Number(cleaned);

    return Number.isFinite(number) ? number : undefined;
};

const startEditing = () => {
    editing.value = true;
    draft.value = props.value === null ? '' : String(Number(props.value));

    nextTick(() => input.value?.select());
};

const fail = (message: string) => {
    status.value = 'error';
    errorMessage.value = message;
};

const save = (next: number | null) => {
    status.value = 'idle';
    errorMessage.value = null;

    const payload = {
        ...(props.companions?.(next) ?? {}),
        [props.field]: next,
    };

    http.transform(() => payload)
        .patch(props.url, {
            onSuccess: (cells) => {
                emit('saved', cells);
                status.value = 'saved';
                setTimeout(() => {
                    if (status.value === 'saved') {
                        status.value = 'idle';
                    }
                }, 1200);
            },
            onError: (errors) =>
                fail(
                    Object.values(errors)[0] ??
                        'This figure could not be saved.',
                ),
            onHttpException: (response) => {
                fail(
                    response.status === 403
                        ? 'You cannot edit prices in this branch.'
                        : 'This figure could not be saved.',
                );

                return false;
            },
        })
        .catch(() => {
            // Failures are surfaced on the cell by the callbacks above.
        });
};

const commit = () => {
    if (!editing.value) {
        return;
    }

    editing.value = false;

    const next = parse(draft.value);
    const current = props.value === null ? null : Number(props.value);

    if (next === undefined) {
        fail('Enter a number.');

        return;
    }

    if (next !== current) {
        save(next);
    }
};

const cancel = () => {
    editing.value = false;
    input.value?.blur();
};

/** Focus the same column in another row; focusing away saves this cell. */
const move = (rowOffset: number): boolean => {
    const target = input.value
        ?.closest('table')
        ?.querySelector<HTMLInputElement>(
            `input[data-grid-col="${props.gridCol}"][data-grid-row="${props.gridRow + rowOffset}"]`,
        );

    target?.focus();

    return Boolean(target);
};

const onKeydown = (event: KeyboardEvent) => {
    if (event.key === 'Escape') {
        event.preventDefault();
        cancel();

        return;
    }

    if (event.key === 'Enter' || event.key === 'ArrowDown') {
        event.preventDefault();

        if (!move(event.key === 'Enter' && event.shiftKey ? -1 : 1)) {
            input.value?.blur();
        }

        return;
    }

    if (event.key === 'ArrowUp') {
        event.preventDefault();
        move(-1);
    }
};
</script>

<template>
    <input
        ref="input"
        type="text"
        inputmode="decimal"
        autocomplete="off"
        :value="shown"
        :data-grid-row="gridRow"
        :data-grid-col="gridCol"
        :aria-label="field"
        :title="errorMessage ?? undefined"
        :aria-invalid="status === 'error' || undefined"
        class="h-8 w-full min-w-[5.5rem] rounded-sm border border-transparent bg-transparent px-2 text-right tabular-nums transition-colors outline-none hover:border-border focus:border-primary focus:bg-background focus:ring-2 focus:ring-primary/20"
        :class="{
            'opacity-60': http.processing,
            'bg-emerald-50 dark:bg-emerald-950/40': status === 'saved',
            'border-destructive text-destructive': status === 'error',
        }"
        @focus="startEditing"
        @input="draft = ($event.target as HTMLInputElement).value"
        @blur="commit"
        @keydown="onKeydown"
    />
</template>
