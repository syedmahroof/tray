<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Building2,
    History,
    IndianRupee,
    Package,
    Pencil,
    Tag,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import TablePagination from '@/components/TablePagination.vue';
import Thumbnail from '@/components/Thumbnail.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
import { money } from '@/lib/products';
import { formatDate } from '@/lib/utils';
import { edit, index } from '@/routes/products';
import { show as showProject } from '@/routes/projects';
import type {
    Branch,
    NamedOption,
    Paginated,
    Product,
    ProductBranchPrice,
    ProductPriceHistory,
} from '@/types';

type ProductProject = NamedOption & {
    status: string;
    builder: NamedOption | null;
};

type ProductDetail = Product & {
    product_category: NamedOption;
    brand: NamedOption | null;
    projects: ProductProject[];
};

const props = defineProps<{
    product: ProductDetail;
    branches: Branch[];
    branchPrices: (ProductBranchPrice & { branch: Branch })[];
    priceHistory?: Paginated<ProductPriceHistory>;
    historyFilters: { branch_id: string | number | null };
}>();

defineOptions({
    layout: { breadcrumbs: [] },
});

const permissions = computed(() => usePage().props.auth.permissions);

const canSeePrices = computed(() =>
    permissions.value.includes('products.price.view'),
);
const canSeeHistory = computed(() =>
    permissions.value.includes('products.price.history'),
);

/** The workbook's six rate columns, rendered per branch row. */
const TIER_COLUMNS = [
    { key: 'sr_rate', label: 'SR' },
    { key: 'sr_rate_with_tax', label: 'SR+TAX' },
    { key: 'pr_rate', label: 'PR' },
    { key: 'pr_rate_with_tax', label: 'PR+TAX' },
    { key: 'cr_rate', label: 'CR' },
    { key: 'cr_rate_with_tax', label: 'CR+TAX' },
] as const;

const historyBranch = ref(
    props.historyFilters.branch_id
        ? String(props.historyFilters.branch_id)
        : 'all',
);

const historyLoading = ref(false);

/** Pull the log in on demand, and again whenever the branch filter moves. */
const loadHistory = () => {
    if (!canSeeHistory.value) {
        return;
    }

    historyLoading.value = true;

    router.reload({
        only: ['priceHistory'],
        data: {
            history_branch_id:
                historyBranch.value !== 'all' ? historyBranch.value : undefined,
            history_page: undefined,
        },
        onFinish: () => (historyLoading.value = false),
    });
};

watch(historyBranch, loadHistory);

/** Turn a snake_case field name into the label used in the price form. */
const fieldLabel = (field: string) =>
    field
        .replace(/^(sr|pr|cr)_/, (tier) => `${tier.slice(0, 2).toUpperCase()} `)
        .replace(/_with_tax$/, ' (incl. tax)')
        .replace(/_/g, ' ')
        .replace(/^\w/, (character) => character.toUpperCase());
</script>

