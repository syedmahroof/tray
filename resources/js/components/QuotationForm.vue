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

type ProductOption = NamedOption & {
    price: string | null;
    taxable_amount: string | null;
    hsn_code: string | null;
    tax_percentage: string;
};
type ContactOption = NamedOption & {
    phone: string | null;
    email: string | null;
    contact_type: NamedOption | null;
};

type QuotationDefaults = {
    contact_id: number | null;
    project_id: number | null;
    enquiry_id: number | null;
    builder_id: number | null;
};

const props = defineProps<{
    quotation?: QuotationDetail | null;
    customers: { id: number; name: string }[];
    contacts: ContactOption[];
    projects: NamedOption[];
    enquiries: NamedOption[];
    builders: NamedOption[];
    products: ProductOption[];
    statuses: string[];
    gstSlabs: Record<string, number>;
    branches: Branch[];
    defaults?: QuotationDefaults & { customer_id?: number } | null;
}>();

// Distinct GST rates for the per-line tax select box (e.g. 0, 5, 12, 18, 28).
const gstRateOptions = computed(() =>
    [...new Set(Object.values(props.gstSlabs))].sort((a, b) => a - b),
);

const emptyItem = () => ({
    product_id: undefined as string | undefined,
    description: '',
    hsn_code: '',
    quantity: '1',
    unit_price: '0',
    tax_percentage: '0',
});

const idString = (value?: number | null) => (value ? String(value) : undefined);

const form = useForm({
    customer_id: idString(
        props.quotation?.customer_id ?? props.defaults?.customer_id,
    ),
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
    gstin: props.quotation?.gstin ?? '',
    supply_type: props.quotation?.supply_type ?? 'intra',
    quotation_date:
        props.quotation?.quotation_date ??
        new Date().toISOString().slice(0, 10),
    valid_until: props.quotation?.valid_until ?? '',
    status: props.quotation?.status ?? 'draft',
    branch_id: props.quotation?.branch_id
        ? String(props.quotation.branch_id)
        : undefined,
    discount: props.quotation?.discount ?? '0',
    notes: props.quotation?.notes ?? '',
    terms: props.quotation?.terms ?? '',
    items:
        props.quotation?.items && props.quotation.items.length
            ? props.quotation.items.map((item) => ({
                  product_id: item.product_id
                      ? String(item.product_id)
                      : undefined,
                  description: item.description,
                  hsn_code: item.hsn_code ?? '',
                  quantity: item.quantity,
                  unit_price: item.unit_price,
                  tax_percentage: String(Number(item.tax_percentage ?? 0)),
              }))
            : [emptyItem()],
});

const customerOptions = computed(() =>
    props.customers.map((c) => ({ value: String(c.id), label: c.name })),
);
const contactOptions = computed(() =>
    props.contacts.map((c) => ({
        value: String(c.id),
        label: c.contact_type ? `${c.name} (${c.contact_type.name})` : c.name,
    })),
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

        // The line's unit price is the taxable (pre-GST) value; fall back to the
        // gross price when a product has no taxable amount recorded.
        const base = product.taxable_amount ?? product.price;
        if (base && (!item.unit_price || item.unit_price === '0')) {
            item.unit_price = base;
        }

        if (product.hsn_code && !item.hsn_code) {
            item.hsn_code = product.hsn_code;
        }

        item.tax_percentage = String(Number(product.tax_percentage ?? 0));
    }
};

const lineTotal = (item: { quantity: string; unit_price: string }) =>
    (parseFloat(item.quantity) || 0) * (parseFloat(item.unit_price) || 0);

const lineTax = (item: { quantity: string; unit_price: string; tax_percentage: string }) =>
    (lineTotal(item) * (parseFloat(item.tax_percentage) || 0)) / 100;

const lineGrandTotal = (item: { quantity: string; unit_price: string; tax_percentage: string }) =>
    lineTotal(item) + lineTax(item);

const subtotal = computed(() =>
    form.items.reduce((sum, item) => sum + lineTotal(item), 0),
);
const discountValue = computed(() =>
    Math.min(parseFloat(form.discount) || 0, subtotal.value),
);
const taxable = computed(() =>
    Math.max(subtotal.value - discountValue.value, 0),
);

// Per-line GST on the discount-adjusted taxable value.
const taxAmount = computed(() =>
    form.items.reduce((sum, item) => {
        const base = lineTotal(item);
        const allocatedDiscount =
            subtotal.value > 0
                ? discountValue.value * (base / subtotal.value)
                : 0;
        const lineTaxable = Math.max(base - allocatedDiscount, 0);
        return (
            sum + (lineTaxable * (parseFloat(item.tax_percentage) || 0)) / 100
        );
    }, 0),
);

