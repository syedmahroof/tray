<script setup lang="ts">
import { CopyCheck, Plus, RotateCcw, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
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
    RATE_TIERS,
    RATE_TIER_KEYS,
    basisFor,
    impliedFreight,
    money,
    rateFor,
    roundTo2,
    toNumber,
    usesMrp,
    withTax,
} from '@/lib/products';
import type { Branch, ProductBranchPrice, RateTier } from '@/types';

const props = withDefaults(
    defineProps<{
        branches: Branch[];
        existing?: ProductBranchPrice[];
        taxPercentage: number;
        errors?: Record<string, string>;
    }>(),
    { existing: () => [], errors: () => ({}) },
);

type Row = {
    key: number;
    branchId: string;
    isNew: boolean;
    removed: boolean;
    cost?: number;
    mrp?: number;
    /** Added to cost before a markup is applied. Worked out, never saved. */
    freight: number;
    discounts: Record<RateTier, number | undefined>;
    rates: Record<RateTier, number | undefined>;
};

let nextKey = 0;

const tierKey = (tier: RateTier) => tier.toLowerCase() as Lowercase<RateTier>;

const rowFor = (price: ProductBranchPrice): Row => ({
    key: nextKey++,
    branchId: String(price.branch_id),
    isNew: false,
    removed: false,
    cost: toNumber(price.cost),
    mrp: toNumber(price.mrp),
    freight: impliedFreight(price),
    discounts: {
        SR: toNumber(price.sr_discount),
        PR: toNumber(price.pr_discount),
        CR: toNumber(price.cr_discount),
    },
    rates: {
        SR: toNumber(price.sr_rate),
        PR: toNumber(price.pr_rate),
        CR: toNumber(price.cr_rate),
    },
});

const rows = ref<Row[]>(props.existing.map(rowFor));

/** Branches not yet priced, so a branch can never be added twice. */
const availableBranches = computed(() => {
    const taken = new Set(
        rows.value.filter((row) => !row.removed).map((row) => row.branchId),
    );

    return props.branches.filter((branch) => !taken.has(String(branch.id)));
});

const branchName = (branchId: string) =>
    props.branches.find((branch) => String(branch.id) === branchId)?.name ??
    'Unknown branch';

const blankRow = (branchId: string): Row => ({
    key: nextKey++,
    branchId,
    isNew: true,
    removed: false,
    freight: 0,
    discounts: { SR: undefined, PR: undefined, CR: undefined },
    rates: { SR: undefined, PR: undefined, CR: undefined },
});

const addRow = () => {
    const branch = availableBranches.value[0];

    if (!branch) {
        return;
    }

    rows.value.push(blankRow(String(branch.id)));
};

/** A new row is dropped outright; a saved one is flagged for the server. */
const removeRow = (row: Row) => {
    if (row.isNew) {
        rows.value = rows.value.filter(
            (candidate) => candidate.key !== row.key,
        );

        return;
    }

    row.removed = true;
};

/**
 * Copy one branch's figures onto every other branch, adding any branch that
 * is not priced yet. Rows flagged for removal are left alone.
 */
const applyToAllBranches = (source: Row) => {
    const copyInto = (target: Row) => {
        target.cost = source.cost;
        target.mrp = source.mrp;
        target.freight = source.freight;
        target.discounts = { ...source.discounts };
        target.rates = { ...source.rates };
    };

    rows.value
        .filter((row) => row.key !== source.key && !row.removed)
        .forEach(copyInto);

    for (const branch of [...availableBranches.value]) {
        const row = blankRow(String(branch.id));
        copyInto(row);
        rows.value.push(row);
    }
};

const error = (index: number, field: string) =>
    props.errors[`prices.${index}.${field}`];

/**
 * Keep a tier's rate in step with its %. Handlers take the incoming value
 * rather than reading the row, as they may run before v-model writes it.
 */
const onPercentChange = (row: Row, tier: RateTier, value: string | number) => {
    const percentage = toNumber(value);

    if (percentage === undefined) {
        return;
    }

    const rate = rateFor(row, percentage);

    if (rate !== undefined) {
        row.rates[tier] = rate;
    }
};

