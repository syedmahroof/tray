<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
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
    gstSlabs: Record<string, number>;
}>();

const price = ref<number | undefined>(
    props.product.price ? Number(props.product.price) : undefined,
);
const taxableAmount = ref<number | undefined>(
    props.product.taxable_amount
        ? Number(props.product.taxable_amount)
        : undefined,
);
const taxType = ref(props.product.tax_type ?? '');
const taxPercentage = ref(Number(props.product.tax_percentage ?? 0));
const lastEdited = ref<'price' | 'taxable'>('price');

const round2 = (value: number) => Math.round(value * 100) / 100;
const toNumber = (value: string | number) =>
    value === '' || value === null ? undefined : Number(value);

const recomputeFromPrice = () => {
    const factor = 1 + taxPercentage.value / 100;
    taxableAmount.value =
        price.value !== undefined ? round2(price.value / factor) : undefined;
};
const recomputeFromTaxable = () => {
    const factor = 1 + taxPercentage.value / 100;
    price.value =
        taxableAmount.value !== undefined
            ? round2(taxableAmount.value * factor)
            : undefined;
};

const onPriceInput = (value: string | number) => {
    price.value = toNumber(value);
    lastEdited.value = 'price';
    recomputeFromPrice();
};
const onTaxableInput = (value: string | number) => {
    taxableAmount.value = toNumber(value);
    lastEdited.value = 'taxable';
    recomputeFromTaxable();
};

const onTaxTypeChange = (label: string) => {
    taxType.value = label;
    taxPercentage.value = props.gstSlabs[label] ?? 0;
    if (lastEdited.value === 'taxable') {
        recomputeFromTaxable();
    } else {
        recomputeFromPrice();
    }
};

const taxAmount = computed(() =>
    round2((price.value ?? 0) - (taxableAmount.value ?? 0)),
);

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

            <div class="grid gap-4 sm:grid-cols-3">
                <div class="grid gap-2">
                    <Label for="price">Price (incl. tax)</Label>
                    <Input
                        id="price"
                        :model-value="price"
                        name="price"
                        type="number"
                        step="0.01"
                        min="0"
                        @update:model-value="onPriceInput"
                    />
                    <InputError :message="errors.price" />
                </div>

                <div class="grid gap-2">
                    <Label for="taxable_amount">Taxable Amount</Label>
                    <Input
                        id="taxable_amount"
                        :model-value="taxableAmount"
                        name="taxable_amount"
                        type="number"
                        step="0.01"
                        min="0"
                        @update:model-value="onTaxableInput"
                    />
                    <InputError :message="errors.taxable_amount" />
                </div>

                <div class="grid gap-2">
                    <Label for="hsn_code">HSN Code</Label>
                    <Input
                        id="hsn_code"
                        name="hsn_code"
                        :default-value="product.hsn_code ?? undefined"
                    />
                    <InputError :message="errors.hsn_code" />
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <div class="grid gap-2">
                    <Label>Tax Type (GST Slab)</Label>
                    <Select
                        name="tax_type"
                        :model-value="taxType"
                        @update:model-value="(v) => onTaxTypeChange(String(v))"
                    >
                        <SelectTrigger class="w-full">
                            <SelectValue placeholder="Select GST slab" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="label in Object.keys(gstSlabs)"
                                :key="label"
                                :value="label"
                            >
                                {{ label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="errors.tax_type" />
                </div>

                <div class="grid gap-2">
                    <Label for="tax_percentage">Tax %</Label>
                    <Input
                        id="tax_percentage"
                        v-model="taxPercentage"
                        name="tax_percentage"
                        type="number"
                        step="0.01"
                        readonly
                    />
                    <InputError :message="errors.tax_percentage" />
                </div>

                <div class="grid gap-2">
                    <Label>Tax Amount (per unit)</Label>
                    <Input :model-value="taxAmount.toFixed(2)" disabled />
                </div>
            </div>

            <div class="grid gap-2 sm:max-w-[calc(50%-0.5rem)]">
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
