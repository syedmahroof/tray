<script setup lang="ts">
import { computed } from 'vue';
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

const money = (value: number) =>
    value.toLocaleString('en-IN', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

/** Drops the trailing zeros a quantity carries in the database. */
const amount = (value: number) =>
    Number(value.toFixed(3)).toLocaleString('en-IN', {
        maximumFractionDigits: 3,
    });

const percent = (value: number) => `${Number(value.toFixed(2))}%`;

/** The document's own details, in the boxes the printed page uses. */
const boxes = computed(() => [
    [
        'Invoice No.',
        props.quotation.number,
        'Dated',
        formatDate(props.quotation.quotation_date),
    ],
    ['Delivery Note', '', 'Mode/Terms of Payment', ''],
    [
        'Reference No. & Date.',
        props.quotation.enquiry ? `${props.quotation.enquiry.id}` : '',
        'Other References',
        props.quotation.version > 1 ? `Rev. ${props.quotation.version}` : '',
    ],
    ["Buyer's Order No.", '', 'Dated', ''],
    ['Dispatch Doc No.', '', 'Delivery Note Date', ''],
    ['Dispatched through', '', 'Destination', ''],
]);
</script>

<template>
    <div
        class="overflow-hidden rounded-lg border border-foreground/25 bg-card text-card-foreground shadow-sm"
    >
        <p class="py-3 text-center text-base font-bold tracking-wide">
            {{ company.quotation_title ?? 'QUOTATION' }}
        </p>

        <!-- Seller, buyer and the document's own details -->
        <div
            class="grid border-t border-foreground/25 md:grid-cols-2 md:divide-x md:divide-foreground/25"
        >
            <div class="divide-y divide-foreground/25">
                <div class="space-y-0.5 p-3">
                    <p class="text-sm font-bold">
                        {{ company.name ?? '—' }}
                    </p>
                    <p
                        v-for="line in company.address ?? []"
                        :key="line"
                        class="text-xs text-muted-foreground"
                    >
                        {{ line }}
                    </p>
                    <p
                        v-if="company.phone || company.mobile"
                        class="text-xs text-muted-foreground"
                    >
                        PH: {{ company.phone
                        }}<template v-if="company.mobile"
                            >,Mob:{{ company.mobile }}</template
                        >
                    </p>
                    <p
                        v-if="company.gstin"
                        class="text-xs text-muted-foreground"
                    >
                        GSTIN/UIN: {{ company.gstin }}
                    </p>
                    <p
                        v-if="company.state?.name"
                        class="text-xs text-muted-foreground"
                    >
                        State Name : {{ company.state.name
                        }}<template v-if="company.state.code"
                            >, Code : {{ company.state.code }}</template
                        >
                    </p>
                    <p
                        v-if="company.email"
                        class="text-xs text-muted-foreground"
                    >
                        E-Mail : {{ company.email }}
                    </p>
                </div>

                <div class="space-y-0.5 p-3">
                    <p class="text-xs text-muted-foreground">Buyer (Bill to)</p>
                    <p class="text-sm font-bold">{{ buyer?.name ?? '—' }}</p>
                    <p
                        v-for="line in buyerAddress"
                        :key="line"
                        class="text-xs text-muted-foreground"
                    >
                        {{ line }}
                    </p>
                    <p v-if="buyer?.phone" class="pt-1 text-xs font-semibold">
                        Phone : {{ buyer.phone }}
                    </p>
                    <dl class="grid grid-cols-[7rem_1fr] pt-1 text-xs">
                        <template v-if="buyerGstin">
                            <dt class="text-muted-foreground">GSTIN/UIN</dt>
                            <dd>: {{ buyerGstin }}</dd>
                        </template>
                        <template v-if="buyerState">
                            <dt class="text-muted-foreground">State Name</dt>
                            <dd>
                                : {{ buyerState.name
                                }}<template v-if="buyerState.code"
                                    >, Code : {{ buyerState.code }}</template
                                >
                            </dd>
                        </template>
                        <template v-if="quotation.project">
                            <dt class="text-muted-foreground">Project</dt>
                            <dd>: {{ quotation.project.name }}</dd>
                        </template>
                    </dl>
                </div>
            </div>

            <div
                class="grid grid-cols-2 divide-x divide-y divide-foreground/25 border-t border-foreground/25 md:border-t-0"
            >
                <template
                    v-for="[
                        leftLabel,
                        leftValue,
                        rightLabel,
                        rightValue,
                    ] in boxes"
                    :key="leftLabel"
                >
                    <div class="p-2">
                        <p class="text-[11px] text-muted-foreground">
                            {{ leftLabel }}
                        </p>
                        <p class="min-h-4 text-xs font-semibold">
                            {{ leftValue }}
                        </p>
                    </div>
                    <div class="p-2">
                        <p class="text-[11px] text-muted-foreground">
                            {{ rightLabel }}
                        </p>
                        <p class="min-h-4 text-xs font-semibold">
                            {{ rightValue }}
                        </p>
                    </div>
                </template>
                <div class="col-span-2 p-2">
                    <p class="text-[11px] text-muted-foreground">
                        Terms of Delivery
                    </p>
                    <p v-if="quotation.valid_until" class="text-xs">
                        Valid until {{ formatDate(quotation.valid_until) }}
                    </p>
                    <p
                        v-if="quotation.terms"
                        class="text-xs whitespace-pre-line text-muted-foreground"
                    >
                        {{ quotation.terms }}
                    </p>
                </div>
            </div>
        </div>

        <!-- The lines, the tax added to them and the total -->
        <div class="overflow-x-auto border-t border-foreground/25">
            <table class="w-full min-w-[42rem] text-xs">
                <thead>
                    <tr
                        class="border-b border-foreground/25 bg-muted/60 text-center"
                    >
                        <th class="w-10 px-2 py-2 font-normal">Sl No.</th>
                        <th class="px-2 py-2 text-left font-normal">
                            Description of Goods
                        </th>
                        <th class="w-20 px-2 py-2 font-normal">HSN/SAC</th>
                        <th class="w-24 px-2 py-2 font-normal">Quantity</th>
                        <th class="w-24 px-2 py-2 font-normal">Rate</th>
                        <th class="w-14 px-2 py-2 font-normal">per</th>
                        <th class="w-28 px-2 py-2 font-normal">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="line in invoice.lines" :key="line.sl">
                        <td class="px-2 py-1 text-center align-top">
                            {{ line.sl }}
                        </td>
                        <td class="px-2 py-1 align-top">
                            {{ line.description }}
                        </td>
                        <td class="px-2 py-1 text-center align-top">
                            {{ line.hsn }}
                        </td>
                        <td class="px-2 py-1 text-right align-top tabular-nums">
                            {{ amount(line.quantity) }} {{ line.unit }}
                        </td>
                        <td class="px-2 py-1 text-right align-top tabular-nums">
                            {{ money(line.rate) }}
                        </td>
                        <td class="px-2 py-1 text-center align-top">
                            {{ line.unit }}
                        </td>
                        <td class="px-2 py-1 text-right align-top tabular-nums">
                            {{ money(line.amount) }}
                        </td>
                    </tr>

                    <tr>
                        <td colspan="6"></td>
                        <td
                            class="border-t border-foreground/40 px-2 py-1 text-right tabular-nums"
                        >
                            {{ money(invoice.subtotal) }}
                        </td>
                    </tr>

                    <tr v-if="invoice.discount > 0">
                        <td></td>
                        <td class="px-2 py-1 text-right font-semibold italic">
                            Less : Discount
                        </td>
                        <td colspan="4"></td>
                        <td
                            class="px-2 py-1 text-right font-semibold tabular-nums"
                        >
                            (-){{ money(invoice.discount) }}
                        </td>
                    </tr>

                    <tr v-for="tax in invoice.tax_lines" :key="tax.label">
                        <td></td>
                        <td class="px-2 py-1 text-right font-semibold italic">
                            {{ tax.label }}
                        </td>
                        <td colspan="2"></td>
                        <td class="px-2 py-1 text-right italic tabular-nums">
                            {{ Number(tax.rate.toFixed(2)) }}
                        </td>
                        <td class="px-2 py-1 text-center">%</td>
                        <td
                            class="px-2 py-1 text-right font-semibold tabular-nums"
                        >
                            {{ money(tax.amount) }}
                        </td>
                    </tr>

                    <tr v-if="Math.abs(invoice.round_off) >= 0.01">
                        <td></td>
                        <td class="px-2 py-1">
                            <span class="float-left italic">Less :</span>
                            <span class="block text-right font-semibold italic"
                                >Round Off Sale</span
                            >
                        </td>
                        <td colspan="4"></td>
                        <td
                            class="px-2 py-1 text-right font-semibold tabular-nums"
                        >
                            <template v-if="invoice.round_off < 0">(-)</template
                            >{{ money(Math.abs(invoice.round_off)) }}
                        </td>
                    </tr>

                    <tr class="border-y border-foreground/40 font-bold">
                        <td></td>
                        <td class="px-2 py-2 text-right">Total</td>
                        <td></td>
                        <td class="px-2 py-2 text-right tabular-nums">
                            {{ amount(invoice.total_quantity) }}
                            {{ invoice.total_unit }}
                        </td>
                        <td colspan="2"></td>
                        <td class="px-2 py-2 text-right tabular-nums">
                            ₹ {{ money(invoice.total) }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="flex items-end justify-between gap-4 p-3">
            <div>
                <p class="text-[11px] text-muted-foreground">
                    Amount Chargeable (in words)
                </p>
                <p class="text-sm font-bold">{{ invoice.amount_in_words }}</p>
            </div>
            <p class="text-xs text-muted-foreground italic">E. &amp; O.E.</p>
        </div>

        <!-- The tax summary, by HSN -->
        <div class="overflow-x-auto border-t border-foreground/25">
            <table class="w-full min-w-[42rem] text-xs">
                <thead class="bg-muted/60 text-center">
                    <tr class="border-b border-foreground/25">
                        <th rowspan="2" class="px-2 py-2 font-normal">
                            HSN/SAC
                        </th>
                        <th rowspan="2" class="px-2 py-2 font-normal">
                            Taxable Value
                        </th>
                        <template v-if="invoice.inter_state">
                            <th colspan="2" class="px-2 py-1 font-normal">
                                IGST
                            </th>
                        </template>
                        <template v-else>
                            <th colspan="2" class="px-2 py-1 font-normal">
                                CGST
                            </th>
                            <th colspan="2" class="px-2 py-1 font-normal">
                                SGST/UTGST
                            </th>
                        </template>
                        <th rowspan="2" class="px-2 py-2 font-normal">
                            Total Tax Amount
                        </th>
                    </tr>
                    <tr class="border-b border-foreground/25">
                        <th class="px-2 py-1 font-normal">Rate</th>
                        <th class="px-2 py-1 font-normal">Amount</th>
                        <template v-if="!invoice.inter_state">
                            <th class="px-2 py-1 font-normal">Rate</th>
                            <th class="px-2 py-1 font-normal">Amount</th>
                        </template>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in invoice.hsn_summary" :key="row.hsn">
                        <td class="px-2 py-1">{{ row.hsn }}</td>
                        <td class="px-2 py-1 text-right tabular-nums">
                            {{ money(row.taxable) }}
                        </td>
                        <template v-if="invoice.inter_state">
                            <td class="px-2 py-1 text-center">
                                {{ percent(row.rate) }}
                            </td>
                            <td class="px-2 py-1 text-right tabular-nums">
                                {{ money(row.igst) }}
                            </td>
                        </template>
                        <template v-else>
                            <td class="px-2 py-1 text-center">
                                {{ percent(row.rate / 2) }}
                            </td>
                            <td class="px-2 py-1 text-right tabular-nums">
                                {{ money(row.cgst) }}
                            </td>
                            <td class="px-2 py-1 text-center">
                                {{ percent(row.rate / 2) }}
                            </td>
                            <td class="px-2 py-1 text-right tabular-nums">
                                {{ money(row.sgst) }}
                            </td>
                        </template>
                        <td class="px-2 py-1 text-right tabular-nums">
                            {{ money(row.tax) }}
                        </td>
                    </tr>
                    <tr class="border-t border-foreground/25 font-semibold">
                        <td class="px-2 py-1 text-right">Total</td>
                        <td class="px-2 py-1 text-right tabular-nums">
                            {{ money(invoice.taxable_value) }}
                        </td>
                        <template v-if="invoice.inter_state">
                            <td></td>
                            <td class="px-2 py-1 text-right tabular-nums">
                                {{ money(invoice.tax_total) }}
                            </td>
                        </template>
                        <template v-else>
                            <td></td>
                            <td class="px-2 py-1 text-right tabular-nums">
                                {{ money(invoice.tax_total / 2) }}
                            </td>
                            <td></td>
                            <td class="px-2 py-1 text-right tabular-nums">
                                {{ money(invoice.tax_total / 2) }}
                            </td>
                        </template>
                        <td class="px-2 py-1 text-right tabular-nums">
                            {{ money(invoice.tax_total) }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="border-t border-foreground/25 p-3">
            <span class="text-[11px] text-muted-foreground"
                >Tax Amount (in words) :</span
            >
            <span class="pl-2 text-sm font-bold">{{
                invoice.tax_in_words
            }}</span>
        </div>

        <!-- Declaration, bank details and the signature -->
        <div
            class="grid border-t border-foreground/25 md:grid-cols-2 md:divide-x md:divide-foreground/25"
        >
            <div class="p-3">
                <template v-if="quotation.notes">
                    <p class="text-[11px] text-muted-foreground">Notes</p>
                    <p class="text-xs whitespace-pre-line">
                        {{ quotation.notes }}
                    </p>
                </template>
                <p class="pt-4 text-xs font-semibold underline">Declaration</p>
                <p class="text-xs text-muted-foreground">
                    {{ company.declaration }}
                </p>
            </div>
            <div
                class="flex flex-col justify-between gap-6 border-t border-foreground/25 p-3 md:border-t-0"
            >
                <div v-if="company.bank?.name">
                    <p class="text-xs font-semibold">Company's Bank Details</p>
                    <dl class="grid grid-cols-[8rem_1fr] pt-1 text-xs">
                        <dt class="text-muted-foreground">Bank Name</dt>
                        <dd class="font-semibold">: {{ company.bank.name }}</dd>
                        <template v-if="company.bank.account">
                            <dt class="text-muted-foreground">A/c No.</dt>
                            <dd class="font-semibold">
                                : {{ company.bank.account }}
                            </dd>
                        </template>
                        <template v-if="company.bank.branch_ifsc">
                            <dt class="text-muted-foreground">
                                Branch &amp; IFS Code
                            </dt>
                            <dd class="font-semibold">
                                : {{ company.bank.branch_ifsc }}
                            </dd>
                        </template>
                    </dl>
                </div>
                <div class="text-right">
                    <p class="text-xs font-semibold">
                        for {{ company.name ?? '—' }}
                    </p>
                    <p class="pt-6 text-xs text-muted-foreground">
                        Authorised Signatory
                    </p>
                </div>
            </div>
        </div>

        <p
            class="border-t border-foreground/25 py-2 text-center text-[11px] text-muted-foreground"
        >
            {{ company.footer ?? 'This is a Computer Generated Invoice' }}
        </p>
    </div>
</template>
