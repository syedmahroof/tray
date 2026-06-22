<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import Combobox from '@/components/Combobox.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { edit, index, update } from '@/routes/products';
import type { Branch, NamedOption, Product } from '@/types';

const props = defineProps<{
    product: Product;
    productCategories: NamedOption[];
    brands: NamedOption[];
    branches: Branch[];
}>();

defineOptions({
    layout: (props: { product: Product }) => ({
        breadcrumbs: [
            { title: 'Products', href: index() },
            { title: props.product.name, href: edit(props.product.id) },
        ],
    }),
});

const categoryOptions = computed(() =>
    props.productCategories.map((category) => ({
        value: String(category.id),
        label: category.name,
    })),
);

const brandOptions = computed(() =>
    props.brands.map((brand) => ({
        value: String(brand.id),
        label: brand.name,
    })),
);
</script>

<template>
    <Head :title="`Edit ${product.name}`" />

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            :title="`Edit ${product.name}`"
            description="Update product details"
        />

        <Form
            v-bind="update.form(props.product.id)"
            class="max-w-3xl space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="name">Name</Label>
                <Input
                    id="name"
                    name="name"
                    :default-value="product.name"
                    required
                    autofocus
                />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="product_category_id">Category</Label>
                <Combobox
                    name="product_category_id"
                    placeholder="Select a category"
                    :options="categoryOptions"
                    :model-value="String(product.product_category_id)"
                />
                <InputError :message="errors.product_category_id" />
            </div>

            <div class="grid gap-2">
                <Label for="brand_id">Brand</Label>
                <Combobox
                    name="brand_id"
                    placeholder="Select a brand (optional)"
                    :options="brandOptions"
                    :model-value="
                        product.brand_id ? String(product.brand_id) : undefined
                    "
                />
                <InputError :message="errors.brand_id" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="price">Price</Label>
                    <Input
                        id="price"
                        name="price"
                        type="number"
                        step="0.01"
                        min="0"
                        :default-value="product.price ?? undefined"
                    />
                    <InputError :message="errors.price" />
                </div>

                <div class="grid gap-2">
                    <Label for="area_sqft">Area (sq ft)</Label>
                    <Input
                        id="area_sqft"
                        name="area_sqft"
                        type="number"
                        step="0.01"
                        min="0"
                        :default-value="product.area_sqft ?? undefined"
                    />
                    <InputError :message="errors.area_sqft" />
                </div>
            </div>

            <div class="grid gap-2">
                <Label for="description">Description</Label>
                <Textarea
                    id="description"
                    name="description"
                    rows="4"
                    :default-value="product.description ?? undefined"
                />
                <InputError :message="errors.description" />
            </div>

            <div v-if="branches.length > 0" class="grid gap-2">
                <Label for="branch_id">Branch</Label>
                <Select
                    name="branch_id"
                    :default-value="String(product.branch_id)"
                >
                    <SelectTrigger class="w-full">
                        <SelectValue placeholder="Select a branch" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="branch in branches"
                            :key="branch.id"
                            :value="String(branch.id)"
                        >
                            {{ branch.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="errors.branch_id" />
            </div>

            <Button type="submit" :disabled="processing">Save</Button>
        </Form>
    </div>
</template>
