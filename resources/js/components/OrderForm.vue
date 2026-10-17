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
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import {
    formatMoney as money,
    orderRoutes,
    orderStatusLabel,
} from '@/lib/orders';
import type {
    Branch,
    OrderDetail,
    OrderPartyOption,
    OrderProductOption,
    OrderType,
} from '@/types';

const props = defineProps<{
    orderType: OrderType;
    order?: OrderDetail;
    customers: OrderPartyOption[];
    suppliers: OrderPartyOption[];
    /** Values carried in from elsewhere, e.g. the customer page's "New order". */
    prefill?: { customer_id: number | null };
    products: OrderProductOption[];
    statuses: string[];
    branches: Branch[];
    defaultBranchId: number | null;
}>();

const routes = orderRoutes(props.orderType);
const isSale = props.orderType === 'sale';
const emptyItem = () => ({
    product_id: undefined as string | undefined,
    description: '',
    hsn_code: '',
    quantity: '1',
    unit_price: '0',
    tax_percentage: '0',
});
const asId = (value?: number | null) => (value ? String(value) : undefined);

const form = useForm({
    customer_id: asId(props.order?.customer_id ?? props.prefill?.customer_id),
    supplier_id: asId(props.order?.supplier_id),
    gstin:
        props.order?.gstin ??
        props.customers.find(
            (customer) => customer.id === props.prefill?.customer_id,
        )?.gst_number ??
        '',
    supply_type: props.order?.supply_type ?? 'intra',
    order_date:
        props.order?.order_date ?? new Date().toISOString().slice(0, 10),
    expected_date: props.order?.expected_date ?? '',
    status: props.order?.status ?? 'draft',
    branch_id: asId(props.order?.branch_id),
    discount: props.order?.discount ?? '0',
    notes: props.order?.notes ?? '',
    terms: props.order?.terms ?? '',
    items: props.order?.items.length
        ? props.order.items.map((item) => ({
              product_id: asId(item.product_id),
              description: item.description,
              hsn_code: item.hsn_code ?? '',
              quantity: item.quantity,
              unit_price: item.unit_price,
              tax_percentage: String(Number(item.tax_percentage)),
          }))
        : [emptyItem()],
});

const partyOptions = computed(() =>
    (isSale ? props.customers : props.suppliers).map((party) => ({
        value: String(party.id),
        label: party.name,
    })),
);
/** Carry the chosen customer's or supplier's GSTIN onto the order. */
const selectParty = (id: string | undefined) => {
    const party = (isSale ? props.customers : props.suppliers).find(
        (option) => String(option.id) === id,
    );

    form.gstin = party?.gst_number ?? '';
};
const productOptions = computed(() =>
    props.products.map((product) => ({
        value: String(product.id),
        label: product.code
            ? `${product.name} (${product.code})`
            : product.name,
    })),
);
const productFor = (id: string | undefined) =>
    props.products.find((product) => String(product.id) === id);
const addItem = () => form.items.push(emptyItem());
const removeItem = (index: number) => {
    if (form.items.length > 1) {
        form.items.splice(index, 1);
    }
};
const selectProduct = (index: number) => {
    const item = form.items[index];
    const product = productFor(item.product_id);

    if (!product) {
        return;
    }

    item.description ||= product.name;
    item.hsn_code ||= product.hsn_code ?? '';
    item.tax_percentage = String(Number(product.tax_percentage));
    item.unit_price = product.taxable_amount ?? product.price ?? '0';
};
const lineTotal = (item: { quantity: string; unit_price: string }) =>
    (Number(item.quantity) || 0) * (Number(item.unit_price) || 0);
const subtotal = computed(() =>
    form.items.reduce((total, item) => total + lineTotal(item), 0),
);
const discount = computed(() =>
    Math.min(Number(form.discount) || 0, subtotal.value),
);
const tax = computed(() =>
    form.items.reduce((total, item) => {
        const base = lineTotal(item);
        const discounted = Math.max(
            base -
                (subtotal.value ? discount.value * (base / subtotal.value) : 0),
            0,
        );

        return total + (discounted * (Number(item.tax_percentage) || 0)) / 100;
    }, 0),
);
const total = computed(() => subtotal.value - discount.value + tax.value);
const submit = () => {
    if (props.order) {
        form.put(routes.update.url(props.order.id), { preserveScroll: true });
    } else {
        form.post(routes.store.url(), { preserveScroll: true });
    }
};
</script>

