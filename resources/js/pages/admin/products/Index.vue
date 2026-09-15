<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    CalendarDays,
    Download,
    Eye,
    Package,
    Pencil,
    Plus,
    Search,
    Table2,
    Tag,
    Trash2,
    User,
    X,
} from '@lucide/vue';
import { watchDebounced } from '@vueuse/core';
import { computed, ref } from 'vue';
import ConfirmDeleteModal from '@/components/ConfirmDeleteModal.vue';
import Heading from '@/components/Heading.vue';
import MultiCombobox from '@/components/MultiCombobox.vue';
import TablePagination from '@/components/TablePagination.vue';
import Thumbnail from '@/components/Thumbnail.vue';
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
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDate } from '@/lib/utils';
import { show as showCategory } from '@/routes/product-categories';
import {
    create,
    destroy,
    edit,
    exportMethod,
    index,
    priceList,
    show,
} from '@/routes/products';
import type {
    NamedOption,
    Paginated,
    ProductFilters,
    ProductListItem,
} from '@/types';

const props = defineProps<{
    products: Paginated<ProductListItem>;
    users: NamedOption[];
    productCategories: NamedOption[];
    brands: NamedOption[];
    units: string[];
    filters: ProductFilters;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Products', href: index() }],
    },
});

const toStrings = (ids: number[] | null | undefined) => (ids ?? []).map(String);

const search = ref(props.filters.search ?? '');
const categoryIds = ref<string[]>(
    toStrings(props.filters.product_category_ids),
);
const brandIds = ref<string[]>(toStrings(props.filters.brand_ids));
const unit = ref(props.filters.unit ?? 'all');
const createdBy = ref(
    props.filters.created_by ? String(props.filters.created_by) : 'all',
);
const createdFrom = ref(props.filters.created_from ?? '');
const createdTo = ref(props.filters.created_to ?? '');

const asOptions = (items: NamedOption[]) =>
    items.map((item) => ({ value: String(item.id), label: item.name }));

const query = computed(() => ({
    search: search.value || undefined,
    product_category_ids: categoryIds.value.length
        ? categoryIds.value
        : undefined,
    brand_ids: brandIds.value.length ? brandIds.value : undefined,
    unit: unit.value !== 'all' ? unit.value : undefined,
    created_by: createdBy.value !== 'all' ? createdBy.value : undefined,
    created_from: createdFrom.value || undefined,
    created_to: createdTo.value || undefined,
}));

