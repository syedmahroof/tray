<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { Plus, Trash2 } from '@lucide/vue';
import { computed, watch } from 'vue';
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
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { index, show, store, update } from '@/routes/quotations';
import type {
    Branch,
    NamedOption,
    QuotationDetail,
    QuotationProductOption,
    RateTier,
} from '@/types';

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
    customers: { id: number; name: string; rate_tier?: RateTier | null }[];
    contacts: ContactOption[];
    projects: NamedOption[];
    enquiries: NamedOption[];
    builders: NamedOption[];
    products: QuotationProductOption[];
    statuses: string[];
    rateTiers: Record<RateTier, string>;
    gstSlabs: Record<string, number>;
    branches: Branch[];
    defaultBranchId?: number | null;
    defaults?: (QuotationDefaults & { customer_id?: number }) | null;
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
    rate_tier: (props.quotation?.rate_tier ?? 'SR') as RateTier,
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

const productFor = (productId: string | undefined) =>
    props.products.find((p) => String(p.id) === productId);

/** The branch whose rates price the lines: the chosen one, else the user's own. */
const pricingBranchId = computed(
    () => form.branch_id ?? idString(props.defaultBranchId),
);

/** The product's ex-tax rate for the chosen tier in the pricing branch. */
const tierRate = (product: QuotationProductOption) => {
    const branchId = pricingBranchId.value;

    return branchId
        ? (product.rates[Number(branchId)]?.[form.rate_tier] ?? null)
        : null;
};

const hasTierRate = (productId: string | undefined) => {
    const product = productFor(productId);

    return product ? tierRate(product) !== null : true;
};

const onProductSelect = (index: number) => {
    const item = form.items[index];
    const product = productFor(item.product_id);

    if (product) {
        if (!item.description) {
            item.description = product.name;
        }

        // The line's unit price is the taxable (pre-GST) value: the branch rate
        // for the chosen tier, else the product's own taxable amount or price.
        const base =
            tierRate(product) ?? product.taxable_amount ?? product.price;

        if (base) {
            item.unit_price = base;
        }

        if (product.hsn_code && !item.hsn_code) {
            item.hsn_code = product.hsn_code;
        }

        item.tax_percentage = String(Number(product.tax_percentage ?? 0));
    }
};

/**
 * Switching the rate tier or branch re-prices every product line that has a
 * rate there. Lines without one keep their price rather than being zeroed.
 */
watch([() => form.rate_tier, pricingBranchId], () => {
    for (const item of form.items) {
        const product = productFor(item.product_id);
        const rate = product ? tierRate(product) : null;

        if (rate !== null) {
            item.unit_price = rate;
        }
    }
});

/**
 * Choosing a customer who has a price type of their own switches the
 * quotation to it, which re-prices the lines through the watcher above.
 * It can still be changed by hand for this one quotation.
 */
watch(
    () => form.customer_id,
    (customerId) => {
        const tier = props.customers.find(
            (customer) => String(customer.id) === customerId,
        )?.rate_tier;

        if (tier) {
            form.rate_tier = tier;
        }
    },
);

const lineTotal = (item: { quantity: string; unit_price: string }) =>
    (parseFloat(item.quantity) || 0) * (parseFloat(item.unit_price) || 0);

const lineTax = (item: {
    quantity: string;
    unit_price: string;
    tax_percentage: string;
}) => (lineTotal(item) * (parseFloat(item.tax_percentage) || 0)) / 100;

const lineGrandTotal = (item: {
    quantity: string;
    unit_price: string;
    tax_percentage: string;
}) => lineTotal(item) + lineTax(item);

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

