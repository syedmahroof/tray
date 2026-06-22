<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowLeft, Building, Download, Pencil, User } from '@lucide/vue';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDate } from '@/lib/utils';
import { show as showContact } from '@/routes/contacts';
import { show as showProject } from '@/routes/projects';
import { edit, index, pdf } from '@/routes/quotations';
import type { QuotationDetail } from '@/types';

defineProps<{
    quotation: QuotationDetail;
}>();

defineOptions({
    layout: (props: { quotation: QuotationDetail }) => ({
        breadcrumbs: [
            { title: 'Quotations', href: index() },
            { title: props.quotation.number, href: index() },
        ],
    }),
});

const permissions = computed(() => usePage().props.auth.permissions);

const statusVariant = (status: string) => {
    if (status === 'accepted') {
        return 'default' as const;
    }

    if (status === 'rejected' || status === 'expired') {
        return 'destructive' as const;
    }

    if (status === 'sent') {
        return 'outline' as const;
    }

    return 'secondary' as const;
};

const money = (value: string | number) =>
    `₹${Number(value).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

const lineTotal = (item: { quantity: string; unit_price: string }) =>
    Number(item.quantity) * Number(item.unit_price);
</script>

<template>
    <Head :title="quotation.number" />

    <div class="flex flex-col space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <Link
                    :href="index()"
                    class="mb-2 inline-flex items-center gap-1 text-sm text-muted-foreground hover:underline"
                >
                    <ArrowLeft class="h-4 w-4 text-slate-500" /> Back to List
                </Link>
                <Heading
                    variant="small"
                    :title="quotation.number"
                    :description="`Created ${formatDate(quotation.created_at)}`"
                />
            </div>

            <div class="flex items-center gap-2">
                <Button variant="outline" as-child>
                    <a :href="pdf.url(quotation.id)">
                        <Download class="h-4 w-4" /> Download PDF
                    </a>
                </Button>
                <Button
                    v-if="permissions.includes('quotations.update')"
                    as-child
                >
                    <Link :href="edit(quotation.id)"><Pencil /> Edit</Link>
                </Button>
            </div>
        </div>

        <div class="grid gap-6 md:grid-cols-3">
            <Card>
                <CardHeader
                    class="flex flex-row items-center gap-2 border-b pb-3"
                >
                    <User class="h-5 w-5 text-[#2563eb]" />
                    <CardTitle class="text-base font-semibold"
                        >Quotation For</CardTitle
                    >
                </CardHeader>
                <CardContent class="space-y-1 pt-6 text-sm">
                    <Link
                        v-if="quotation.contact"
                        :href="showContact(quotation.contact.id)"
                        class="font-medium hover:underline"
                    >
                        {{ quotation.contact.name }}
                    </Link>
                    <p v-else class="text-muted-foreground">—</p>
                    <p
                        v-if="quotation.contact?.phone"
                        class="text-muted-foreground"
                    >
                        {{ quotation.contact.phone }}
                    </p>
                    <p
                        v-if="quotation.contact?.email"
                        class="text-muted-foreground"
                    >
                        {{ quotation.contact.email }}
                    </p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader
                    class="flex flex-row items-center gap-2 border-b pb-3"
                >
                    <Building class="h-5 w-5 text-[#4f46e5]" />
                    <CardTitle class="text-base font-semibold"
                        >Details</CardTitle
                    >
                </CardHeader>
                <CardContent class="space-y-2 pt-6 text-sm">
                    <div class="flex justify-between">
                        <span class="text-muted-foreground">Status</span>
                        <Badge
                            :variant="statusVariant(quotation.status)"
                            class="capitalize"
                        >
                            {{ quotation.status }}
                        </Badge>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-muted-foreground">Date</span>
                        <span>{{ formatDate(quotation.quotation_date) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-muted-foreground">Valid until</span>
                        <span>{{ formatDate(quotation.valid_until) }}</span>
                    </div>
                    <div v-if="quotation.project" class="flex justify-between">
                        <span class="text-muted-foreground">Project</span>
                        <Link
                            :href="showProject(quotation.project.id)"
                            class="font-medium hover:underline"
                        >
                            {{ quotation.project.name }}
                        </Link>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader
                    class="flex flex-row items-center gap-2 border-b pb-3"
                >
                    <CardTitle class="text-base font-semibold">Total</CardTitle>
                </CardHeader>
                <CardContent class="space-y-2 pt-6 text-sm">
                    <div class="flex justify-between">
                        <span class="text-muted-foreground">Subtotal</span>
                        <span class="tabular-nums">{{
                            money(quotation.subtotal)
                        }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-muted-foreground">Discount</span>
                        <span class="tabular-nums"
                            >- {{ money(quotation.discount) }}</span
                        >
                    </div>
                    <div class="flex justify-between">
                        <span class="text-muted-foreground"
                            >Tax ({{ Number(quotation.tax_percent) }}%)</span
                        >
                        <span class="tabular-nums">{{
                            money(quotation.tax_amount)
                        }}</span>
                    </div>
                    <div
                        class="flex justify-between border-t pt-2 text-base font-semibold"
                    >
                        <span>Total</span>
                        <span class="tabular-nums">{{
                            money(quotation.total)
                        }}</span>
                    </div>
                </CardContent>
            </Card>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Items</CardTitle>
            </CardHeader>
            <CardContent>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead class="w-12">#</TableHead>
                            <TableHead>Description</TableHead>
                            <TableHead class="text-right">Qty</TableHead>
                            <TableHead class="text-right">Unit Price</TableHead>
                            <TableHead class="text-right">Amount</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="(item, i) in quotation.items"
                            :key="item.id"
                        >
                            <TableCell class="text-muted-foreground">{{
                                i + 1
                            }}</TableCell>
                            <TableCell>
                                <div class="font-medium">
                                    {{ item.description }}
                                </div>
                                <div
                                    v-if="item.product"
                                    class="text-xs text-muted-foreground"
                                >
                                    {{ item.product.name }}
                                </div>
                            </TableCell>
                            <TableCell class="text-right tabular-nums">{{
                                Number(item.quantity)
                            }}</TableCell>
                            <TableCell class="text-right tabular-nums">{{
                                money(item.unit_price)
                            }}</TableCell>
                            <TableCell class="text-right tabular-nums">{{
                                money(lineTotal(item))
                            }}</TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </CardContent>
        </Card>

        <div
            v-if="quotation.notes || quotation.terms"
            class="grid gap-6 md:grid-cols-2"
        >
            <Card v-if="quotation.notes">
                <CardHeader><CardTitle>Notes</CardTitle></CardHeader>
                <CardContent
                    class="text-sm whitespace-pre-wrap text-muted-foreground"
                    >{{ quotation.notes }}</CardContent
                >
            </Card>
            <Card v-if="quotation.terms">
                <CardHeader
                    ><CardTitle>Terms &amp; Conditions</CardTitle></CardHeader
                >
                <CardContent
                    class="text-sm whitespace-pre-wrap text-muted-foreground"
                    >{{ quotation.terms }}</CardContent
                >
            </Card>
        </div>
    </div>
</template>