/** Keep a tier's % in step with a typed rate. */
const onRateChange = (row: Row, tier: RateTier, value: string | number) => {
    const rate = toNumber(value);
    const basis = basisFor(row);

    if (rate === undefined || !basis) {
        return;
    }

    const percentage = usesMrp(row)
        ? (1 - rate / basis) * 100
        : (rate / basis - 1) * 100;

    // A rate on the wrong side of its basis would need a negative %, which
    // the server rejects; clear it instead.
    row.discounts[tier] = percentage < 0 ? undefined : roundTo2(percentage);
};

/** Re-price every tier that carries a % when the cost or MRP moves. */
const onBasisChange = (
    row: Row,
    field: 'cost' | 'mrp',
    value: string | number,
) => {
    // Clearing a field is left alone, so emptying the MRP never silently
    // turns every discount into a markup.
    if (toNumber(value) === undefined) {
        return;
    }

    const next = { ...row, [field]: toNumber(value) };

    for (const tier of RATE_TIER_KEYS) {
        const percentage = toNumber(row.discounts[tier]);
        const rate =
            percentage === undefined ? undefined : rateFor(next, percentage);

        if (rate !== undefined) {
            row.rates[tier] = rate;
        }
    }
};

/** Says what the % on this row is worked out from. */
const basisHint = (row: Row) => {
    if (usesMrp(row)) {
        return `% is a discount off MRP ${money(row.mrp)}.`;
    }

    const basis = basisFor(row);

    if (basis === undefined) {
        return 'Enter a cost or MRP to work rates out from a %.';
    }

    return row.freight > 0
        ? `% is a markup on ${money(basis)} (cost + ${money(row.freight)} freight).`
        : `% is a markup on cost ${money(basis)}.`;
};
</script>