const isInterState = computed(() => form.supply_type === 'inter');
const cgstAmount = computed(() =>
    isInterState.value ? 0 : taxAmount.value / 2,
);
const sgstAmount = computed(() =>
    isInterState.value ? 0 : taxAmount.value / 2,
);
const igstAmount = computed(() => (isInterState.value ? taxAmount.value : 0));
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
        <div class="space-y-6">
            <!-- Main -->
            <Card>
                <CardHeader>
                    <CardTitle>Quotation Details</CardTitle>
                </CardHeader>
                <CardContent class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                        <div class="grid gap-2">
                            <Label>Customer *</Label>
                            <Combobox
                                v-model="form.customer_id"
                                placeholder="Select a customer"
                                :options="customerOptions"
                            />
                            <InputError :message="form.errors.customer_id" />
                        </div>

                        <div class="grid gap-2">
                            <Label>Contact</Label>
                            <Combobox
                                v-model="form.contact_id"
                                placeholder="Select a contact (optional)"
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
                            <Label for="gstin">Buyer GSTIN</Label>
                            <Input
                                id="gstin"
                                v-model="form.gstin"
                                placeholder="e.g. 29ABCDE1234F1Z5"
                            />
                            <InputError :message="form.errors.gstin" />
                        </div>

                        <div class="grid gap-2">
                            <Label>Supply Type *</Label>
                            <Select v-model="form.supply_type">
                                <SelectTrigger class="w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="intra"
                                        >Intra-state (CGST + SGST)</SelectItem
                                    >
                                    <SelectItem value="inter"
                                        >Inter-state (IGST)</SelectItem
                                    >
                                </SelectContent>
                            </Select>
                            <InputError :message="form.errors.supply_type" />
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
                    <CardContent class="overflow-x-auto">
                        <div class="min-w-[1050px] space-y-3 pb-2">
                            <!-- Header Row -->
                            <div class="grid grid-cols-[1.5fr_2fr_1fr_0.75fr_1fr_0.75fr_1fr_1fr_1fr_auto] gap-3 px-1 text-xs font-medium text-muted-foreground pb-2 border-b">
                                <div>Product</div>
                                <div>Description *</div>
                                <div>HSN</div>
                                <div>Qty</div>
                                <div>Unit Price</div>
                                <div>GST %</div>
                                <div>Taxable</div>
                                <div>Tax Amt</div>
                                <div>Total</div>
                                <div class="w-8"></div>
                            </div>

                            <!-- Line Items -->
                            <div
                                v-for="(item, index) in form.items"
                                :key="index"
                                class="grid grid-cols-[1.5fr_2fr_1fr_0.75fr_1fr_0.75fr_1fr_1fr_1fr_auto] gap-3 items-start"
                            >
                                <div>
                                    <Combobox
                                        v-model="item.product_id"
                                        placeholder="Optional"
                                        :options="productOptions"
                                        @update:model-value="
                                            onProductSelect(index)
                                        "
                                    />
                                </div>
                                <div class="grid gap-1.5">
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
                                <div>
                                    <Input
                                        v-model="item.hsn_code"
                                        placeholder="Code"
                                    />
                                </div>
                                <div>
                                    <Input
                                        v-model="item.quantity"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                    />
                                </div>
                                <div>
                                    <Input
                                        v-model="item.unit_price"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                    />
                                </div>
                                <div>
                                    <Select v-model="item.tax_percentage">
                                        <SelectTrigger class="w-full">
                                            <SelectValue placeholder="%" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem
                                                v-for="rate in gstRateOptions"
                                                :key="rate"
                                                :value="String(rate)"
                                            >
                                                {{ rate }}%
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div>
                                    <Input
                                        :value="lineTotal(item).toFixed(2)"
                                        readonly
                                        class="bg-muted text-muted-foreground"
                                    />
                                </div>
                                <div>
                                    <Input
                                        :value="lineTax(item).toFixed(2)"
                                        readonly
                                        class="bg-muted text-muted-foreground"
                                    />
                                </div>
                                <div>
                                    <Input
                                        :value="lineGrandTotal(item).toFixed(2)"
                                        readonly
                                        class="bg-muted font-medium text-foreground"
                                    />
                                </div>
                                <div class="flex justify-end">
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
                        </div>
                        <InputError :message="form.errors.items" class="mt-2" />
                    </CardContent>
                </Card>

            <!-- Summary (Bottom) -->
            <Card>
                <CardHeader>
                    <CardTitle>Summary</CardTitle>
                </CardHeader>
                <CardContent class="flex flex-col sm:flex-row justify-between gap-6">
                    <div class="w-full sm:w-1/3">
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
                    </div>
                    
                    <div class="w-full sm:w-1/3 space-y-4">
                        <div class="flex justify-between text-sm">
                            <span class="text-muted-foreground">Subtotal</span>
                            <span class="font-medium tabular-nums">{{
                                money(subtotal)
                            }}</span>
                        </div>
                        <div
                            v-if="discountValue > 0"
                            class="flex justify-between text-sm"
                        >
                            <span class="text-muted-foreground"
                                >Taxable value</span
                            >
                            <span class="font-medium tabular-nums">{{
                                money(taxable)
                            }}</span>
                        </div>
                        <template v-if="isInterState">
                            <div class="flex justify-between text-sm">
                                <span class="text-muted-foreground">IGST</span>
                                <span class="font-medium tabular-nums">{{
                                    money(igstAmount)
                                }}</span>
                            </div>
                        </template>
                        <template v-else>
                            <div class="flex justify-between text-sm">
                                <span class="text-muted-foreground">CGST</span>
                                <span class="font-medium tabular-nums">{{
                                    money(cgstAmount)
                                }}</span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-muted-foreground">SGST</span>
                                <span class="font-medium tabular-nums">{{
                                    money(sgstAmount)
                                }}</span>
                            </div>
                        </template>
                        <div class="flex justify-between text-sm">
                            <span class="text-muted-foreground">Total Tax</span>
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
                    </div>
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

            <div class="flex justify-end">
                <Button
                    type="submit"
                    class="w-full sm:w-auto"
                    :disabled="form.processing"
                >
                    {{
                        quotation ? 'Save Changes' : 'Create Quotation'
                    }}
                </Button>
            </div>
        </div>
    </form>
</template>