<template>
    <form class="space-y-6" @submit.prevent="submit">
        <Card>
            <CardHeader>
                <CardTitle>Order details</CardTitle>
            </CardHeader>
            <CardContent class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div v-if="isSale" class="grid gap-2">
                    <Label>Customer *</Label>
                    <Combobox
                        v-model="form.customer_id"
                        :options="partyOptions"
                        placeholder="Select customer"
                        @update:model-value="selectParty"
                    />
                    <InputError :message="form.errors.customer_id" />
                </div>
                <div v-else class="grid gap-2">
                    <Label>Supplier *</Label>
                    <Combobox
                        v-model="form.supplier_id"
                        :options="partyOptions"
                        placeholder="Select supplier"
                        @update:model-value="selectParty"
                    />
                    <InputError :message="form.errors.supplier_id" />
                </div>
                <div class="grid gap-2">
                    <Label for="gstin">{{
                        isSale ? 'Customer GSTIN' : 'Supplier GSTIN'
                    }}</Label>
                    <Input id="gstin" v-model="form.gstin" />
                    <InputError :message="form.errors.gstin" />
                </div>
                <div class="grid gap-2">
                    <Label for="order_date">Order date *</Label>
                    <Input
                        id="order_date"
                        v-model="form.order_date"
                        type="date"
                    />
                    <InputError :message="form.errors.order_date" />
                </div>
                <div class="grid gap-2">
                    <Label for="expected_date">Expected date</Label>
                    <Input
                        id="expected_date"
                        v-model="form.expected_date"
                        type="date"
                    />
                </div>
                <div class="grid gap-2">
                    <Label>Status</Label>
                    <Select v-model="form.status">
                        <SelectTrigger>
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="status in statuses"
                                :key="status"
                                :value="status"
                                class="capitalize"
                                >{{ orderStatusLabel(status) }}</SelectItem
                            >
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.status" />
                </div>
                <div class="grid gap-2">
                    <Label>Supply type</Label>
                    <Select v-model="form.supply_type">
                        <SelectTrigger>
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
                </div>
                <div v-if="branches.length" class="grid gap-2">
                    <Label>Branch *</Label>
                    <Select v-model="form.branch_id">
                        <SelectTrigger>
                            <SelectValue placeholder="Select branch" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="branch in branches"
                                :key="branch.id"
                                :value="String(branch.id)"
                                >{{ branch.name }}</SelectItem
                            >
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.branch_id" />
                </div>
            </CardContent>
        </Card>
        <Card>
            <CardHeader
                class="flex flex-row items-center justify-between gap-4"
            >
                <CardTitle>Items</CardTitle>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    @click="addItem"
                >
                    <Plus class="size-4" /> Add item</Button
                >
            </CardHeader>
            <CardContent class="overflow-x-auto px-0">
                <Table class="min-w-[58rem]">
                    <TableHeader>
                        <TableRow>
                            <TableHead class="pl-6">Product</TableHead>
                            <TableHead>Description *</TableHead>
                            <TableHead>HSN</TableHead>
                            <TableHead class="text-right">Qty</TableHead>
                            <TableHead class="text-right">Unit price</TableHead>
                            <TableHead class="text-right">GST %</TableHead>
                            <TableHead class="text-right">Amount</TableHead>
                            <TableHead class="pr-6" />
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="(item, index) in form.items"
                            :key="index"
                            class="[&>td]:align-top"
                        >
                            <TableCell class="pl-6">
                                <Combobox
                                    v-model="item.product_id"
                                    :options="productOptions"
                                    placeholder="Select product"
                                    @update:model-value="selectProduct(index)"
                                />
                            </TableCell>
                            <TableCell>
                                <Input v-model="item.description" />
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
                                <Input v-model="item.hsn_code" />
                            </TableCell>
                            <TableCell>
                                <Input
                                    v-model="item.quantity"
                                    type="number"
                                    min="0.01"
                                    step="0.01"
                                    class="text-right"
                                />
                            </TableCell>
                            <TableCell>
                                <Input
                                    v-model="item.unit_price"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    class="text-right"
                                />
                            </TableCell>
                            <TableCell>
                                <Input
                                    v-model="item.tax_percentage"
                                    type="number"
                                    min="0"
                                    max="100"
                                    step="0.01"
                                    class="text-right"
                                />
                            </TableCell>
                            <TableCell class="text-right tabular-nums">{{
                                money(lineTotal(item))
                            }}</TableCell>
                            <TableCell class="pr-6 text-right">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    :disabled="form.items.length === 1"
                                    @click="removeItem(index)"
                                >
                                    <Trash2 class="size-4" />
                                    <span class="sr-only">Remove item</span>
                                </Button>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </CardContent>
        </Card>
        <div class="grid gap-6 lg:grid-cols-[1fr_22rem]">
            <Card>
                <CardHeader>
                    <CardTitle>Notes and terms</CardTitle>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="grid gap-2">
                        <Label for="notes">Notes</Label>
                        <Textarea id="notes" v-model="form.notes" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="terms">Terms</Label>
                        <Textarea id="terms" v-model="form.terms" />
                    </div>
                </CardContent>
            </Card>
            <Card>
                <CardHeader>
                    <CardTitle>Totals</CardTitle>
                </CardHeader>
                <CardContent class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <span>Subtotal</span>
                        <span>{{ money(subtotal) }}</span>
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
                    </div>
                    <div class="flex justify-between">
                        <span>GST</span>
                        <span>{{ money(tax) }}</span>
                    </div>
                    <div
                        class="flex justify-between border-t pt-3 text-base font-semibold"
                    >
                        <span>Total</span>
                        <span>{{ money(total) }}</span>
                    </div>
                </CardContent>
            </Card>
        </div>
        <div class="flex justify-end">
            <Button type="submit" :disabled="form.processing">{{
                form.processing
                    ? 'Saving…'
                    : order
                      ? 'Update order'
                      : 'Create order'
            }}</Button>
        </div>
    </form>
</template>
