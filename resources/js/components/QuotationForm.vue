<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Plus, Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import Combobox from '@/components/Combobox.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
import { store, update } from '@/routes/quotations';
import type { Branch, NamedOption, QuotationDetail } from '@/types';

type ProductOption = NamedOption & { price: string | null };
type ContactOption = NamedOption & {
    phone: string | null;
    email: string | null;
};

type QuotationDefaults = {
    contact_id: number | null;
    project_id: number | null;
    enquiry_id: number | null;
    builder_id: number | null;
};

const props = defineProps<{
    quotation?: QuotationDetail | null;
    contacts: ContactOption[];
    projects: NamedOption[];
    enquiries: NamedOption[];
    builders: NamedOption[];
    products: ProductOption[];
    statuses: string[];
    branches: Branch[];
    defaults?: QuotationDefaults | null;
}>();

const emptyItem = () => ({
    product_id: undefined as string | undefined,
    description: '',
    quantity: '1',
    unit_price: '0',
});

const idString = (value?: number | null) => (value ? String(value) : undefined);

const form = useForm({
    contact_id: idString(
        props.quotation?.contact_id ?? props.defaults?.contact_id,
    ),
    project_id: idString(
        props.quotation?.project_id ?? props.defaults?.project_id,
    ),
    enquiry_id: idString(
        props.quotation?.enquiry_id ?? props.defaults?.enquiry_id,
    ),
    builder_id: idString(
        props.quotation?.builder_id ?? props.defaults?.builder_id,
    ),
    quotation_date:
        props.quotation?.quotation_date ??
        new Date().toISOString().slice(0, 10),
    valid_until: props.quotation?.valid_until ?? '',
    status: props.quotation?.status ?? 'draft',
    branch_id: props.quotation?.branch_id
        ? String(props.quotation.branch_id)
        : undefined,
    discount: props.quotation?.discount ?? '0',
    tax_percent: props.quotation?.tax_percent ?? '0',
    notes: props.quotation?.notes ?? '',
    terms: props.quotation?.terms ?? '',
    items:
        props.quotation?.items && props.quotation.items.length
            ? props.quotation.items.map((item) => ({
                  product_id: item.product_id
                      ? String(item.product_id)
                      : undefined,
                  description: item.description,
                  quantity: item.quantity,
                  unit_price: item.unit_price,
              }))
            : [emptyItem()],
});

const contactOptions = computed(() =>
    props.contacts.map((c) => ({ value: String(c.id), label: c.name })),
);
const projectOptions = computed(() =>
    props.projects.map((p) => ({ value: String(p.id), label: p.name })),
);
const enquiryOptions = computed(() =>
    props.enquiries.map((e) => ({ value: String(e.id), label: e.name })),
);
const builderOptions = computed(() =>
    props.builders.map((b) => ({ value: String(b.id), label: b.name })),
);
const productOptions = computed(() =>
    props.products.map((p) => ({ value: String(p.id), label: p.name })),
);

const addItem = () => form.items.push(emptyItem());
const removeItem = (index: number) => {
    if (form.items.length > 1) {
        form.items.splice(index, 1);
    }
};

const onProductSelect = (index: number) => {
    const item = form.items[index];
    const product = props.products.find(
        (p) => String(p.id) === item.product_id,
    );

    if (product) {
        if (!item.description) {
            item.description = product.name;
        }

        if (product.price && (!item.unit_price || item.unit_price === '0')) {
            item.unit_price = product.price;
        }
    }
};

const lineTotal = (item: { quantity: string; unit_price: string }) =>
    (parseFloat(item.quantity) || 0) * (parseFloat(item.unit_price) || 0);

const subtotal = computed(() =>
    form.items.reduce((sum, item) => sum + lineTotal(item), 0),
);
const taxable = computed(() =>
    Math.max(subtotal.value - (parseFloat(form.discount) || 0), 0),
);
const taxAmount = computed(
    () => (taxable.value * (parseFloat(form.tax_percent) || 0)) / 100,
);
const total = computed(() => taxable.value + taxAmount.value);

