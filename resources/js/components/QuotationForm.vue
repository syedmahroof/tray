<script setup lang="ts">
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { Plus, Trash2 } from '@lucide/vue';
import { computed, reactive, ref, watch } from 'vue';
import Combobox from '@/components/Combobox.vue';
import InputError from '@/components/InputError.vue';
import ProductCombobox from '@/components/ProductCombobox.vue';
import QuickCustomerDialog from '@/components/QuickCustomerDialog.vue';
import type { QuickCustomer } from '@/components/QuickCustomerDialog.vue';
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
import { basisFor, impliedFreight, usesMrp } from '@/lib/products';
import type { PriceBasis } from '@/lib/products';
import { index, show, store, update } from '@/routes/quotations';
import type {
    Branch,
    NamedOption,
    QuotationDetail,
    QuotationLineRateTier,
    QuotationProductOption,
    RateBasis,
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
    lineRateTiers: Record<QuotationLineRateTier, string>;
    gstSlabs: Record<string, number>;
    branches: Branch[];
    defaultBranchId?: number | null;
    defaults?: (QuotationDefaults & { customer_id?: number | null }) | null;
}>();

// Distinct GST rates for the per-line tax select box (e.g. 0, 5, 12, 18, 28).
const gstRateOptions = computed(() =>
    [...new Set(Object.values(props.gstSlabs))].sort((a, b) => a - b),
);

const emptyItem = (rateTier: QuotationLineRateTier = 'SR') => ({
    product_id: undefined as string | undefined,
    description: '',
    hsn_code: '',
    quantity: '1',
    rate_tier: rateTier,
    /** What the line's % is stated against: off MRP, or on cost. */
    price_basis: '' as RateBasis | '',
    /** The MRP, or the cost plus any freight, the % applies to. */
    list_price: '',
    price_percent: '',
    /** The line's own price, before any agent commission is spread over it. */
    unit_price: '0',
    tax_percentage: '0',
});

type QuotationLine = ReturnType<typeof emptyItem>;

/** A stored decimal as the form shows it: 36, not 36.00; blank when missing. */
const plainNumber = (value?: string | null) =>
    value === null || value === undefined || value === ''
        ? ''
        : String(Number(value));

const roundTo2 = (value: number) => Math.round(value * 100) / 100;

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
    agent_commission: plainNumber(props.quotation?.agent_commission) || '0',
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
                  rate_tier: (item.rate_tier ??
                      props.quotation?.rate_tier ??
                      'SR') as QuotationLineRateTier,
                  price_basis: (item.price_basis ?? '') as RateBasis | '',
                  list_price: item.list_price ?? '',
                  price_percent: plainNumber(item.price_percent),
                  unit_price: item.base_price ?? item.unit_price,
                  tax_percentage: String(Number(item.tax_percentage ?? 0)),
              }))
            : [emptyItem((props.quotation?.rate_tier ?? 'SR') as RateTier)],
});

/** Customers created on the spot from this form, e.g. one-time parties. */
const addedCustomers = ref<QuickCustomer[]>([]);

const allCustomers = computed(() => [
    ...props.customers,
    ...addedCustomers.value.map((customer) => ({
        ...customer,
        rate_tier: customer.rate_tier as RateTier | null,
    })),
]);

const customerOptions = computed(() =>
    allCustomers.value.map((c) => ({ value: String(c.id), label: c.name })),
);

const canCreateCustomer = computed(() =>
    usePage().props.auth.permissions.includes('customers.create'),
);
const isCreatingCustomer = ref(false);
const newCustomerName = ref('');

const startCustomerCreate = (term: string) => {
    newCustomerName.value = term;
    isCreatingCustomer.value = true;
};

/** Select the party just created and carry its GSTIN onto the quotation. */
const onCustomerCreated = (customer: QuickCustomer) => {
    addedCustomers.value.push(customer);
    form.customer_id = String(customer.id);

    if (customer.gst_number && !form.gstin) {
        form.gstin = customer.gst_number;
    }

    // A branch picked for the party is the natural one for its quotation too.
    if (!form.branch_id && customer.branch_id && props.branches.length > 0) {
        form.branch_id = String(customer.branch_id);
    }
};
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

const addItem = () => form.items.push(emptyItem(form.rate_tier));
const removeItem = (index: number) => {
    if (form.items.length > 1) {
        form.items.splice(index, 1);
    }
};

/**
 * Every product the form has seen: the ones the quotation already uses, plus
 * each one picked from a search, so lines can be priced without a refetch.
 */
const knownProducts = reactive(
    new Map(props.products.map((product) => [String(product.id), product])),
);

const productFor = (productId: string | undefined) =>
    productId ? knownProducts.get(productId) : undefined;