const updateFilters = () => {
    router.get(index().url, query.value, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const hasActiveFilters = computed(
    () =>
        search.value !== '' ||
        categoryIds.value.length > 0 ||
        brandIds.value.length > 0 ||
        unit.value !== 'all' ||
        createdBy.value !== 'all' ||
        createdFrom.value !== '' ||
        createdTo.value !== '',
);

const clearFilters = () => {
    search.value = '';
    categoryIds.value = [];
    brandIds.value = [];
    unit.value = 'all';
    createdBy.value = 'all';
    createdFrom.value = '';
    createdTo.value = '';
    updateFilters();
};

watchDebounced(search, () => updateFilters(), { debounce: 300 });

const exportUrl = computed(() => exportMethod.url({ query: query.value }));

const deleteDialogOpen = ref(false);
const productToDelete = ref<ProductListItem | null>(null);

const confirmDelete = (product: ProductListItem) => {
    productToDelete.value = product;
    deleteDialogOpen.value = true;
};
</script>

<template>
    <Head title="Products" />

    <div class="flex flex-col space-y-6">
        <div class="flex items-center justify-between">
            <Heading
                variant="small"
                title="Products"
                description="Manage units and offerings within each project"
            />

            <div class="flex items-center gap-2">
                <Button variant="outline" as-child>
                    <Link :href="priceList()"><Table2 /> Price list</Link>
                </Button>
                <Button variant="outline" as-child>
                    <a :href="exportUrl"><Download /> Export</a>
                </Button>
                <Button as-child>
                    <Link :href="create()"><Plus /> New product</Link>
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
                            placeholder="Search products…"
                            class="px-9"
                            data-test="search-input"
                        />
                    </div>

                    <div class="flex items-center gap-1.5">
                        <Package class="h-4 w-4 text-[#ca8a04]" />
                        <MultiCombobox
                            v-model="categoryIds"
                            class="w-[200px]"
                            placeholder="All categories"
                            data-test="category-filter"
                            :options="asOptions(productCategories)"
                            @update:model-value="updateFilters"
                        />
                    </div>

                    <div class="flex items-center gap-1.5">
                        <Tag class="h-4 w-4 text-[#e11d48]" />
                        <MultiCombobox
                            v-model="brandIds"
                            class="w-[180px]"
                            placeholder="All brands"
                            :options="asOptions(brands)"
                            @update:model-value="updateFilters"
                        />
                    </div>

                    <Select v-model="unit" @update:model-value="updateFilters">
                        <SelectTrigger class="w-[120px]">
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

                    <!-- Created By Filter -->
                    <Select
                        v-model="createdBy"
                        @update:model-value="updateFilters"
                    >
                        <SelectTrigger
                            class="flex w-full items-center gap-1.5 sm:w-[180px]"
                        >
                            <User class="h-4 w-4 text-[#0ea5e9]" />
                            <SelectValue placeholder="Created by" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Creators</SelectItem>
                            <SelectItem
                                v-for="user in users"
                                :key="user.id"
                                :value="String(user.id)"
                            >
                                {{ user.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>

                    <!-- Created Date Range -->
                    <div class="flex items-center gap-1.5">
                        <CalendarDays class="h-4 w-4 text-[#16a34a]" />
                        <Input
                            v-model="createdFrom"
                            type="date"
                            class="w-[150px]"
                            aria-label="Created from"
                            @change="updateFilters"
                        />
                        <span class="text-sm text-muted-foreground">to</span>
                        <Input
                            v-model="createdTo"
                            type="date"
                            class="w-[150px]"
                            aria-label="Created to"
                            @change="updateFilters"
                        />
                    </div>

                    <!-- Clear Filters -->
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
            <CardContent>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead class="w-12">S.No.</TableHead>
                            <TableHead class="w-32">Code</TableHead>
                            <TableHead>Name</TableHead>
                            <TableHead>Category</TableHead>
                            <TableHead>Brand</TableHead>
                            <TableHead class="w-16">Unit</TableHead>
                            <TableHead class="text-right">Priced in</TableHead>
                            <TableHead>Created</TableHead>
                            <TableHead class="text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="(product, index) in products.data"
                            :key="product.id"
                        >
                            <TableCell
                                class="font-medium text-muted-foreground"
                            >
                                {{ (products.from ?? 1) + index }}
                            </TableCell>
                            <TableCell
                                class="font-mono text-xs text-muted-foreground"
                            >
                                {{ product.code ?? '—' }}
                            </TableCell>
                            <TableCell class="font-medium">
                                <div class="flex items-center gap-3">
                                    <Thumbnail
                                        :src="product.image_url"
                                        :name="product.name"
                                    />
                                    <Link
                                        :href="show(product.id)"
                                        class="font-semibold text-foreground hover:underline"
                                    >
                                        {{ product.name }}
                                    </Link>
                                </div>
                            </TableCell>
                            <TableCell>
                                <Link
                                    :href="
                                        showCategory(
                                            product.product_category.id,
                                        )
                                    "
                                    class="hover:underline"
                                >
                                    {{ product.product_category.name }}
                                </Link>
                            </TableCell>
                            <TableCell>{{
                                product.brand?.name ?? '—'
                            }}</TableCell>
                            <TableCell class="text-muted-foreground">
                                {{ product.unit ?? '—' }}
                            </TableCell>
                            <TableCell class="text-right tabular-nums">
                                {{ product.branch_prices_count ?? 0 }}
                                <span class="text-xs text-muted-foreground">
                                    {{
                                        product.branch_prices_count === 1
                                            ? 'branch'
                                            : 'branches'
                                    }}
                                </span>
                            </TableCell>
                            <TableCell>
                                <div class="text-sm">
                                    {{ formatDate(product.created_at) }}
                                </div>
                                <div class="text-xs text-muted-foreground">
                                    {{ product.creator?.name ?? '—' }}
                                </div>
                            </TableCell>
                            <TableCell class="space-x-1.5 text-right">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    as-child
                                    class="bg-blue-50 text-blue-600 hover:bg-blue-100 hover:text-blue-800 dark:bg-blue-950/30 dark:text-blue-400 dark:hover:bg-blue-900/40 dark:hover:text-blue-300"
                                    :aria-label="`View ${product.name}`"
                                    :data-test="`view-product-${product.id}`"
                                >
                                    <Link :href="show(product.id)">
                                        <Eye class="h-4 w-4" />
                                    </Link>
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    as-child
                                    class="bg-amber-50 text-amber-600 hover:bg-amber-100 hover:text-amber-800 dark:bg-amber-950/30 dark:text-amber-400 dark:hover:bg-amber-900/40 dark:hover:text-amber-300"
                                    :aria-label="`Edit ${product.name}`"
                                    :data-test="`edit-product-${product.id}`"
                                >
                                    <Link :href="edit(product.id)">
                                        <Pencil class="h-4 w-4" />
                                    </Link>
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    class="bg-red-50 text-red-600 hover:bg-red-100 hover:text-red-800 dark:bg-red-950/30 dark:text-red-400 dark:hover:bg-red-900/40 dark:hover:text-red-300"
                                    :aria-label="`Delete ${product.name}`"
                                    :data-test="`delete-product-${product.id}`"
                                    @click="confirmDelete(product)"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </TableCell>
                        </TableRow>
                        <TableRow v-if="products.data.length === 0">
                            <TableCell
                                :colspan="9"
                                class="text-center text-muted-foreground"
                            >
                                No products match these filters.
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </CardContent>
        </Card>

        <TablePagination :links="products.links" />
    </div>

    <ConfirmDeleteModal
        :open="deleteDialogOpen"
        title="Delete product"
        :description="`This will permanently delete “${productToDelete?.name}”.`"
        :delete-url="productToDelete ? destroy.url(productToDelete.id) : null"
        @update:open="deleteDialogOpen = $event"
    />
</template>
