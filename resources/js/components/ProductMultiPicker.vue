<script setup lang="ts">
import { Package, X } from '@lucide/vue';
import { ref, watch } from 'vue';
import ProductCombobox from '@/components/ProductCombobox.vue';
import Thumbnail from '@/components/Thumbnail.vue';
import { Badge } from '@/components/ui/badge';
import { productDetailsLine } from '@/lib/products';
import type { ProductPickerOption } from '@/types';

/**
 * Picks several products by searching the server, so the form never has to
 * load the whole catalogue. The picked products are posted as `name[]`.
 */
const props = defineProps<{
    name: string;
    /** The products picked so far, e.g. the ones a record already has. */
    initial?: ProductPickerOption[];
    /** The address of one page of matches. */
    searchUrl: (query: { search: string; page: number }) => string;
    placeholder?: string;
}>();

const picked = ref<ProductPickerOption[]>([]);

watch(
    () => props.initial,
    (products) => {
        picked.value = [...(products ?? [])];
    },
    { immediate: true },
);

const add = (product: ProductPickerOption) => {
    if (!picked.value.some((existing) => existing.id === product.id)) {
        picked.value = [...picked.value, product];
    }
};

const remove = (product: ProductPickerOption) => {
    picked.value = picked.value.filter(
        (existing) => existing.id !== product.id,
    );
};
</script>

<template>
    <div class="space-y-2" data-test="product-multi-picker">
        <input
            v-for="product in picked"
            :key="product.id"
            type="hidden"
            :name="`${name}[]`"
            :value="product.id"
        />

        <ProductCombobox
            :search-url="searchUrl"
            :placeholder="placeholder ?? 'Add a product…'"
            content-class="w-[28rem] max-w-[90vw]"
            @select="add"
        />

        <div v-if="picked.length > 0" class="flex flex-wrap gap-1.5">
            <Badge
                v-for="product in picked"
                :key="product.id"
                variant="secondary"
                class="h-auto gap-1.5 py-1 pr-1 text-left whitespace-normal"
            >
                <Thumbnail
                    :fallback-icon="Package"
                    :src="product.image_url"
                    :name="product.name"
                    class="size-5 rounded"
                />
                <span>
                    {{ product.name }}
                    <span
                        v-if="productDetailsLine(product)"
                        class="text-muted-foreground"
                    >
                        · {{ productDetailsLine(product) }}
                    </span>
                </span>
                <button
                    type="button"
                    class="rounded-full p-0.5 hover:bg-muted-foreground/20"
                    :aria-label="`Remove ${product.name}`"
                    @click="remove(product)"
                >
                    <X class="h-3 w-3" />
                </button>
            </Badge>
        </div>
    </div>
</template>