/** The branch whose rates price the lines: the chosen one, else the user's own. */
const pricingBranchId = computed(
    () => form.branch_id ?? idString(props.defaultBranchId),
);

const branchRates = (product: QuotationProductOption) => {
    const branchId = pricingBranchId.value;

    return branchId ? (product.rates[Number(branchId)] ?? null) : null;
};

/** The product's ex-tax rate for a tier in the pricing branch. */
const tierRate = (product: QuotationProductOption, tier: RateTier) =>
    branchRates(product)?.[tier] ?? null;

/**
 * What a product's tier % is worked out from in the pricing branch: its MRP,
 * or its cost plus the freight its saved rates imply. Null when it has neither.
 */
const productBasis = (product: QuotationProductOption) => {
    const row = branchRates(product);

    if (!row) {
        return null;
    }

    const basis: PriceBasis = {
        mrp: row.mrp,
        cost: row.cost,
        rate_basis: row.rate_basis,
        freight: impliedFreight(row),
    };
    const listPrice = basisFor(basis);

    if (!listPrice) {
        return null;
    }

    return {
        priceBasis: (usesMrp(basis) ? 'mrp' : 'cost') as RateBasis,
        listPrice,
    };
};

const isCustom = (item: QuotationLine) => item.rate_tier === 'custom';

const listPriceOf = (item: QuotationLine) => parseFloat(item.list_price) || 0;

/** A line has an MRP or cost for its % to apply to. */
const hasListPrice = (item: QuotationLine) =>
    item.price_basis !== '' && listPriceOf(item) > 0;

/** The % that turns the line's list price into a price, or null below zero. */
const percentOf = (item: QuotationLine, price: number) => {
    const listPrice = listPriceOf(item);
    const percentage =
        item.price_basis === 'cost'
            ? (price / listPrice - 1) * 100
            : (1 - price / listPrice) * 100;

    return percentage < 0 ? null : roundTo2(percentage);
};

/** Work a line's % out from its unit price, for every line with a list price. */
const percentFromPrice = (item: QuotationLine) => {
    if (!hasListPrice(item)) {
        return;
    }

    const percentage = percentOf(item, parseFloat(item.unit_price) || 0);

    item.price_percent = percentage === null ? '' : String(percentage);
};

/** Work a custom line's unit price out from its list price and %. */
const priceFromPercent = (item: QuotationLine) => {
    if (!hasListPrice(item)) {
        return;
    }

    const percentage = parseFloat(item.price_percent) || 0;
    const factor =
        item.price_basis === 'cost'
            ? 1 + percentage / 100
            : 1 - percentage / 100;

    item.unit_price = String(Math.max(0, roundTo2(listPriceOf(item) * factor)));
};

/**
 * The % a custom line starts at: what the quotation's price type (the
 * customer's own, when they have one) already gives this product.
 */
const defaultPercent = (
    item: QuotationLine,
    product: QuotationProductOption,
) => {
    const rate = tierRate(product, form.rate_tier);

    return rate === null ? '0' : String(percentOf(item, Number(rate)) ?? 0);
};

/**
 * Price a line from its own price type. A tier line takes the branch rate,
 * keeping its price when there is none. A custom line takes the MRP or cost
 * and, when asked to (or it has none yet), the customer's default %.
 */
const priceLine = (item: QuotationLine, resetPercent: boolean) => {
    const product = productFor(item.product_id);
    const basis = product ? productBasis(product) : null;

    item.price_basis = basis?.priceBasis ?? '';
    item.list_price = basis ? String(basis.listPrice) : '';

    if (!isCustom(item)) {
        const rate = product
            ? tierRate(product, item.rate_tier as RateTier)
            : null;

        if (rate !== null) {
            item.unit_price = rate;
        }

        item.price_percent = '';
        percentFromPrice(item);

        return;
    }

    if (!product || !basis) {
        // Nothing to work a % from: the price is entered by hand.
        item.price_percent = '';

        return;
    }

    if (resetPercent || item.price_percent === '') {
        item.price_percent = defaultPercent(item, product);
    }

    priceFromPercent(item);
};

const onLineTierChange = (item: QuotationLine, tier: unknown) => {
    item.rate_tier = tier as QuotationLineRateTier;
    priceLine(item, true);
};

const hasLineRate = (item: QuotationLine) => {
    const product = productFor(item.product_id);

    if (!product) {
        return true;
    }

    return isCustom(item)
        ? productBasis(product) !== null
        : tierRate(product, item.rate_tier as RateTier) !== null;
};

