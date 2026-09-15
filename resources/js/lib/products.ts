import type { RateTier } from '@/types';

/** Units of measure used across the catalogue. */
export const UNIT_OPTIONS = ['Mtr', 'Nos', 'Kg', 'Sqm', 'Ltr', 'Set'] as const;

/** Rate tiers carried by every branch price row. */
export const RATE_TIERS: Record<RateTier, string> = {
    SR: 'Stockist Rate',
    PR: 'Project Rate',
    CR: 'Counter Rate',
};

export const RATE_TIER_KEYS = Object.keys(RATE_TIERS) as RateTier[];

/** Format a decimal string as rupees, or an em dash when it is missing. */
export const money = (value: string | number | null | undefined): string =>
    value === null || value === undefined || value === ''
        ? '—'
        : Number(value).toLocaleString('en-IN', {
              minimumFractionDigits: 2,
              maximumFractionDigits: 2,
          });

/** Format a decimal string as a percentage, or an em dash when it is missing. */
export const percent = (value: string | number | null | undefined): string =>
    value === null || value === undefined || value === ''
        ? '—'
        : `${Number(value).toLocaleString('en-IN', { maximumFractionDigits: 2 })}%`;

/** Multiply a rate by its tax percentage, matching the importer's rounding. */
export const withTax = (
    rate: number | undefined,
    taxPercentage: number,
): number | undefined =>
    rate === undefined || Number.isNaN(rate)
        ? undefined
        : Math.round(rate * (1 + taxPercentage / 100) * 100) / 100;

/** A number from a form value or decimal string, or undefined when blank. */
export const toNumber = (
    value: string | number | null | undefined,
): number | undefined =>
    value === null || value === undefined || value === ''
        ? undefined
        : Number(value);

export const roundTo2 = (value: number): number =>
    Math.round(value * 100) / 100;

/** The figures a tier % is worked out from. */
export type PriceBasis = {
    mrp?: string | number | null;
    cost?: string | number | null;
    /** Added to cost before a markup. Worked out, never stored. */
    freight: number;
};

/**
 * The price list uses the tier % two ways. With an MRP it is a discount off
 * MRP; without one it is a markup on cost, and on some sheets (cable tray) on
 * cost plus a freight figure the database does not keep.
 */
export const usesMrp = (basis: Pick<PriceBasis, 'mrp'>): boolean =>
    (toNumber(basis.mrp) ?? 0) > 0;

/** The figure a % is applied to, when there is one. */
export const basisFor = (basis: PriceBasis): number | undefined => {
    if (usesMrp(basis)) {
        return toNumber(basis.mrp);
    }

    const cost = toNumber(basis.cost);

    return cost === undefined ? undefined : cost + basis.freight;
};

/** The rate a % gives on the basis. */
export const rateFor = (
    basis: PriceBasis,
    percentage: number,
): number | undefined => {
    const value = basisFor(basis);

    if (value === undefined) {
        return undefined;
    }

    return roundTo2(
        usesMrp(basis)
            ? value * (1 - percentage / 100)
            : value * (1 + percentage / 100),
    );
};

/**
 * The % that turns the basis into a rate: undefined without a basis, null
 * when it would be negative (the server rejects those, so it is cleared).
 */
export const percentFor = (
    basis: PriceBasis,
    rate: number,
): number | null | undefined => {
    const value = basisFor(basis);

    if (!value) {
        return undefined;
    }

    const percentage = usesMrp(basis)
        ? (1 - rate / value) * 100
        : (rate / value - 1) * 100;

    return percentage < 0 ? null : roundTo2(percentage);
};

type TierFigures = {
    [Key in `${Lowercase<RateTier>}_${'discount' | 'rate'}`]?:
        | string
        | number
        | null;
};

/**
 * Recover the freight a saved no-MRP price was built on: the basis its tiers
 * agree on, less cost. Hand-rounded rows agree on nothing and get none.
 */
export const impliedFreight = (
    price: TierFigures & {
        cost?: string | number | null;
        mrp?: string | number | null;
    },
): number => {
    const cost = toNumber(price.cost);

    if (toNumber(price.mrp) || cost === undefined) {
        return 0;
    }

    const bases = RATE_TIER_KEYS.flatMap((tier) => {
        const key = tier.toLowerCase() as Lowercase<RateTier>;
        const percentage = toNumber(price[`${key}_discount`]);
        const rate = toNumber(price[`${key}_rate`]);

        return percentage === undefined || rate === undefined
            ? []
            : [rate / (1 + percentage / 100)];
    });

    let best: { basis: number; votes: number } | undefined;

    for (const basis of bases) {
        const votes = bases.filter(
            (other) => Math.abs(other - basis) <= 0.05,
        ).length;

        if (!best || votes > best.votes) {
            best = { basis, votes };
        }
    }

    if (!best || (bases.length > 1 && best.votes < 2)) {
        return 0;
    }

    return Math.max(0, roundTo2(best.basis - cost));
};