const amount = (value: number) =>
    value.toLocaleString('en-IN', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

const money = (value: number) => `₹${amount(value)}`;

const cancelHref = computed(() =>
    props.quotation ? show(props.quotation.id) : index(),
);

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
                <CardContent class="space-y-6">
                    <section class="space-y-4">
                        <h3
                            class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                        >
                            Customer
                        </h3>
                        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            <div class="grid content-start gap-2">
                                <Label>Customer *</Label>
                                <Combobox
                                    v-model="form.customer_id"
                                    placeholder="Select a customer"
                                    :options="customerOptions"
                                />
                                <InputError
                                    :message="form.errors.customer_id"
                                />
                            </div>

                            <div class="grid content-start gap-2">
                                <Label>Contact</Label>
                                <Combobox
                                    v-model="form.contact_id"
                                    placeholder="Select a contact (optional)"
                                    :options="contactOptions"
                                />
                                <InputError :message="form.errors.contact_id" />
                            </div>

                            <div class="grid content-start gap-2">
                                <Label for="gstin">Buyer GSTIN</Label>
                                <Input
                                    id="gstin"
                                    v-model="form.gstin"
                                    placeholder="e.g. 29ABCDE1234F1Z5"
                                />
                                <InputError :message="form.errors.gstin" />
                            </div>

                            <div class="grid content-start gap-2">
                                <Label>Project</Label>
                                <Combobox
                                    v-model="form.project_id"
                                    placeholder="Select a project (optional)"
                                    :options="projectOptions"
                                />
                                <InputError :message="form.errors.project_id" />
                            </div>

                            <div class="grid content-start gap-2">
                                <Label>Builder</Label>
                                <Combobox
                                    v-model="form.builder_id"
                                    placeholder="Link a builder (optional)"
                                    :options="builderOptions"
                                />
                                <InputError :message="form.errors.builder_id" />
                            </div>

                            <div class="grid content-start gap-2">
                                <Label>Enquiry</Label>
                                <Combobox
                                    v-model="form.enquiry_id"
                                    placeholder="Link an enquiry (optional)"
                                    :options="enquiryOptions"
                                />
                                <InputError :message="form.errors.enquiry_id" />
                            </div>
                        </div>
                    </section>

                    <section class="space-y-4 border-t pt-6">
                        <h3
                            class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                        >
                            Quotation
                        </h3>
                        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            <div class="grid content-start gap-2">
                                <Label for="quotation_date"
                                    >Quotation Date *</Label
                                >
                                <Input
                                    id="quotation_date"
                                    v-model="form.quotation_date"
                                    type="date"
                                />
                                <InputError
                                    :message="form.errors.quotation_date"
                                />
                            </div>

                            <div class="grid content-start gap-2">
                                <Label for="valid_until">Valid Until</Label>
                                <Input
                                    id="valid_until"
                                    v-model="form.valid_until"
                                    type="date"
                                />
                                <InputError
                                    :message="form.errors.valid_until"
                                />
                            </div>

                            <div class="grid content-start gap-2">
                                <Label>Status</Label>
                                <Select v-model="form.status">
                                    <SelectTrigger class="w-full capitalize">
                                        <SelectValue
                                            placeholder="Select status"
                                        />
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

                            <div
                                v-if="branches.length > 0"
                                class="grid content-start gap-2"
                            >
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

                            <div class="grid content-start gap-2">
                                <Label>Supply Type *</Label>
                                <Select v-model="form.supply_type">
                                    <SelectTrigger class="w-full">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="intra"
                                            >Intra-state (CGST +
                                            SGST)</SelectItem
                                        >
                                        <SelectItem value="inter"
                                            >Inter-state (IGST)</SelectItem
                                        >
                                    </SelectContent>
                                </Select>
                                <InputError
                                    :message="form.errors.supply_type"
                                />
                            </div>

                            <div class="grid content-start gap-2">
                                <Label>Price Type *</Label>
                                <Select v-model="form.rate_tier">
                                    <SelectTrigger
                                        class="w-full"
                                        data-test="rate-tier"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="(label, tier) in rateTiers"
                                            :key="tier"
                                            :value="tier"
                                        >
                                            {{ tier }} — {{ label }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError :message="form.errors.rate_tier" />
                            </div>
                        </div>
                    </section>
                </CardContent>
            </Card>

            <!-- Line Items -->
            <Card>
                <CardHeader
                    class="flex flex-row items-center justify-between gap-4"
                >
                    <div class="space-y-1">
                        <CardTitle>Items</CardTitle>
                        <p class="text-sm text-muted-foreground">
                            Priced at {{ form.rate_tier }} —
                            {{ rateTiers[form.rate_tier] }}
                        </p>
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="addItem"
                    >
                        <Plus class="h-4 w-4" /> Add item
                    </Button>
                </CardHeader>
                <CardContent class="px-0">
                    <!-- Fixed columns total 53rem, leaving Description 13rem at the minimum width. -->
                    <Table class="min-w-[66rem] table-fixed">
                        <TableHeader>
                            <TableRow class="hover:bg-transparent">
                                <TableHead class="w-10 pl-6">#</TableHead>
                                <TableHead class="w-56">Product</TableHead>
                                <TableHead>Description *</TableHead>
                                <TableHead class="w-24">HSN</TableHead>
                                <TableHead class="w-20 text-right"
                                    >Qty</TableHead
                                >
                                <TableHead class="w-28 text-right"
                                    >Unit Price</TableHead
                                >
                                <TableHead class="w-24">GST</TableHead>
                                <TableHead class="w-36 text-right"
                                    >Amount</TableHead
                                >
                                <TableHead class="w-14 pr-6">
                                    <span class="sr-only">Remove</span>
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow
                                v-for="(item, index) in form.items"
                                :key="index"
                                class="hover:bg-transparent [&>td]:align-top"
                            >
                                <TableCell
                                    class="pl-6 leading-9 text-muted-foreground tabular-nums"
                                >
                                    {{ index + 1 }}
                                </TableCell>
                                <TableCell>
                                    <Combobox
                                        v-model="item.product_id"
                                        placeholder="Select product"
                                        content-class="w-[28rem] max-w-[90vw]"
                                        :options="productOptions"
                                        @update:model-value="
                                            onProductSelect(index)
                                        "
                                    />
                                </TableCell>
                                <TableCell class="whitespace-normal">
                                    <Input
                                        v-model="item.description"
                                        placeholder="Item description"
                                    />
                                    <InputError
                                        class="mt-1"
                                        :message="
                                            form.errors[
                                                `items.${index}.description`
                                            ]
                                        "
                                    />
                                </TableCell>
                                <TableCell>
                                    <Input
                                        v-model="item.hsn_code"
                                        placeholder="Code"
                                    />
                                </TableCell>
                                <TableCell>
                                    <Input
                                        v-model="item.quantity"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        class="text-right tabular-nums"
                                    />
                                </TableCell>
                                <TableCell class="whitespace-normal">
                                    <Input
                                        v-model="item.unit_price"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        class="text-right tabular-nums"
                                        :data-test="`unit-price-${index}`"
                                    />
                                    <p
                                        v-if="!hasTierRate(item.product_id)"
                                        class="mt-1 text-right text-xs text-amber-600"
                                    >
                                        No {{ form.rate_tier }} rate here
                                    </p>
                                </TableCell>
                                <TableCell>
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
                                </TableCell>
                                <TableCell
                                    class="text-right whitespace-normal tabular-nums"
                                >
                                    <div class="leading-9 font-medium">
                                        {{ amount(lineGrandTotal(item)) }}
                                    </div>
                                    <div class="text-xs text-muted-foreground">
                                        {{ amount(lineTotal(item)) }} +
                                        {{ amount(lineTax(item)) }} GST
                                    </div>
                                </TableCell>
                                <TableCell class="pr-6 text-right">
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        :aria-label="`Remove item ${index + 1}`"
                                        :disabled="form.items.length === 1"
                                        @click="removeItem(index)"
                                    >
                                        <Trash2
                                            class="h-4 w-4 text-destructive"
                                        />
                                    </Button>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                    <div
                        class="flex items-center justify-between gap-4 border-t px-6 pt-4"
                    >
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            @click="addItem"
                        >
                            <Plus class="h-4 w-4" /> Add another item
                        </Button>
                        <InputError :message="form.errors.items" />
                    </div>
                </CardContent>
            </Card>

            <div class="grid gap-6 lg:grid-cols-3">
                <Card class="lg:col-span-2">
                    <CardHeader>
                        <CardTitle>Notes &amp; Terms</CardTitle>
                    </CardHeader>
                    <CardContent class="grid gap-4 sm:grid-cols-2">
                        <div class="grid content-start gap-2">
                            <Label for="notes">Notes</Label>
                            <Textarea
                                id="notes"
                                v-model="form.notes"
                                rows="6"
                                class="min-h-36"
                                placeholder="Shown on the quotation"
                            />
                        </div>
                        <div class="grid content-start gap-2">
                            <Label for="terms">Terms &amp; Conditions</Label>
                            <Textarea
                                id="terms"
                                v-model="form.terms"
                                rows="6"
                                class="min-h-36"
                                placeholder="Payment, delivery, validity…"
                            />
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Summary</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-3 text-sm">
                        <div class="flex items-center justify-between gap-4">
                            <span class="text-muted-foreground">Subtotal</span>
                            <span class="font-medium tabular-nums">{{
                                money(subtotal)
                            }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-4">
                            <Label
                                for="discount"
                                class="font-normal text-muted-foreground"
                                >Discount</Label
                            >
                            <Input
                                id="discount"
                                v-model="form.discount"
                                type="number"
                                min="0"
                                step="0.01"
                                class="h-8 w-32 text-right tabular-nums"
                            />
                        </div>
                        <InputError
                            :message="form.errors.discount"
                            class="text-right"
                        />
                        <div
                            v-if="discountValue > 0"
                            class="flex items-center justify-between gap-4"
                        >
                            <span class="text-muted-foreground"
                                >Taxable value</span
                            >
                            <span class="font-medium tabular-nums">{{
                                money(taxable)
                            }}</span>
                        </div>
                        <div
                            v-if="isInterState"
                            class="flex items-center justify-between gap-4"
                        >
                            <span class="text-muted-foreground">IGST</span>
                            <span class="font-medium tabular-nums">{{
                                money(igstAmount)
                            }}</span>
                        </div>
                        <template v-else>
                            <div
                                class="flex items-center justify-between gap-4"
                            >
                                <span class="text-muted-foreground">CGST</span>
                                <span class="font-medium tabular-nums">{{
                                    money(cgstAmount)
                                }}</span>
                            </div>
                            <div
                                class="flex items-center justify-between gap-4"
                            >
                                <span class="text-muted-foreground">SGST</span>
                                <span class="font-medium tabular-nums">{{
                                    money(sgstAmount)
                                }}</span>
                            </div>
                        </template>
                        <div class="flex items-center justify-between gap-4">
                            <span class="text-muted-foreground">Total Tax</span>
                            <span class="font-medium tabular-nums">{{
                                money(taxAmount)
                            }}</span>
                        </div>
                        <div
                            class="flex items-center justify-between gap-4 border-t pt-3 text-base font-semibold"
                        >
                            <span>Total</span>
                            <span class="tabular-nums">{{ money(total) }}</span>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <div
                class="flex flex-col-reverse gap-2 border-t pt-6 sm:flex-row sm:justify-end"
            >
                <Button variant="outline" as-child>
                    <Link :href="cancelHref">Cancel</Link>
                </Button>
                <Button type="submit" :disabled="form.processing">
                    {{ quotation ? 'Save Changes' : 'Create Quotation' }}
                </Button>
            </div>
        </div>
    </form>
</template>
