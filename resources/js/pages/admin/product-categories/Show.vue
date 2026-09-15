<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Building2,
    IndianRupee,
    Package,
    Search,
    Tag,
    X,
} from '@lucide/vue';
import { watchDebounced } from '@vueuse/core';
import { computed, ref } from 'vue';
import BranchPriceTable from '@/components/admin/BranchPriceTable.vue';
import Heading from '@/components/Heading.vue';
import MultiCombobox from '@/components/MultiCombobox.vue';
import StatCard from '@/components/StatCard.vue';
import TablePagination from '@/components/TablePagination.vue';
import Thumbnail from '@/components/Thumbnail.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { index, show } from '@/routes/product-categories';
import type { Branch, CategoryItem, Paginated, PriceMatrixRow } from '@/types';

const props = defineProps<{
    productCategory: CategoryItem;
    products: Paginated<PriceMatrixRow>;
    branches: Branch[];
    stats: { products: number; brands: number; priced: number };
    filters: { search: string; branch_ids: number[] | null };
}>();

defineOptions({
    layout: (props: { productCategory: CategoryItem }) => ({
        breadcrumbs: [
            { title: 'Product Categories', href: index() },
            {
                title: props.productCategory.name,
                href: show(props.productCategory.id),
            },
        ],
    }),
});

const search = ref(props.filters.search ?? '');
const branchIds = ref<string[]>((props.filters.branch_ids ?? []).map(String));

const branchOptions = computed(() =>
    props.branches.map((branch) => ({
        value: String(branch.id),
        label: branch.name,
    })),
);

const updateFilters = () => {
    router.get(
        show(props.productCategory.id).url,
        {
            search: search.value || undefined,
            branch_ids: branchIds.value.length ? branchIds.value : undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

const hasActiveFilters = computed(
    () => search.value !== '' || branchIds.value.length > 0,
);

const clearFilters = () => {
    search.value = '';
    branchIds.value = [];
    updateFilters();
};

watchDebounced(search, () => updateFilters(), { debounce: 300 });
</script>

<template>
    <Head :title="productCategory.name" />

    <div class="flex flex-col space-y-6">
        <div>
            <Link
                :href="index()"
                class="mb-2 inline-flex items-center gap-1 text-sm text-muted-foreground hover:underline"
            >
                <ArrowLeft class="h-4 w-4 text-slate-500" /> Back to categories
            </Link>
            <div class="flex items-center gap-3">
                <Thumbnail
                    :src="productCategory.image_url"
                    :name="productCategory.name"
                    class="size-14"
                />
                <Heading
                    variant="small"
                    :title="productCategory.name"
                    description="Products filed under this category and their current rates"
                />
                <Badge
                    :variant="
                        productCategory.is_active ? 'secondary' : 'outline'
                    "
                >
                    {{ productCategory.is_active ? 'Active' : 'Inactive' }}
                </Badge>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <StatCard
                label="Products"
                :value="stats.products"
                :icon="Package"
                color="#059669"
            />
            <StatCard
                label="Brands"
                :value="stats.brands"
                :icon="Tag"
                color="#e11d48"
            />
            <StatCard
                label="With a price"
                :value="stats.priced"
                :icon="IndianRupee"
                color="#0891b2"
            />
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
                            placeholder="Search this category…"
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
                            @update:model-value="updateFilters"
                        />
                    </div>

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
                />
            </CardContent>
        </Card>

        <TablePagination :links="products.links" />
    </div>
</template>
