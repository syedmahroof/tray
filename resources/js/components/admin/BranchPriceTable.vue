<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import EditablePriceCell from '@/components/admin/EditablePriceCell.vue';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    RATE_TIER_KEYS,
    impliedFreight,
    money,
    percent,
    percentFor,
    rateFor,
    toNumber,
    usesMrp,
} from '@/lib/products';
import type { PriceBasis } from '@/lib/products';
import { show } from '@/routes/products';
import { update as updateBranchPrice } from '@/routes/products/branch-prices';
import type { Branch, BranchPriceCells, PriceMatrixRow } from '@/types';

const props = withDefaults(
    defineProps<{
        rows: PriceMatrixRow[];
        branches: Branch[];
        /** Insert a heading row whenever the category changes, as the workbook does. */
        groupByCategory?: boolean;
        startIndex?: number;
        /** Let figures be edited in place, spreadsheet style. */
        editable?: boolean;
    }>(),
    { groupByCategory: false, startIndex: 1, editable: false },
);

type FigureKey = Exclude<keyof BranchPriceCells, 'mrp'>;

/** The discount % and rate columns each branch contributes, per tier. */
const TIER_COLUMNS = [
    { key: 'sr_discount', label: 'SR %', kind: 'discount' },
    { key: 'sr_rate', label: 'SR', kind: 'rate' },
    { key: 'sr_rate_with_tax', label: 'SR+TAX', kind: 'tax' },
    { key: 'pr_discount', label: 'PR %', kind: 'discount' },
    { key: 'pr_rate', label: 'PR', kind: 'rate' },
    { key: 'pr_rate_with_tax', label: 'PR+TAX', kind: 'tax' },
    { key: 'cr_discount', label: 'CR %', kind: 'discount' },
    { key: 'cr_rate', label: 'CR', kind: 'rate' },
    { key: 'cr_rate_with_tax', label: 'CR+TAX', kind: 'tax' },
] as const satisfies readonly {
    key: FigureKey;
    label: string;
    kind: 'discount' | 'rate' | 'tax';
}[];

const leadingColumns = 4;

const totalColumns = computed(
    () => leadingColumns + props.branches.length * (TIER_COLUMNS.length + 1),
);

/**
 * A working copy of the rows, so a saved cell updates in place without
 * reloading the page. Replaced whenever the page hands over new rows.
 */
const localRows = ref<PriceMatrixRow[]>([]);

watch(
    () => props.rows,
    (rows) => {
        localRows.value = rows.map((row) => ({
            ...row,
            prices: { ...row.prices },
        }));
    },
    { immediate: true },
);

/** Rows annotated with the category heading that should precede them. */
const displayRows = computed(() => {
    let previous: string | null = null;

    return localRows.value.map((row) => {
        const heading =
            props.groupByCategory && row.category !== previous
                ? row.category
                : null;
        previous = row.category;

        return { row, heading };
    });
});

const basisOf = (cells: BranchPriceCells | null): PriceBasis => ({
    mrp: cells?.mrp,
    cost: cells?.cost,
    rate_basis: cells?.rate_basis,
    freight: cells ? impliedFreight(cells) : 0,
});

/**
 * The figures to send alongside an edit so the row stays consistent, the
 * same way the product form's editor keeps them in step: a new % re-prices
 * its tier, a new rate works its % back out, and a new cost re-prices every
 * marked-up tier.
 */
const companionsFor =
    (cells: BranchPriceCells | null, key: FigureKey) =>
    (value: number | null): Partial<Record<FigureKey, number | null>> => {
        if (value === null) {
            return {};
        }

        const basis = basisOf(cells);

        if (key === 'cost') {
            if (usesMrp(basis)) {
                return {};
            }

            const repriced: Partial<Record<FigureKey, number | null>> = {};

            for (const tier of RATE_TIER_KEYS) {
                const prefix = tier.toLowerCase() as 'sr' | 'pr' | 'cr';
                const percentage = toNumber(cells?.[`${prefix}_discount`]);
                const rate =
                    percentage === undefined
                        ? undefined
                        : rateFor({ ...basis, cost: value }, percentage);

                if (rate !== undefined) {
                    repriced[`${prefix}_rate`] = rate;
                }
            }

            return repriced;
        }

        const [prefix, field] = key.split('_') as ['sr' | 'pr' | 'cr', string];

        if (field === 'discount') {
            const rate = rateFor(basis, value);

            return rate === undefined ? {} : { [`${prefix}_rate`]: rate };
        }

        const percentage = percentFor(basis, value);

        return percentage === undefined
            ? {}
            : { [`${prefix}_discount`]: percentage };
    };

const onSaved = (
    row: PriceMatrixRow,
    branchId: number,
    cells: BranchPriceCells,
) => {
    row.prices = { ...row.prices, [branchId]: cells };
};
</script>