/** The cost and freight a cost-based line's % is on, as the line reads them. */
const costLabel = (item: QuotationLine) => {
    const product = productFor(item.product_id);
    const cost = product ? Number(branchRates(product)?.cost ?? 0) : 0;
    const freight = roundTo2(listPriceOf(item) - cost);

    return cost > 0 && freight > 0
        ? `cost ${amount(cost)} + ${amount(freight)} freight`
        : `cost ${amount(listPriceOf(item))}`;
};

/**
 * Picking a product fills the line from it. The description and HSN code follow
 * the product when they are blank or still the previous product's, so switching
 * products updates them while custom wording typed on the line is kept.
 */
const onProductSelect = (index: number, picked: QuotationProductOption) => {
    const item = form.items[index];
    const previous = productFor(item.product_id);
    const productId = String(picked.id);

    knownProducts.set(productId, picked);

    const product = productFor(productId);

    item.product_id = productId;

    if (product) {
        if (!item.description || item.description === previous?.name) {
            item.description = product.name;
        }

        // The line's unit price is the taxable (pre-GST) value: its price type,
        // else the product's own taxable amount or price.
        const fallback = product.taxable_amount ?? product.price;

        if (fallback) {
            item.unit_price = fallback;
        }

        priceLine(item, true);

        if (
            product.hsn_code &&
            (!item.hsn_code || item.hsn_code === (previous?.hsn_code ?? ''))
        ) {
            item.hsn_code = product.hsn_code;
        }

        item.tax_percentage = String(Number(product.tax_percentage ?? 0));
    }
};

/**
 * The quotation's price type is the default for its lines: changing it moves
 * every tier line onto it. Custom lines keep the discount bargained on them.
 */
watch(
    () => form.rate_tier,
    (tier) => {
        for (const item of form.items) {
            if (!isCustom(item)) {
                item.rate_tier = tier;
                priceLine(item, false);
            }
        }
    },
);