<template>
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <div>
                <Label class="text-base">Branch prices</Label>
                <p class="text-sm text-muted-foreground">
                    Each branch keeps its own {{ RATE_TIER_KEYS.join(', ') }}
                    rates. Tax-inclusive figures are worked out on save at
                    {{ taxPercentage }}%.
                </p>
            </div>
            <Button
                type="button"
                variant="outline"
                size="sm"
                :disabled="availableBranches.length === 0"
                data-test="add-branch-price"
                @click="addRow"
            >
                <Plus class="h-4 w-4" /> Add branch
            </Button>
        </div>

        <p
            v-if="rows.length === 0"
            class="rounded-md border border-dashed p-6 text-center text-sm text-muted-foreground"
        >
            No branch prices yet. Add a branch to set its rates.
        </p>

        <div
            v-for="(row, index) in rows"
            :key="row.key"
            class="rounded-lg border p-4"
            :class="row.removed ? 'opacity-60' : undefined"
        >
            <input
                type="hidden"
                :name="`prices[${index}][branch_id]`"
                :value="row.branchId"
            />
            <input
                v-if="row.removed"
                type="hidden"
                :name="`prices[${index}][remove]`"
                value="1"
            />

            <div class="flex flex-wrap items-end justify-between gap-3">
                <div class="grid gap-2">
                    <Label>Branch</Label>
                    <Select
                        v-if="row.isNew"
                        v-model="row.branchId"
                        :disabled="row.removed"
                    >
                        <SelectTrigger class="w-[220px]">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="branch in branches"
                                :key="branch.id"
                                :value="String(branch.id)"
                                :disabled="
                                    String(branch.id) !== row.branchId &&
                                    !availableBranches.some(
                                        (option) => option.id === branch.id,
                                    )
                                "
                            >
                                {{ branch.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <p v-else class="text-sm font-semibold">
                        {{ branchName(row.branchId) }}
                        <span
                            v-if="row.removed"
                            class="ml-2 text-xs font-normal text-destructive"
                        >
                            will be removed on save
                        </span>
                    </p>
                    <InputError :message="error(index, 'branch_id')" />
                </div>

                <Button
                    v-if="row.removed"
                    type="button"
                    variant="ghost"
                    size="sm"
                    @click="row.removed = false"
                >
                    <RotateCcw class="h-4 w-4" /> Keep
                </Button>
                <div v-else class="flex items-center gap-1">
                    <Button
                        v-if="branches.length > 1"
                        type="button"
                        variant="outline"
                        size="sm"
                        data-test="apply-to-all-branches"
                        :title="`Copy ${branchName(row.branchId)}'s figures to every branch`"
                        @click="applyToAllBranches(row)"
                    >
                        <CopyCheck class="h-4 w-4" /> Apply to all branches
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        class="text-destructive"
                        :aria-label="`Remove ${branchName(row.branchId)} price`"
                        @click="removeRow(row)"
                    >
                        <Trash2 class="h-4 w-4" /> Remove
                    </Button>
                </div>
            </div>

            <template v-if="!row.removed">
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label :for="`cost-${row.key}`">Cost</Label>
                        <Input
                            :id="`cost-${row.key}`"
                            v-model.number="row.cost"
                            :name="`prices[${index}][cost]`"
                            type="number"
                            step="0.0001"
                            min="0"
                            @update:model-value="
                                onBasisChange(row, 'cost', $event)
                            "
                        />
                        <InputError :message="error(index, 'cost')" />
                    </div>
                    <div class="grid gap-2">
                        <Label :for="`mrp-${row.key}`">MRP</Label>
                        <Input
                            :id="`mrp-${row.key}`"
                            v-model.number="row.mrp"
                            :name="`prices[${index}][mrp]`"
                            type="number"
                            step="0.01"
                            min="0"
                            @update:model-value="
                                onBasisChange(row, 'mrp', $event)
                            "
                        />
                        <InputError :message="error(index, 'mrp')" />
                    </div>
                </div>

                <p
                    class="mt-2 text-xs text-muted-foreground"
                    :data-test="`basis-hint-${index}`"
                >
                    {{ basisHint(row) }}
                </p>

                <div class="mt-4 grid gap-4 sm:grid-cols-3">
                    <div
                        v-for="tier in RATE_TIER_KEYS"
                        :key="tier"
                        class="rounded-md bg-muted/40 p-3"
                    >
                        <p class="text-sm font-semibold">
                            {{ tier }}
                            <span
                                class="ml-1 text-xs font-normal text-muted-foreground"
                            >
                                {{ RATE_TIERS[tier] }}
                            </span>
                        </p>

                        <div class="mt-3 grid gap-2">
                            <Label
                                :for="`${tierKey(tier)}-discount-${row.key}`"
                                class="text-xs"
                            >
                                {{ usesMrp(row) ? 'Discount %' : 'Markup %' }}
                            </Label>
                            <Input
                                :id="`${tierKey(tier)}-discount-${row.key}`"
                                v-model.number="row.discounts[tier]"
                                :name="`prices[${index}][${tierKey(tier)}_discount]`"
                                type="number"
                                step="0.01"
                                min="0"
                                @update:model-value="
                                    onPercentChange(row, tier, $event)
                                "
                            />
                            <InputError
                                :message="
                                    error(index, `${tierKey(tier)}_discount`)
                                "
                            />
                        </div>

                        <div class="mt-3 grid gap-2">
                            <Label
                                :for="`${tierKey(tier)}-rate-${row.key}`"
                                class="text-xs"
                            >
                                Rate
                            </Label>
                            <Input
                                :id="`${tierKey(tier)}-rate-${row.key}`"
                                v-model.number="row.rates[tier]"
                                :name="`prices[${index}][${tierKey(tier)}_rate]`"
                                type="number"
                                step="0.01"
                                min="0"
                                @update:model-value="
                                    onRateChange(row, tier, $event)
                                "
                            />
                            <InputError
                                :message="error(index, `${tierKey(tier)}_rate`)"
                            />
                        </div>

                        <p class="mt-2 text-xs text-muted-foreground">
                            incl. tax
                            <span class="font-medium text-foreground">
                                {{
                                    money(
                                        withTax(row.rates[tier], taxPercentage),
                                    )
                                }}
                            </span>
                        </p>
                    </div>
                </div>
            </template>
        </div>
    </div>
</template>