<template>
    <Head :title="product.name" />

    <div class="flex flex-col space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <Link
                    :href="index()"
                    class="mb-2 inline-flex items-center gap-1 text-sm text-muted-foreground hover:underline"
                >
                    <ArrowLeft class="h-4 w-4 text-slate-500" /> Back to List
                </Link>
                <div class="flex items-center gap-4">
                    <Thumbnail
                        :src="product.image_url"
                        :name="product.name"
                        class="size-16"
                    />
                    <Heading
                        variant="small"
                        :title="product.name"
                        :description="`Category: ${product.product_category.name}`"
                    />
                </div>
            </div>

            <Button v-if="permissions.includes('products.update')" as-child>
                <Link :href="edit(product.id)"><Pencil /> Edit</Link>
            </Button>
        </div>

        <div class="grid gap-6 md:grid-cols-2">
            <!-- Details -->
            <Card>
                <CardHeader
                    class="flex flex-row items-center gap-2 border-b pb-3"
                >
                    <Tag class="h-5 w-5 text-[#059669]" />
                    <CardTitle class="text-base font-semibold"
                        >Product Details</CardTitle
                    >
                </CardHeader>
                <CardContent class="grid grid-cols-2 gap-4 pt-6">
                    <div>
                        <p class="text-sm text-muted-foreground">Category</p>
                        <p class="text-sm font-medium">
                            {{ product.product_category.name }}
                        </p>
                    </div>
                    <div>
                        <p class="text-sm text-muted-foreground">Brand</p>
                        <p class="text-sm font-medium">
                            {{ product.brand?.name ?? '—' }}
                        </p>
                    </div>
                    <div>
                        <p class="text-sm text-muted-foreground">Code</p>
                        <p class="text-sm font-medium">
                            {{ product.code ?? '—' }}
                        </p>
                    </div>
                    <div>
                        <p class="text-sm text-muted-foreground">Unit</p>
                        <p class="text-sm font-medium">
                            {{ product.unit ?? '—' }}
                        </p>
                    </div>
                    <div>
                        <p class="text-sm text-muted-foreground">Price</p>
                        <p class="text-sm font-medium">
                            {{
                                product.price
                                    ? `₹${parseFloat(product.price).toLocaleString()}`
                                    : '—'
                            }}
                        </p>
                    </div>
                    <div>
                        <p class="text-sm text-muted-foreground">HSN Code</p>
                        <p class="text-sm font-medium">
                            {{ product.hsn_code ?? '—' }}
                        </p>
                    </div>
                    <div>
                        <p class="text-sm text-muted-foreground">GST</p>
                        <p class="text-sm font-medium">
                            {{ product.tax_type ?? '—'
                            }}{{
                                Number(product.tax_percentage)
                                    ? ` (${Number(product.tax_percentage)}%)`
                                    : ''
                            }}
                        </p>
                    </div>
                    <div>
                        <p class="text-sm text-muted-foreground">Area (Sqft)</p>
                        <p class="text-sm font-medium">
                            {{
                                product.area_sqft
                                    ? `${parseFloat(product.area_sqft).toLocaleString()} sqft`
                                    : '—'
                            }}
                        </p>
                    </div>
                </CardContent>
            </Card>

            <!-- Description -->
            <Card>
                <CardHeader
                    class="flex flex-row items-center gap-2 border-b pb-3"
                >
                    <Package class="h-5 w-5 text-[#059669]" />
                    <CardTitle class="text-base font-semibold"
                        >Description</CardTitle
                    >
                </CardHeader>
                <CardContent class="pt-6">
                    <p class="text-sm font-medium whitespace-pre-wrap">
                        {{ product.description ?? 'No description provided.' }}
                    </p>
                </CardContent>
            </Card>
        </div>

        <!-- Branch prices -->
        <Card v-if="canSeePrices">
            <CardHeader class="flex flex-row items-center gap-2 border-b pb-3">
                <IndianRupee class="h-5 w-5 text-[#059669]" />
                <CardTitle class="text-base font-semibold">
                    Branch Prices
                </CardTitle>
            </CardHeader>
            <CardContent class="px-0">
                <div class="overflow-x-auto">
                    <Table class="min-w-max">
                        <TableHeader>
                            <TableRow>
                                <TableHead>Branch</TableHead>
                                <TableHead class="text-right">COST</TableHead>
                                <TableHead class="text-right">MRP</TableHead>
                                <TableHead
                                    v-for="column in TIER_COLUMNS"
                                    :key="column.key"
                                    class="text-right whitespace-nowrap"
                                >
                                    {{ column.label }}
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow
                                v-for="price in branchPrices"
                                :key="price.id"
                            >
                                <TableCell class="font-medium">
                                    {{ price.branch?.name ?? '—' }}
                                </TableCell>
                                <TableCell
                                    class="text-right text-muted-foreground tabular-nums"
                                >
                                    {{ money(price.cost) }}
                                </TableCell>
                                <TableCell
                                    class="text-right text-muted-foreground tabular-nums"
                                >
                                    {{ money(price.mrp) }}
                                </TableCell>
                                <TableCell
                                    v-for="(column, position) in TIER_COLUMNS"
                                    :key="`${price.id}-${column.key}`"
                                    class="text-right tabular-nums"
                                    :class="
                                        position % 2 === 0
                                            ? 'font-medium'
                                            : 'text-muted-foreground'
                                    "
                                >
                                    {{ money(price[column.key]) }}
                                </TableCell>
                            </TableRow>
                            <TableRow v-if="branchPrices.length === 0">
                                <TableCell
                                    :colspan="3 + TIER_COLUMNS.length"
                                    class="text-center text-muted-foreground"
                                >
                                    No branch prices set for this product yet.
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>
            </CardContent>
        </Card>

        <!-- Price change log -->
        <Card v-if="canSeeHistory">
            <CardHeader class="border-b pb-3">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <History class="h-5 w-5 text-[#7c3aed]" />
                        <CardTitle class="text-base font-semibold">
                            Price Update Log
                        </CardTitle>
                    </div>

                    <div class="flex items-center gap-2">
                        <Select v-model="historyBranch">
                            <SelectTrigger class="w-[200px]">
                                <SelectValue placeholder="All branches" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">
                                    All branches
                                </SelectItem>
                                <SelectItem
                                    v-for="branch in branches"
                                    :key="branch.id"
                                    :value="String(branch.id)"
                                >
                                    {{ branch.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>

                        <Button
                            v-if="!priceHistory"
                            variant="outline"
                            size="sm"
                            :disabled="historyLoading"
                            @click="loadHistory"
                        >
                            {{ historyLoading ? 'Loading…' : 'Show log' }}
                        </Button>
                    </div>
                </div>
            </CardHeader>
            <CardContent class="pt-6">
                <div
                    v-if="!priceHistory"
                    class="space-y-2"
                    :class="historyLoading ? 'animate-pulse' : undefined"
                >
                    <div
                        v-for="row in 3"
                        :key="row"
                        class="h-9 rounded bg-muted"
                    />
                </div>

                <template v-else>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>When</TableHead>
                                <TableHead>Branch</TableHead>
                                <TableHead>Field</TableHead>
                                <TableHead class="text-right">From</TableHead>
                                <TableHead class="text-right">To</TableHead>
                                <TableHead>By</TableHead>
                                <TableHead>Reason</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow
                                v-for="entry in priceHistory.data"
                                :key="entry.id"
                            >
                                <TableCell class="whitespace-nowrap">
                                    {{ formatDate(entry.changed_at) }}
                                </TableCell>
                                <TableCell>
                                    {{ entry.branch?.name ?? '—' }}
                                </TableCell>
                                <TableCell>{{
                                    fieldLabel(entry.field)
                                }}</TableCell>
                                <TableCell
                                    class="text-right text-muted-foreground tabular-nums"
                                >
                                    {{ money(entry.old_value) }}
                                </TableCell>
                                <TableCell
                                    class="text-right font-medium tabular-nums"
                                >
                                    {{ money(entry.new_value) }}
                                </TableCell>
                                <TableCell>
                                    {{ entry.user?.name ?? 'System' }}
                                </TableCell>
                                <TableCell class="text-muted-foreground">
                                    {{ entry.reason ?? '—' }}
                                </TableCell>
                            </TableRow>
                            <TableRow v-if="priceHistory.data.length === 0">
                                <TableCell
                                    :colspan="7"
                                    class="text-center text-muted-foreground"
                                >
                                    No price changes recorded yet.
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>

                    <TablePagination
                        v-if="priceHistory.data.length > 0"
                        class="mt-4"
                        :links="priceHistory.links"
                    />
                </template>
            </CardContent>
        </Card>

        <!-- Project Information -->
        <Card>
            <CardHeader class="flex flex-row items-center gap-2 border-b pb-3">
                <Building2 class="h-5 w-5 text-[#4f46e5]" />
                <CardTitle class="text-base font-semibold"
                    >Project Information</CardTitle
                >
            </CardHeader>
            <CardContent class="pt-6">
                <div
                    v-if="product.projects.length"
                    class="flex flex-wrap gap-2"
                >
                    <Link
                        v-for="project in product.projects"
                        :key="project.id"
                        :href="showProject(project.id)"
                        class="hover:underline"
                    >
                        <Badge variant="secondary" class="gap-1.5">
                            {{ project.name }}
                            <span class="text-muted-foreground capitalize">
                                {{ project.status }}
                            </span>
                            <span
                                v-if="project.builder"
                                class="text-muted-foreground"
                            >
                                · {{ project.builder.name }}
                            </span>
                        </Badge>
                    </Link>
                </div>
                <p v-else class="text-sm text-muted-foreground">
                    This product is not linked to any project yet.
                </p>
            </CardContent>
        </Card>
    </div>
</template>
