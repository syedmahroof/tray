<script setup lang="ts">
import { Check, ChevronsUpDown, LoaderCircle } from '@lucide/vue';
import { nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { cn } from '@/lib/utils';
import { products as searchProducts } from '@/routes/quotations';
import type { QuotationProductOption } from '@/types';

/**
 * A product picker that searches the server as you type and loads further
 * pages as the list is scrolled, so the catalogue is never loaded at once.
 */
const props = defineProps<{
    /** The selected product's id. */
    modelValue?: string;
    /** The selected product's name, shown on the trigger. */
    selectedLabel?: string;
    placeholder?: string;
    contentClass?: string;
}>();

const emit = defineEmits<{
    select: [product: QuotationProductOption];
}>();

const open = ref(false);
const search = ref('');
const results = ref<QuotationProductOption[]>([]);
const nextPage = ref<number | null>(1);
const loading = ref(false);
const highlighted = ref(0);
const list = ref<HTMLElement | null>(null);
const searchInput = ref<InstanceType<typeof Input> | null>(null);

let controller: AbortController | null = null;
let searchTimeout: ReturnType<typeof setTimeout> | null = null;

/** Load the next page of matches, or the first one after a fresh search. */
const load = async (fresh = false) => {
    if (fresh) {
        nextPage.value = 1;
    }

    if (nextPage.value === null || (loading.value && !fresh)) {
        return;
    }

    controller?.abort();

    const current = new AbortController();

    controller = current;
    loading.value = true;

    try {
        const response = await fetch(
            searchProducts.url({
                query: { search: search.value.trim(), page: nextPage.value },
            }),
            {
                headers: { Accept: 'application/json' },
                signal: current.signal,
            },
        );

        if (!response.ok) {
            return;
        }

        const page = (await response.json()) as {
            data: QuotationProductOption[];
            next_page: number | null;
        };

        results.value = fresh ? page.data : [...results.value, ...page.data];
        nextPage.value = page.next_page;

        if (fresh) {
            highlighted.value = 0;
            list.value?.scrollTo({ top: 0 });
        }
    } catch (error) {
        if (!(error instanceof DOMException && error.name === 'AbortError')) {
            console.error(error);
        }
    } finally {
        // A newer request owns the flag once this one has been replaced.
        if (controller === current) {
            loading.value = false;
        }
    }
};

watch(search, () => {
    if (searchTimeout) {
        clearTimeout(searchTimeout);
    }

    searchTimeout = setTimeout(() => load(true), 250);
});

watch(open, async (isOpen) => {
    if (!isOpen) {
        return;
    }

    search.value = '';
    await load(true);
    await nextTick();
    (searchInput.value?.$el as HTMLInputElement | undefined)?.focus();
});

/** Fetch the next page as the list nears its end. */
const onScroll = () => {
    const element = list.value;

    if (
        element &&
        element.scrollTop + element.clientHeight >= element.scrollHeight - 48
    ) {
        load();
    }
};

const choose = (product: QuotationProductOption) => {
    emit('select', product);
    open.value = false;
};

const scrollHighlightedIntoView = () =>
    nextTick(() =>
        list.value
            ?.querySelector(`[data-index="${highlighted.value}"]`)
            ?.scrollIntoView({ block: 'nearest' }),
    );

const onKeydown = (event: KeyboardEvent) => {
    if (event.key === 'ArrowDown') {
        event.preventDefault();
        highlighted.value = Math.min(
            highlighted.value + 1,
            results.value.length - 1,
        );
        scrollHighlightedIntoView();

        if (highlighted.value >= results.value.length - 3) {
            load();
        }
    } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        highlighted.value = Math.max(highlighted.value - 1, 0);
        scrollHighlightedIntoView();
    } else if (event.key === 'Enter') {
        event.preventDefault();

        const product = results.value[highlighted.value];

        if (product) {
            choose(product);
        }
    }
};

onBeforeUnmount(() => {
    controller?.abort();

    if (searchTimeout) {
        clearTimeout(searchTimeout);
    }
});
</script>

<template>
    <Popover v-model:open="open">
        <PopoverTrigger as-child>
            <Button
                type="button"
                variant="outline"
                role="combobox"
                :aria-expanded="open"
                :title="props.selectedLabel"
                class="w-full justify-between font-normal"
            >
                <span
                    :class="
                        cn(
                            'truncate',
                            !props.selectedLabel && 'text-muted-foreground',
                        )
                    "
                >
                    {{ props.selectedLabel ?? placeholder ?? 'Select…' }}
                </span>
                <ChevronsUpDown class="ml-2 h-4 w-4 shrink-0 opacity-50" />
            </Button>
        </PopoverTrigger>
        <PopoverContent
            :class="cn('w-(--reka-popover-trigger-width) p-0', contentClass)"
            align="start"
        >
            <div class="border-b p-2">
                <Input
                    ref="searchInput"
                    v-model="search"
                    placeholder="Search products…"
                    class="h-8"
                    data-test="product-search"
                    @keydown="onKeydown"
                />
            </div>
            <div
                ref="list"
                role="listbox"
                class="max-h-72 overflow-y-auto p-1"
                @scroll="onScroll"
            >
                <button
                    v-for="(product, index) in results"
                    :key="product.id"
                    type="button"
                    role="option"
                    :data-index="index"
                    :aria-selected="String(product.id) === modelValue"
                    :class="
                        cn(
                            'flex w-full items-center rounded-sm px-2 py-1.5 text-left text-sm',
                            index === highlighted &&
                                'bg-accent text-accent-foreground',
                        )
                    "
                    @mouseenter="highlighted = index"
                    @click="choose(product)"
                >
                    <Check
                        :class="
                            cn(
                                'mr-2 h-4 w-4 shrink-0',
                                String(product.id) === modelValue
                                    ? 'opacity-100'
                                    : 'opacity-0',
                            )
                        "
                    />
                    <span class="truncate">{{ product.name }}</span>
                </button>
                <div
                    v-if="loading"
                    class="flex items-center justify-center gap-2 py-2 text-sm text-muted-foreground"
                >
                    <LoaderCircle class="h-4 w-4 animate-spin" /> Loading…
                </div>
                <p
                    v-else-if="results.length === 0"
                    class="py-6 text-center text-sm text-muted-foreground"
                >
                    No products found.
                </p>
            </div>
        </PopoverContent>
    </Popover>
</template>
