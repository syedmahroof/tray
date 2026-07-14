<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { FileText, Plus } from '@lucide/vue';
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
import { show as showQuotation } from '@/routes/quotations';
import type { QuotationSummary } from '@/types';

defineProps<{
    quotations: QuotationSummary[];
    /** Wayfinder URL to the create form, pre-filled for this profile. */
    createHref?: string;
    canCreate?: boolean;
}>();

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
</script>

<template>
    <Card>
        <CardHeader
            class="flex flex-row items-center justify-between border-b pb-3"
        >
            <CardTitle class="flex items-center gap-2 text-base font-semibold">
                <FileText class="h-5 w-5 text-[#4f46e5]" /> Quotations
                <span class="text-sm font-normal text-muted-foreground"
                    >({{ quotations.length }})</span
                >
            </CardTitle>
            <Button
                v-if="canCreate && createHref"
                variant="outline"
                size="sm"
                as-child
            >
                <Link :href="createHref"><Plus class="h-4 w-4" /> New</Link>
            </Button>
        </CardHeader>
        <CardContent class="pt-4">
            <Table v-if="quotations.length">
                <TableHeader>
                    <TableRow>
                        <TableHead>Number</TableHead>
                        <TableHead>Date</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead class="text-right">Total</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-for="quotation in quotations"
                        :key="quotation.id"
                    >
                        <TableCell>
                            <div class="flex items-center gap-2">
                                <Link
                                    :href="showQuotation(quotation.id)"
                                    class="font-medium hover:underline"
                                >
                                    {{ quotation.number }}
                                </Link>
                                <Badge
                                    v-if="quotation.version > 1"
                                    variant="secondary"
                                    class="tabular-nums"
                                >
                                    v{{ quotation.version }}
                                </Badge>
                            </div>
                        </TableCell>
                        <TableCell>{{
                            formatDate(quotation.quotation_date)
                        }}</TableCell>
                        <TableCell>
                            <Badge
                                :variant="statusVariant(quotation.status)"
                                class="capitalize"
                            >
                                {{ quotation.status }}
                            </Badge>
                        </TableCell>
                        <TableCell class="text-right tabular-nums">{{
                            money(quotation.total)
                        }}</TableCell>
                    </TableRow>
                </TableBody>
            </Table>
            <p v-else class="py-6 text-center text-sm text-muted-foreground">
                No quotations yet.
            </p>
        </CardContent>
    </Card>
</template>