const money = (value: number) =>
    `₹${value.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

const submit = () => {
    if (props.quotation) {
        form.put(update.url(props.quotation.id), { preserveScroll: true });
    } else {
        form.post(store.url(), { preserveScroll: true });
    }
};
</script>

<template>
    <form class="space-y-6" @submit.prevent="submit">
        <div class="grid gap-6 lg:grid-cols-3">
            <!-- Main -->
            <div class="space-y-6 lg:col-span-2">
                <Card>
                    <CardHeader>
                        <CardTitle>Quotation Details</CardTitle>
                    </CardHeader>
                    <CardContent class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label>Contact *</Label>
                            <Combobox
                                v-model="form.contact_id"
                                placeholder="Select a contact"
                                :options="contactOptions"
                            />
                            <InputError :message="form.errors.contact_id" />
                        </div>

                        <div class="grid gap-2">
                            <Label>Project</Label>
                            <Combobox
                                v-model="form.project_id"
                                placeholder="Select a project (optional)"
                                :options="projectOptions"
                            />
                            <InputError :message="form.errors.project_id" />
                        </div>

                        <div class="grid gap-2">
                            <Label>Enquiry</Label>
                            <Combobox
                                v-model="form.enquiry_id"
                                placeholder="Link an enquiry (optional)"
                                :options="enquiryOptions"
                            />
                            <InputError :message="form.errors.enquiry_id" />
                        </div>

                        <div class="grid gap-2">
                            <Label>Builder</Label>
                            <Combobox
                                v-model="form.builder_id"
                                placeholder="Link a builder (optional)"
                                :options="builderOptions"
                            />
                            <InputError :message="form.errors.builder_id" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="quotation_date">Quotation Date *</Label>
                            <Input
                                id="quotation_date"
                                v-model="form.quotation_date"
                                type="date"
                            />
                            <InputError :message="form.errors.quotation_date" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="valid_until">Valid Until</Label>
                            <Input
                                id="valid_until"
                                v-model="form.valid_until"
                                type="date"
                            />
                            <InputError :message="form.errors.valid_until" />
                        </div>

                        <div class="grid gap-2">
                            <Label>Status</Label>
                            <Select v-model="form.status">
                                <SelectTrigger class="w-full">
                                    <SelectValue placeholder="Select status" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="status in statuses"
                                        :key="status"
                                        :value="status"
                                        class="capitalize"
                                    >
                                        {{ status }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="form.errors.status" />
                        </div>

                        <div v-if="branches.length > 0" class="grid gap-2">
                            <Label>Branch *</Label>
                            <Select v-model="form.branch_id">
                                <SelectTrigger class="w-full">
                                    <SelectValue
                                        placeholder="Select a branch"
                                    />
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
                            <InputError :message="form.errors.branch_id" />
                        </div>
                    </CardContent>
                </Card>

                <!-- Line Items -->
                <Card>
                    <CardHeader
                        class="flex flex-row items-center justify-between"
                    >
                        <CardTitle>Items</CardTitle>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="addItem"
                        >
                            <Plus class="h-4 w-4" /> Add item
                        </Button>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <div
                            v-for="(item, index) in form.items"
                            :key="index"
                            class="grid items-end gap-3 rounded-lg border p-3 sm:grid-cols-12"
                        >
                            <div class="grid gap-1.5 sm:col-span-3">
                                <Label class="text-xs">Product</Label>
                                <Combobox
                                    v-model="item.product_id"
                                    placeholder="Optional"
                                    :options="productOptions"
                                    @update:model-value="onProductSelect(index)"
                                />
                            </div>
                            <div class="grid gap-1.5 sm:col-span-4">
                                <Label class="text-xs">Description *</Label>
                                <Input
                                    v-model="item.description"
                                    placeholder="Item description"
                                />
                                <InputError
                                    :message="
                                        form.errors[
                                            `items.${index}.description`
                                        ]
                                    "
                                />
                            </div>
                            <div class="grid gap-1.5 sm:col-span-1">
                                <Label class="text-xs">Qty</Label>
                                <Input
                                    v-model="item.quantity"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                />
                            </div>
                            <div class="grid gap-1.5 sm:col-span-2">
                                <Label class="text-xs">Unit Price</Label>
                                <Input
                                    v-model="item.unit_price"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                />
                            </div>
                            <div
                                class="flex items-center justify-between gap-2 sm:col-span-2"
                            >
                                <span class="text-sm font-medium tabular-nums">
                                    {{ money(lineTotal(item)) }}
                                </span>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    :disabled="form.items.length === 1"
                                    @click="removeItem(index)"
                                >
                                    <Trash2 class="h-4 w-4 text-red-500" />
                                </Button>
                            </div>
                        </div>
                        <InputError :message="form.errors.items" />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Notes &amp; Terms</CardTitle>
                    </CardHeader>
                    <CardContent class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="notes">Notes</Label>
                            <Textarea
                                id="notes"
                                v-model="form.notes"
                                rows="4"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label for="terms">Terms &amp; Conditions</Label>
                            <Textarea
                                id="terms"
                                v-model="form.terms"
                                rows="4"
                            />
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Summary sidebar -->
            <div class="space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Summary</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="flex justify-between text-sm">
                            <span class="text-muted-foreground">Subtotal</span>
                            <span class="font-medium tabular-nums">{{
                                money(subtotal)
                            }}</span>
                        </div>
                        <div class="grid gap-2">
                            <Label for="discount">Discount</Label>
                            <Input
                                id="discount"
                                v-model="form.discount"
                                type="number"
                                min="0"
                                step="0.01"
                            />
                            <InputError :message="form.errors.discount" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="tax_percent">Tax (%)</Label>
                            <Input
                                id="tax_percent"
                                v-model="form.tax_percent"
                                type="number"
                                min="0"
                                max="100"
                                step="0.01"
                            />
                            <InputError :message="form.errors.tax_percent" />
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-muted-foreground">Tax</span>
                            <span class="font-medium tabular-nums">{{
                                money(taxAmount)
                            }}</span>
                        </div>
                        <div
                            class="flex justify-between border-t pt-3 text-base font-semibold"
                        >
                            <span>Total</span>
                            <span class="tabular-nums">{{ money(total) }}</span>
                        </div>

                        <Button
                            type="submit"
                            class="w-full"
                            :disabled="form.processing"
                        >
                            {{
                                quotation ? 'Save Changes' : 'Create Quotation'
                            }}
                        </Button>
                    </CardContent>
                </Card>
            </div>
        </div>
    </form>
</template>
