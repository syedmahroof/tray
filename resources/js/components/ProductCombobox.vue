<script setup lang="ts" generic="T extends ProductPickerOption">
import { Package, Check, ChevronsUpDown, LoaderCircle } from '@lucide/vue';
import { nextTick, onBeforeUnmount, ref, watch } from 'vue';
import type { Ref } from 'vue';
import Thumbnail from '@/components/Thumbnail.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { productDetailsLine } from '@/lib/products';
import { cn } from '@/lib/utils';
import { products as searchProducts } from '@/routes/quotations';
import type { ProductPickerOption } from '@/types';

/**
 * A product picker that searches the server as you type and loads further
 * pages as the list is scrolled, so the catalogue is never loaded at once.
 */
const props = defineProps<{
    /** The selected product's id. */
    modelValue?: string;
    /** The selected product, shown on the trigger with its brand, code and picture. */
    selected?: T;
    placeholder?: string;
    contentClass?: string;
    /**
     * The address of one page of matches. Defaults to the quotation picker's,
     * which carries each product's branch rates.
     */
    searchUrl?: (query: { search: string; page: number }) => string;
}>();

const emit = defineEmits<{
    select: [product: T];
}>();

const open = ref(false);
const search = ref('');
const results = ref<T[]>([]) as Ref<T[]>;
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
            (props.searchUrl ?? ((query) => searchProducts.url({ query })))({
                search: search.value.trim(),
                page: nextPage.value ?? 1,
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
            data: T[];
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

const choose = (product: T) => {
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
                :title="props.selected?.name"
                class="h-auto min-h-9 w-full justify-between py-1.5 text-left font-normal whitespace-normal"
            >
                <span
                    v-if="props.selected"
                    class="flex min-w-0 items-center gap-2"
                >
                    <Thumbnail
                        v-if="props.selected.image_url"
                        :src="props.selected.image_url"
                        :name="props.selected.name"
                        class="size-8"
                    />
                    <span class="min-w-0">
                        <span class="block leading-snug break-words">
                            {{ props.selected.name }}
                        </span>
                        <span
                            v-if="productDetailsLine(props.selected)"
                            class="block text-xs text-muted-foreground"
                        >
                            {{ productDetailsLine(props.selected) }}
                        </span>
                    </span>
                </span>
                <span v-else class="text-muted-foreground">
                    {{ placeholder ?? 'Select…' }}
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
                    placeholder="Search by name or code…"
                    class="h-8"
                    data-test="product-search"
                    @keydown="onKeydown"
                />
            </div>
            <div
                ref="list"
                role="listbox"
                class="max-h-96 overflow-y-auto p-1"
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
                            'flex w-full items-center gap-2 rounded-sm px-2 py-1.5 text-left text-sm',
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
                                'h-4 w-4 shrink-0',
                                String(product.id) === modelValue
                                    ? 'opacity-100'
                                    : 'opacity-0',
                            )
                        "
                    />
                    <Thumbnail
                        :fallback-icon="Package"
                        :src="product.image_url"
                        :name="product.name"
                    />
                    <span class="min-w-0 flex-1">
                        <span class="block leading-snug break-words">
                            {{ product.name }}
                        </span>
                        <span
                            v-if="productDetailsLine(product)"
                            :class="
                                cn(
                                    'block text-xs',
                                    index === highlighted
                                        ? 'text-accent-foreground/70'
                                        : 'text-muted-foreground',
                                )
                            "
                        >
                            {{ productDetailsLine(product) }}
                        </span>
                    </span>
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
