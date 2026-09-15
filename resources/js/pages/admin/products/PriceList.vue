<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { Building2, Download, Package, Search, Tag, X } from '@lucide/vue';
import { watchDebounced } from '@vueuse/core';
import { computed, ref } from 'vue';
import BranchPriceTable from '@/components/admin/BranchPriceTable.vue';
import Heading from '@/components/Heading.vue';
import MultiCombobox from '@/components/MultiCombobox.vue';
import TablePagination from '@/components/TablePagination.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { priceList } from '@/routes/products';
import {
    exportBranches,
    exportMethod as exportPriceList,
} from '@/routes/products/price-list';
import type {
    Branch,
    NamedOption,
    Paginated,
    PriceMatrixRow,
    ProductFilters,
} from '@/types';

const props = defineProps<{
    products: Paginated<PriceMatrixRow>;
    branches: Branch[];
    productCategories: NamedOption[];
    brands: NamedOption[];
    units: string[];
    filters: ProductFilters;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Price List', href: priceList() }],
    },
});

const toStrings = (ids: number[] | null) => (ids ?? []).map(String);

const canEditPrices = computed(() =>
    usePage().props.auth.permissions.includes('products.price.update'),
);

const search = ref(props.filters.search ?? '');
const branchIds = ref<string[]>(toStrings(props.filters.branch_ids));
const categoryIds = ref<string[]>(
    toStrings(props.filters.product_category_ids),
);
const brandIds = ref<string[]>(toStrings(props.filters.brand_ids));
const unit = ref(props.filters.unit ?? 'all');

const asOptions = (items: NamedOption[]) =>
    items.map((item) => ({ value: String(item.id), label: item.name }));

const branchOptions = computed(() =>
    props.branches.map((branch) => ({
        value: String(branch.id),
        label: branch.name,
    })),
);

const updateFilters = () => {
    router.get(
        priceList().url,
        {
            search: search.value || undefined,
            branch_ids: branchIds.value.length ? branchIds.value : undefined,
            product_category_ids: categoryIds.value.length
                ? categoryIds.value
                : undefined,
            brand_ids: brandIds.value.length ? brandIds.value : undefined,
            unit: unit.value !== 'all' ? unit.value : undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

const hasActiveFilters = computed(
    () =>
        search.value !== '' ||
        branchIds.value.length > 0 ||
        categoryIds.value.length > 0 ||
        brandIds.value.length > 0 ||
        unit.value !== 'all',
);

const clearFilters = () => {
    search.value = '';
    branchIds.value = [];
    categoryIds.value = [];
    brandIds.value = [];
    unit.value = 'all';
    updateFilters();
};

watchDebounced(search, () => updateFilters(), { debounce: 300 });

const exportQuery = computed(() => ({
    search: search.value || undefined,
    branch_ids: branchIds.value.length ? branchIds.value : undefined,
    product_category_ids: categoryIds.value.length
        ? categoryIds.value
        : undefined,
    brand_ids: brandIds.value.length ? brandIds.value : undefined,
    unit: unit.value !== 'all' ? unit.value : undefined,
}));

const exportUrl = computed(() =>
    exportPriceList.url({ query: exportQuery.value }),
);

const exportBranchesUrl = computed(() =>
    exportBranches.url({ query: exportQuery.value }),
);
</script>

<template>
    <Head title="Price List" />

    <div class="flex flex-col space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <Heading
                variant="small"
                title="Price List"
                :description="
                    (branches.length > 1
                        ? `Comparing ${branches.length} branches side by side`
                        : 'Current rates as they appear in the price list workbook') +
                    (canEditPrices
                        ? ' · Click a figure to edit it; Enter saves and moves down, Esc cancels'
                        : '')
                "
            />

            <div class="flex items-center gap-2">
                <Button variant="outline" as-child>
                    <a :href="exportUrl"><Download /> Export by category</a>
                </Button>
                <Button variant="outline" as-child>
                    <a :href="exportBranchesUrl">
                        <Download /> Export all branches
                    </a>
                </Button>
            </div>
        </div>

        <Card>
            <CardHeader class="border-b">
                <div
                    class="flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-center"
                >
                    <div class="relative w-full max-w-sm">
                        <Search
                            class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                        />
                        <Input
                            v-model="search"
                            type="search"
                            placeholder="Search name, code or HSN…"
                            class="px-9"
                            data-test="search-input"
                        />
                    </div>

                    <div class="flex items-center gap-1.5">
                        <Building2 class="h-4 w-4 text-[#0891b2]" />
                        <MultiCombobox
                            v-model="branchIds"
                            class="w-[220px]"
                            placeholder="All branches"
                            :options="branchOptions"
                            data-test="branch-filter"
                            @update:model-value="updateFilters"
                        />
                    </div>

                    <div class="flex items-center gap-1.5">
                        <Package class="h-4 w-4 text-[#ca8a04]" />
                        <MultiCombobox
                            v-model="categoryIds"
                            class="w-[220px]"
                            placeholder="All categories"
                            :options="asOptions(productCategories)"
                            @update:model-value="updateFilters"
                        />
                    </div>

                    <div class="flex items-center gap-1.5">
                        <Tag class="h-4 w-4 text-[#e11d48]" />
                        <MultiCombobox
                            v-model="brandIds"
                            class="w-[200px]"
                            placeholder="All brands"
                            :options="asOptions(brands)"
                            @update:model-value="updateFilters"
                        />
                    </div>

                    <Select v-model="unit" @update:model-value="updateFilters">
                        <SelectTrigger class="w-[130px]">
                            <SelectValue placeholder="Unit" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All units</SelectItem>
                            <SelectItem
                                v-for="option in units"
                                :key="option"
                                :value="option"
                            >
                                {{ option }}
                            </SelectItem>
                        </SelectContent>
                    </Select>

                    <Button
                        v-if="hasActiveFilters"
                        variant="ghost"
                        size="sm"
                        class="text-muted-foreground"
                        data-test="clear-filters"
                        @click="clearFilters"
                    >
                        <X class="h-4 w-4" /> Clear
                    </Button>
                </div>
            </CardHeader>

            <CardContent class="px-0">
                <BranchPriceTable
                    :rows="products.data"
                    :branches="branches"
                    :start-index="products.from ?? 1"
                    group-by-category
                    :editable="canEditPrices"
                />
            </CardContent>
        </Card>

        <TablePagination :links="products.links" />
    </div>
</template>