<template>
    <div class="overflow-x-auto">
        <Table class="min-w-max">
            <TableHeader>
                <TableRow>
                    <TableHead :colspan="leadingColumns" class="border-r" />
                    <TableHead
                        v-for="branch in branches"
                        :key="`group-${branch.id}`"
                        :colspan="TIER_COLUMNS.length + 1"
                        class="border-r text-center font-semibold text-foreground"
                    >
                        {{ branch.name }}
                    </TableHead>
                </TableRow>
                <TableRow>
                    <TableHead class="w-14">SL NO.</TableHead>
                    <TableHead class="w-32">CODE</TableHead>
                    <TableHead class="min-w-[18rem]">ITEMS</TableHead>
                    <TableHead class="w-20 border-r">UNIT</TableHead>
                    <template v-for="branch in branches" :key="branch.id">
                        <TableHead class="text-right whitespace-nowrap">
                            COST
                        </TableHead>
                        <TableHead
                            v-for="(column, position) in TIER_COLUMNS"
                            :key="`${branch.id}-${column.key}`"
                            class="text-right whitespace-nowrap"
                            :class="
                                position === TIER_COLUMNS.length - 1
                                    ? 'border-r'
                                    : undefined
                            "
                        >
                            {{ column.label }}
                        </TableHead>
                    </template>
                </TableRow>
            </TableHeader>
            <TableBody>
                <template
                    v-for="({ row, heading }, index) in displayRows"
                    :key="row.id"
                >
                    <TableRow v-if="heading" class="bg-muted/60">
                        <TableCell
                            :colspan="totalColumns"
                            class="text-sm font-semibold tracking-wide uppercase"
                        >
                            {{ heading }}
                        </TableCell>
                    </TableRow>
                    <TableRow>
                        <TableCell class="text-muted-foreground">
                            {{ startIndex + index }}
                        </TableCell>
                        <TableCell
                            class="font-mono text-xs text-muted-foreground"
                        >
                            {{ row.code ?? '—' }}
                        </TableCell>
                        <TableCell class="font-medium">
                            <Link
                                :href="show(row.id)"
                                class="hover:underline"
                                >{{ row.name }}</Link
                            >
                            <span
                                v-if="row.brand"
                                class="ml-2 text-xs text-muted-foreground"
                                >{{ row.brand }}</span
                            >
                        </TableCell>
                        <TableCell class="border-r text-muted-foreground">
                            {{ row.unit ?? '—' }}
                        </TableCell>
                        <template v-for="branch in branches" :key="branch.id">
                            <TableCell
                                class="text-right text-muted-foreground tabular-nums"
                                :class="editable ? 'p-1' : undefined"
                            >
                                <EditablePriceCell
                                    v-if="editable"
                                    :value="row.prices[branch.id]?.cost ?? null"
                                    kind="money"
                                    field="cost"
                                    :url="
                                        updateBranchPrice.url({
                                            product: row.id,
                                            branch: branch.id,
                                        })
                                    "
                                    :companions="
                                        companionsFor(
                                            row.prices[branch.id] ?? null,
                                            'cost',
                                        )
                                    "
                                    :grid-row="index"
                                    :grid-col="`${branch.id}-cost`"
                                    @saved="onSaved(row, branch.id, $event)"
                                />
                                <template v-else>
                                    {{ money(row.prices[branch.id]?.cost) }}
                                </template>
                            </TableCell>
                            <TableCell
                                v-for="(column, position) in TIER_COLUMNS"
                                :key="`${row.id}-${branch.id}-${column.key}`"
                                class="text-right tabular-nums"
                                :class="[
                                    column.kind === 'rate'
                                        ? 'font-medium'
                                        : 'text-muted-foreground',
                                    position === TIER_COLUMNS.length - 1
                                        ? 'border-r'
                                        : undefined,
                                    editable && column.kind !== 'tax'
                                        ? 'p-1'
                                        : undefined,
                                ]"
                            >
                                <EditablePriceCell
                                    v-if="editable && column.kind !== 'tax'"
                                    :value="
                                        row.prices[branch.id]?.[column.key] ??
                                        null
                                    "
                                    :kind="
                                        column.kind === 'discount'
                                            ? 'percent'
                                            : 'money'
                                    "
                                    :field="column.key"
                                    :url="
                                        updateBranchPrice.url({
                                            product: row.id,
                                            branch: branch.id,
                                        })
                                    "
                                    :companions="
                                        companionsFor(
                                            row.prices[branch.id] ?? null,
                                            column.key,
                                        )
                                    "
                                    :grid-row="index"
                                    :grid-col="`${branch.id}-${column.key}`"
                                    @saved="onSaved(row, branch.id, $event)"
                                />
                                <template v-else>
                                    {{
                                        column.kind === 'discount'
                                            ? percent(
                                                  row.prices[branch.id]?.[
                                                      column.key
                                                  ],
                                              )
                                            : money(
                                                  row.prices[branch.id]?.[
                                                      column.key
                                                  ],
                                              )
                                    }}
                                </template>
                            </TableCell>
                        </template>
                    </TableRow>
                </template>
                <TableRow v-if="rows.length === 0">
                    <TableCell
                        :colspan="totalColumns"
                        class="text-center text-muted-foreground"
                    >
                        No products match these filters.
                    </TableCell>
                </TableRow>
            </TableBody>
        </Table>
    </div>
</template>
