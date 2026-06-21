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
import { create, index, store } from '@/routes/products';
import type { Branch, NamedOption } from '@/types';

const props = defineProps<{
    productCategories: NamedOption[];
    branches: Branch[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Products', href: index() },
            { title: 'New product', href: create() },
        ],
    },
});

const categoryOptions = computed(() =>
    props.productCategories.map((category) => ({
        value: String(category.id),
        label: category.name,
    })),
);
</script>

<template>
    <Head title="New product" />

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            title="New product"
            description="Create a new product"
        />

        <Form
            v-bind="store.form()"
            class="max-w-3xl space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="name">Name</Label>
                <Input id="name" name="name" required autofocus />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="product_category_id">Category</Label>
                <Combobox
                    name="product_category_id"
                    placeholder="Select a category"
                    :options="categoryOptions"
                />
                <InputError :message="errors.product_category_id" />
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
                    />
                    <InputError :message="errors.area_sqft" />
                </div>
            </div>

            <div class="grid gap-2">
                <Label for="description">Description</Label>
                <Textarea id="description" name="description" rows="4" />
                <InputError :message="errors.description" />
            </div>

            <div v-if="branches.length > 0" class="grid gap-2">
                <Label for="branch_id">Branch</Label>
                <Select name="branch_id">
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

            <Button type="submit" :disabled="processing">
                Create product
            </Button>
        </Form>
    </div>
</template>
