<script setup lang="ts">
import {
    Building2,
    FileText,
    Landmark,
    Package,
    ReceiptText,
    ScrollText,
    UserRound,
} from '@lucide/vue';
import { computed } from 'vue';
import PhoneLink from '@/components/PhoneLink.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableFooter,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDate } from '@/lib/utils';
import type {
    CompanyProfile,
    QuotationDetail,
    QuotationInvoice,
} from '@/types';

const props = defineProps<{
    quotation: QuotationDetail;
    invoice: QuotationInvoice;
    company: CompanyProfile;
    terms?: string | null;
}>();

const buyer = computed(
    () => props.quotation.customer ?? props.quotation.contact,
);

const buyerAddress = computed(() =>
    (buyer.value?.address ?? '').split(/\r\n|\r|\n/).filter(Boolean),
);

const buyerGstin = computed(
    () => props.quotation.gstin ?? props.quotation.customer?.gst_number ?? null,
);

const buyerState = computed(
    () => props.quotation.customer?.state ?? props.quotation.contact?.state,
);

/** The contact person, named separately when the quotation is on a customer. */
const attention = computed(() =>
    props.quotation.customer ? props.quotation.contact : null,
);

const termLines = computed(() =>
    (props.terms ?? props.quotation.terms ?? '')
        .split(/\r\n|\r|\n/)
        .map((line) => line.replace(/^\s*\d+[.)]\s*/, '').trim())
        .filter(Boolean),
);

const companyPhones = computed(() =>
    [props.company.phone, props.company.mobile].filter(
        (phone): phone is string => Boolean(phone),
    ),
);

/** The document's own details, as label and value pairs. */
const details = computed(() =>
    [
        ['Quotation No', props.quotation.number],
        ['Quotation Date', formatDate(props.quotation.quotation_date)],
        [
            'Valid Until',
            props.quotation.valid_until
                ? formatDate(props.quotation.valid_until)
                : '',
        ],
        [
            'Revision',
            props.quotation.version > 1
                ? `Rev. ${props.quotation.version}`
                : '',
        ],
        [
            'Enquiry Ref.',
            props.quotation.enquiry ? `#${props.quotation.enquiry.id}` : '',
        ],
        ['Project', props.quotation.project?.name ?? ''],
        ['Sent By', props.quotation.creator?.name ?? ''],
        [
            'Supply',
            props.quotation.supply_type === 'inter'
                ? 'Inter-state (IGST)'
                : 'Intra-state (CGST + SGST)',
        ],
    ].filter(([, value]) => value !== ''),
);

