<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Eye, Pencil, Plus, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import ConfirmDeleteModal from '@/components/ConfirmDeleteModal.vue';
import Heading from '@/components/Heading.vue';
import SearchInput from '@/components/SearchInput.vue';
import TablePagination from '@/components/TablePagination.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { create, destroy, edit, index, show } from '@/routes/products';
import type { Filters, Paginated, ProductListItem } from '@/types';

defineProps<{
    products: Paginated<ProductListItem>;
    filters: Filters;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Products', href: index() }],
    },
});

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

            <Button as-child>
                <Link :href="create()"><Plus /> New product</Link>
            </Button>
        </div>

        <Card>
            <CardContent>
                <div class="mb-4">
                    <SearchInput
                        :model-value="filters.search"
                        placeholder="Search products…"
                    />
                </div>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Name</TableHead>
                            <TableHead>Category</TableHead>
                            <TableHead>Price</TableHead>
                            <TableHead class="text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="product in products.data"
                            :key="product.id"
                        >
                            <TableCell class="font-medium">
                                <Link
                                    :href="show(product.id)"
                                    class="font-semibold text-foreground hover:underline"
                                >
                                    {{ product.name }}
                                </Link>
                            </TableCell>
                            <TableCell>{{
                                product.product_category.name
                            }}</TableCell>
                            <TableCell>{{ product.price ?? '—' }}</TableCell>
                            <TableCell class="text-right">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    as-child
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
                                :colspan="4"
                                class="text-center text-muted-foreground"
                            >
                                No products yet.
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