/** Another branch has its own rates and MRPs, so every line is re-priced. */
watch(pricingBranchId, () => {
    for (const item of form.items) {
        priceLine(item, false);
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
        const tier = allCustomers.value.find(
            (customer) => String(customer.id) === customerId,
        )?.rate_tier;

        if (tier) {
            form.rate_tier = tier;
        }
    },
);

/** What the lines come to before any agent commission. */
const baseSubtotal = computed(() =>
    form.items.reduce(
        (sum, item) =>
            sum +
            (parseFloat(item.quantity) || 0) *
                (parseFloat(item.unit_price) || 0),
        0,
    ),
);

/** The agent's commission, spread over the lines in proportion to value. */
const agentCommission = computed(() =>
    baseSubtotal.value > 0
        ? Math.max(parseFloat(form.agent_commission) || 0, 0)
        : 0,
);

/** The unit price that prints: the line's own price plus its commission share. */
const printedPrice = (item: { unit_price: string }) =>
    roundTo2(
        (parseFloat(item.unit_price) || 0) *
            (1 +
                (baseSubtotal.value > 0
                    ? agentCommission.value / baseSubtotal.value
                    : 0)),
    );

const lineTotal = (item: { quantity: string; unit_price: string }) =>
    (parseFloat(item.quantity) || 0) * printedPrice(item);

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
                        <div class="space-y-1">
                            <h3
                                class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                            >
                                Link Entities
                            </h3>
                            <p class="text-sm text-muted-foreground">
                                Link at least one customer, contact, project,
                                builder, or enquiry.
                            </p>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            <div class="grid content-start gap-2">
                                <Label>Customer</Label>
                                <Combobox
                                    v-model="form.customer_id"
                                    placeholder="Select a customer (optional)"
                                    :options="customerOptions"
                                    :create-label="
                                        canCreateCustomer
                                            ? 'Add a new customer'
                                            : undefined
                                    "
                                    @create="startCustomerCreate"
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
                                <Label for="gstin">Buyer GSTIN</Label>
                                <Input
                                    id="gstin"
                                    v-model="form.gstin"
                                    placeholder="e.g. 29ABCDE1234F1Z5"
                                />
                                <InputError :message="form.errors.gstin" />
                            </div>

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
                            New lines are priced at {{ form.rate_tier }} —
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
                    <!-- Fixed columns total 61rem, leaving Description 13rem at the minimum width. -->
                    <Table class="min-w-[74rem] table-fixed">
                        <TableHeader>
                            <TableRow class="hover:bg-transparent">
                                <TableHead class="w-10 pl-6">#</TableHead>
                                <TableHead class="w-56">Product</TableHead>
                                <TableHead>Description *</TableHead>
                                <TableHead class="w-24">HSN</TableHead>
                                <TableHead class="w-20 text-right"
                                    >Qty</TableHead
                                >
                                <TableHead class="w-32">Price Type</TableHead>
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
                                    <ProductCombobox
                                        :model-value="item.product_id"
                                        :selected-label="
                                            productFor(item.product_id)?.name
                                        "
                                        placeholder="Select product"
                                        content-class="w-[28rem] max-w-[90vw]"
                                        @select="
                                            (product) =>
                                                onProductSelect(index, product)
                                        "
                                    />
                                </TableCell>
                                <TableCell class="whitespace-normal">
                                    <Input
                                        v-model="item.description"
                                        placeholder="Item description"
                                        :data-test="`description-${index}`"
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
                                    <Select
                                        :model-value="item.rate_tier"
                                        @update:model-value="
                                            (tier) =>
                                                onLineTierChange(item, tier)
                                        "
                                    >
                                        <SelectTrigger
                                            class="w-full"
                                            :data-test="`line-tier-${index}`"
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem
                                                v-for="(
                                                    label, tier
                                                ) in lineRateTiers"
                                                :key="tier"
                                                :value="tier"
                                                :title="label"
                                                :data-test="`line-tier-option-${tier}`"
                                            >
                                                {{
                                                    tier === 'custom'
                                                        ? 'Custom'
                                                        : tier
                                                }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <template v-if="hasListPrice(item)">
                                        <div
                                            v-if="isCustom(item)"
                                            class="mt-2 flex items-center gap-1"
                                        >
                                            <span
                                                v-if="
                                                    item.price_basis === 'cost'
                                                "
                                                class="text-sm text-muted-foreground"
                                                >+</span
                                            >
                                            <Input
                                                v-model="item.price_percent"
                                                type="number"
                                                min="0"
                                                step="0.01"
                                                class="h-8 text-right tabular-nums"
                                                :aria-label="
                                                    item.price_basis === 'cost'
                                                        ? 'Markup on cost'
                                                        : 'Discount off MRP'
                                                "
                                                :data-test="`price-percent-${index}`"
                                                @update:model-value="
                                                    priceFromPercent(item)
                                                "
                                            />
                                            <span
                                                class="text-sm text-muted-foreground"
                                                >%</span
                                            >
                                        </div>
                                        <p
                                            class="mt-1 text-xs text-muted-foreground tabular-nums"
                                            :data-test="`price-basis-${index}`"
                                        >
                                            <template v-if="!isCustom(item)">
                                                {{
                                                    item.price_basis === 'cost'
                                                        ? '+'
                                                        : ''
                                                }}{{
                                                    item.price_percent || '—'
                                                }}%
                                            </template>
                                            {{
                                                item.price_basis === 'cost'
                                                    ? `on ${costLabel(item)}`
                                                    : `off MRP ${amount(listPriceOf(item))}`
                                            }}
                                        </p>
                                        <InputError
                                            class="mt-1"
                                            :message="
                                                form.errors[
                                                    `items.${index}.price_percent`
                                                ]
                                            "
                                        />
                                    </template>
                                </TableCell>
                                <TableCell class="whitespace-normal">
                                    <Input
                                        v-model="item.unit_price"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        class="text-right tabular-nums"
                                        :data-test="`unit-price-${index}`"
                                        @update:model-value="
                                            percentFromPrice(item)
                                        "
                                    />
                                    <p
                                        v-if="!hasLineRate(item)"
                                        class="mt-1 text-right text-xs text-amber-600"
                                    >
                                        {{
                                            isCustom(item)
                                                ? 'No MRP or cost — enter price'
                                                : `No ${item.rate_tier} rate here`
                                        }}
                                    </p>
                                    <p
                                        v-if="agentCommission > 0"
                                        class="mt-1 text-right text-xs text-muted-foreground tabular-nums"
                                        :data-test="`printed-price-${index}`"
                                    >
                                        {{ amount(printedPrice(item)) }} with
                                        agent
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
                        <div class="flex items-center justify-between gap-4">
                            <Label
                                for="agent_commission"
                                class="font-normal text-muted-foreground"
                            >
                                Agent commission
                            </Label>
                            <Input
                                id="agent_commission"
                                v-model="form.agent_commission"
                                type="number"
                                min="0"
                                step="0.01"
                                class="h-8 w-32 text-right tabular-nums"
                                data-test="agent-commission"
                            />
                        </div>
                        <p
                            v-if="agentCommission > 0"
                            class="text-right text-xs text-muted-foreground"
                        >
                            Spread into the item prices; not printed.
                        </p>
                        <InputError
                            :message="form.errors.agent_commission"
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

            <QuickCustomerDialog
                v-model:open="isCreatingCustomer"
                :initial-name="newCustomerName"
                :branch-id="form.branch_id"
                :branches="branches"
                @created="onCustomerCreated"
            />
        </div>
    </form>
</template>