const money = (value: number) =>
    `₹${value.toLocaleString('en-IN', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;

/** Drops the trailing zeros a quantity carries in the database. */
const amount = (value: number) =>
    Number(value.toFixed(3)).toLocaleString('en-IN', {
        maximumFractionDigits: 3,
    });

const percent = (value: number) => `${Number(value.toFixed(2))}%`;
</script>

<template>
    <div class="flex flex-col gap-6">
        <!-- Who the quotation is from, who it is for, and its own details -->
        <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
            <Card class="gap-0">
                <CardHeader class="flex flex-row items-center gap-2 border-b">
                    <Building2 class="h-4 w-4 text-muted-foreground" />
                    <CardTitle class="text-sm font-semibold">From</CardTitle>
                </CardHeader>
                <CardContent class="space-y-1 pt-4 text-sm">
                    <p class="font-semibold">{{ company.name ?? '—' }}</p>
                    <p
                        v-for="line in company.address ?? []"
                        :key="line"
                        class="text-muted-foreground"
                    >
                        {{ line }}
                    </p>
                    <dl
                        class="grid grid-cols-[5.5rem_1fr] gap-x-2 gap-y-1 pt-2"
                    >
                        <template v-if="companyPhones.length">
                            <dt class="text-muted-foreground">Phone</dt>
                            <dd class="flex flex-wrap gap-x-3">
                                <PhoneLink
                                    v-for="phone in companyPhones"
                                    :key="phone"
                                    :phone="phone"
                                    :icon="false"
                                />
                            </dd>
                        </template>
                        <template v-if="company.email">
                            <dt class="text-muted-foreground">Email</dt>
                            <dd class="break-all">{{ company.email }}</dd>
                        </template>
                        <template v-if="company.website">
                            <dt class="text-muted-foreground">Website</dt>
                            <dd class="break-all">{{ company.website }}</dd>
                        </template>
                        <template v-if="company.gstin">
                            <dt class="text-muted-foreground">GSTIN</dt>
                            <dd class="font-medium">{{ company.gstin }}</dd>
                        </template>
                        <template v-if="company.state?.name">
                            <dt class="text-muted-foreground">State</dt>
                            <dd>
                                {{ company.state.name
                                }}<template v-if="company.state.code">
                                    ({{ company.state.code }})</template
                                >
                            </dd>
                        </template>
                    </dl>
                </CardContent>
            </Card>

            <Card class="gap-0">
                <CardHeader class="flex flex-row items-center gap-2 border-b">
                    <UserRound class="h-4 w-4 text-muted-foreground" />
                    <CardTitle class="text-sm font-semibold">Bill To</CardTitle>
                </CardHeader>
                <CardContent class="space-y-1 pt-4 text-sm">
                    <p class="font-semibold">{{ buyer?.name ?? '—' }}</p>
                    <p
                        v-for="line in buyerAddress"
                        :key="line"
                        class="text-muted-foreground"
                    >
                        {{ line }}
                    </p>
                    <dl
                        class="grid grid-cols-[5.5rem_1fr] gap-x-2 gap-y-1 pt-2"
                    >
                        <template v-if="attention">
                            <dt class="text-muted-foreground">Attn.</dt>
                            <dd>{{ attention.name }}</dd>
                        </template>
                        <template v-if="attention?.phone ?? buyer?.phone">
                            <dt class="text-muted-foreground">Phone</dt>
                            <dd>
                                <PhoneLink
                                    :phone="attention?.phone ?? buyer?.phone"
                                    :icon="false"
                                />
                            </dd>
                        </template>
                        <template v-if="attention?.email ?? buyer?.email">
                            <dt class="text-muted-foreground">Email</dt>
                            <dd class="break-all">
                                {{ attention?.email ?? buyer?.email }}
                            </dd>
                        </template>
                        <template v-if="buyerGstin">
                            <dt class="text-muted-foreground">GSTIN</dt>
                            <dd class="font-medium">{{ buyerGstin }}</dd>
                        </template>
                        <template v-if="buyerState">
                            <dt class="text-muted-foreground">State</dt>
                            <dd>
                                {{ buyerState.name
                                }}<template v-if="buyerState.code">
                                    ({{ buyerState.code }})</template
                                >
                            </dd>
                        </template>
                    </dl>
                </CardContent>
            </Card>

            <Card class="gap-0 md:col-span-2 xl:col-span-1">
                <CardHeader class="flex flex-row items-center gap-2 border-b">
                    <FileText class="h-4 w-4 text-muted-foreground" />
                    <CardTitle class="text-sm font-semibold">
                        Quotation Details
                    </CardTitle>
                </CardHeader>
                <CardContent class="pt-4 text-sm">
                    <dl class="grid grid-cols-[7rem_1fr] gap-x-2 gap-y-2">
                        <template
                            v-for="[label, value] in details"
                            :key="label"
                        >
                            <dt class="text-muted-foreground">{{ label }}</dt>
                            <dd class="font-medium">{{ value }}</dd>
                        </template>
                    </dl>
                </CardContent>
            </Card>
        </div>

        <!-- The lines and the totals they come to -->
        <Card class="gap-0">
            <CardHeader class="flex flex-row items-center gap-2 border-b">
                <Package class="h-4 w-4 text-muted-foreground" />
                <CardTitle class="text-sm font-semibold">
                    Items
                    <span class="font-normal text-muted-foreground">
                        ({{ invoice.lines.length }})
                    </span>
                </CardTitle>
            </CardHeader>
            <CardContent class="px-0">
                <Table class="min-w-[48rem]">
                    <TableHeader>
                        <TableRow class="bg-muted/50 hover:bg-muted/50">
                            <TableHead class="w-12 pl-6 text-center"
                                >#</TableHead
                            >
                            <TableHead>Product</TableHead>
                            <TableHead class="w-24">HSN</TableHead>
                            <TableHead class="w-24 text-right">Qty</TableHead>
                            <TableHead class="w-28 text-right">
                                List Price
                            </TableHead>
                            <TableHead class="w-20 text-right">Disc.</TableHead>
                            <TableHead class="w-28 text-right">
                                Net Price
                            </TableHead>
                            <TableHead class="w-32 pr-6 text-right">
                                Total
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="line in invoice.lines" :key="line.sl">
                            <TableCell
                                class="pl-6 text-center align-top text-muted-foreground"
                            >
                                {{ line.sl }}
                            </TableCell>
                            <TableCell class="align-top whitespace-normal">
                                <p class="font-medium">
                                    {{ line.description }}
                                </p>
                                <p
                                    v-if="line.detail"
                                    class="text-xs whitespace-pre-line text-muted-foreground"
                                >
                                    {{ line.detail }}
                                </p>
                            </TableCell>
                            <TableCell class="align-top text-muted-foreground">
                                {{ line.hsn ?? '—' }}
                            </TableCell>
                            <TableCell
                                class="text-right align-top tabular-nums"
                            >
                                {{ amount(line.quantity) }}
                                <span
                                    class="text-xs text-muted-foreground uppercase"
                                >
                                    {{ line.unit }}
                                </span>
                            </TableCell>
                            <TableCell
                                class="text-right align-top tabular-nums"
                            >
                                {{ money(line.rate) }}
                            </TableCell>
                            <TableCell
                                class="text-right align-top tabular-nums"
                            >
                                {{ percent(line.discount_percent) }}
                            </TableCell>
                            <TableCell
                                class="text-right align-top tabular-nums"
                            >
                                {{ money(line.net_rate) }}
                            </TableCell>
                            <TableCell
                                class="pr-6 text-right align-top font-medium tabular-nums"
                            >
                                {{ money(line.net_amount) }}
                            </TableCell>
                        </TableRow>
                    </TableBody>
                    <TableFooter>
                        <TableRow class="hover:bg-muted/50">
                            <TableCell colspan="3" class="pl-6 font-medium">
                                Total Quantity
                            </TableCell>
                            <TableCell
                                class="text-right font-medium tabular-nums"
                            >
                                {{ amount(invoice.total_quantity) }}
                                <span
                                    v-if="invoice.total_unit"
                                    class="text-xs text-muted-foreground uppercase"
                                >
                                    {{ invoice.total_unit }}
                                </span>
                            </TableCell>
                            <TableCell colspan="4" />
                        </TableRow>
                    </TableFooter>
                </Table>

                <div
                    class="flex flex-col gap-6 border-t px-6 pt-6 md:flex-row md:items-end md:justify-between"
                >
                    <div class="space-y-1 text-sm md:max-w-md">
                        <p class="text-xs text-muted-foreground">
                            Amount in words
                        </p>
                        <p class="font-medium">{{ invoice.amount_in_words }}</p>
                        <p class="pt-2 text-xs text-muted-foreground">
                            Tax in words
                        </p>
                        <p class="font-medium">{{ invoice.tax_in_words }}</p>
                    </div>

                    <dl class="w-full space-y-2 text-sm md:w-80">
                        <div class="flex justify-between gap-4">
                            <dt class="text-muted-foreground">Sub-Total</dt>
                            <dd class="tabular-nums">
                                {{ money(invoice.subtotal) }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-muted-foreground">
                                Discount
                                <template v-if="invoice.discount_percent > 0">
                                    ({{ percent(invoice.discount_percent) }})
                                </template>
                            </dt>
                            <dd class="tabular-nums">
                                − {{ money(invoice.discount) }}
                            </dd>
                        </div>
                        <div
                            class="flex justify-between gap-4 border-t pt-2 font-medium"
                        >
                            <dt>Pre-Tax Sub-Total</dt>
                            <dd class="tabular-nums">
                                {{ money(invoice.taxable_value) }}
                            </dd>
                        </div>
                        <div
                            v-for="tax in invoice.tax_lines"
                            :key="tax.label"
                            class="flex justify-between gap-4"
                        >
                            <dt class="text-muted-foreground">
                                {{ tax.label.replace(' OUT', '') }}
                            </dt>
                            <dd class="tabular-nums">
                                {{ money(tax.amount) }}
                            </dd>
                        </div>
                        <div
                            v-if="Math.abs(invoice.round_off) >= 0.01"
                            class="flex justify-between gap-4"
                        >
                            <dt class="text-muted-foreground">Round Off</dt>
                            <dd class="tabular-nums">
                                {{ invoice.round_off < 0 ? '−' : '+' }}
                                {{ money(Math.abs(invoice.round_off)) }}
                            </dd>
                        </div>
                        <div
                            class="flex justify-between gap-4 rounded-md bg-muted px-3 py-2 text-base font-semibold"
                        >
                            <dt>Grand Total</dt>
                            <dd class="tabular-nums">
                                {{ money(invoice.total) }}
                            </dd>
                        </div>
                    </dl>
                </div>
            </CardContent>
        </Card>

        <div class="grid gap-6 lg:grid-cols-2">
            <!-- The tax, by HSN -->
            <Card class="gap-0">
                <CardHeader class="flex flex-row items-center gap-2 border-b">
                    <ReceiptText class="h-4 w-4 text-muted-foreground" />
                    <CardTitle class="text-sm font-semibold">
                        Tax Summary
                    </CardTitle>
                </CardHeader>
                <CardContent class="px-0">
                    <Table>
                        <TableHeader>
                            <TableRow class="bg-muted/50 hover:bg-muted/50">
                                <TableHead class="pl-6">HSN</TableHead>
                                <TableHead class="text-right"
                                    >Taxable</TableHead
                                >
                                <template v-if="invoice.inter_state">
                                    <TableHead class="text-right"
                                        >IGST</TableHead
                                    >
                                </template>
                                <template v-else>
                                    <TableHead class="text-right"
                                        >CGST</TableHead
                                    >
                                    <TableHead class="text-right"
                                        >SGST</TableHead
                                    >
                                </template>
                                <TableHead class="pr-6 text-right">
                                    Total Tax
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow
                                v-for="row in invoice.hsn_summary"
                                :key="`${row.hsn}-${row.rate}`"
                            >
                                <TableCell class="pl-6">{{
                                    row.hsn
                                }}</TableCell>
                                <TableCell class="text-right tabular-nums">
                                    {{ money(row.taxable) }}
                                </TableCell>
                                <template v-if="invoice.inter_state">
                                    <TableCell class="text-right tabular-nums">
                                        {{ money(row.igst) }}
                                        <span
                                            class="block text-xs text-muted-foreground"
                                        >
                                            @ {{ percent(row.rate) }}
                                        </span>
                                    </TableCell>
                                </template>
                                <template v-else>
                                    <TableCell class="text-right tabular-nums">
                                        {{ money(row.cgst) }}
                                        <span
                                            class="block text-xs text-muted-foreground"
                                        >
                                            @ {{ percent(row.rate / 2) }}
                                        </span>
                                    </TableCell>
                                    <TableCell class="text-right tabular-nums">
                                        {{ money(row.sgst) }}
                                        <span
                                            class="block text-xs text-muted-foreground"
                                        >
                                            @ {{ percent(row.rate / 2) }}
                                        </span>
                                    </TableCell>
                                </template>
                                <TableCell class="pr-6 text-right tabular-nums">
                                    {{ money(row.tax) }}
                                </TableCell>
                            </TableRow>
                        </TableBody>
                        <TableFooter>
                            <TableRow class="font-medium hover:bg-muted/50">
                                <TableCell class="pl-6">Total</TableCell>
                                <TableCell class="text-right tabular-nums">
                                    {{ money(invoice.taxable_value) }}
                                </TableCell>
                                <template v-if="invoice.inter_state">
                                    <TableCell class="text-right tabular-nums">
                                        {{ money(invoice.tax_total) }}
                                    </TableCell>
                                </template>
                                <template v-else>
                                    <TableCell class="text-right tabular-nums">
                                        {{ money(invoice.tax_total / 2) }}
                                    </TableCell>
                                    <TableCell class="text-right tabular-nums">
                                        {{ money(invoice.tax_total / 2) }}
                                    </TableCell>
                                </template>
                                <TableCell class="pr-6 text-right tabular-nums">
                                    {{ money(invoice.tax_total) }}
                                </TableCell>
                            </TableRow>
                        </TableFooter>
                    </Table>
                </CardContent>
            </Card>

            <!-- Where to pay -->
            <Card class="gap-0">
                <CardHeader class="flex flex-row items-center gap-2 border-b">
                    <Landmark class="h-4 w-4 text-muted-foreground" />
                    <CardTitle class="text-sm font-semibold">
                        Bank Details
                    </CardTitle>
                </CardHeader>
                <CardContent class="pt-4 text-sm">
                    <dl
                        v-if="company.bank?.name"
                        class="grid grid-cols-[8rem_1fr] gap-x-2 gap-y-2"
                    >
                        <dt class="text-muted-foreground">Account Name</dt>
                        <dd class="font-medium">{{ company.name }}</dd>
                        <dt class="text-muted-foreground">Bank</dt>
                        <dd class="font-medium">{{ company.bank.name }}</dd>
                        <template v-if="company.bank.account">
                            <dt class="text-muted-foreground">A/c No.</dt>
                            <dd class="font-medium tabular-nums">
                                {{ company.bank.account }}
                            </dd>
                        </template>
                        <template v-if="company.bank.ifsc">
                            <dt class="text-muted-foreground">IFSC</dt>
                            <dd class="font-medium">{{ company.bank.ifsc }}</dd>
                        </template>
                        <template v-if="company.bank.branch">
                            <dt class="text-muted-foreground">Branch</dt>
                            <dd class="font-medium">
                                {{ company.bank.branch }}
                            </dd>
                        </template>
                        <template v-if="company.gstin">
                            <dt class="text-muted-foreground">GST No</dt>
                            <dd class="font-medium">{{ company.gstin }}</dd>
                        </template>
                    </dl>
                    <p v-else class="text-muted-foreground">
                        No bank account on record for this branch.
                    </p>
                </CardContent>
            </Card>
        </div>

        <!-- The terms it is quoted on, and anything else to note -->
        <Card
            v-if="termLines.length || quotation.notes || company.declaration"
            class="gap-0"
        >
            <CardHeader class="flex flex-row items-center gap-2 border-b">
                <ScrollText class="h-4 w-4 text-muted-foreground" />
                <CardTitle class="text-sm font-semibold">
                    Terms &amp; Notes
                </CardTitle>
            </CardHeader>
            <CardContent class="grid gap-6 pt-4 text-sm md:grid-cols-2">
                <div v-if="termLines.length">
                    <p class="mb-2 font-medium">Terms and Conditions</p>
                    <ol
                        class="list-decimal space-y-1 pl-5 text-muted-foreground"
                    >
                        <li v-for="(term, index) in termLines" :key="index">
                            {{ term }}
                        </li>
                    </ol>
                </div>
                <div class="space-y-4">
                    <div v-if="quotation.notes">
                        <p class="mb-2 font-medium">Notes</p>
                        <p class="whitespace-pre-line text-muted-foreground">
                            {{ quotation.notes }}
                        </p>
                    </div>
                    <div v-if="company.declaration">
                        <p class="mb-2 font-medium">Declaration</p>
                        <p class="text-muted-foreground">
                            {{ company.declaration }}
                        </p>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
