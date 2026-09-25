import type { AnalyticsFormat } from '@/types';

/** Writes a figure out the way its format asks for. */
export function formatAnalytics(
    value: number | string | null | undefined,
    format: AnalyticsFormat,
): string {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    if (
        typeof value === 'string' &&
        format !== 'number' &&
        format !== 'money' &&
        format !== 'percent'
    ) {
        if (format === 'status') {
            return (
                value.charAt(0).toUpperCase() +
                value.slice(1).replace(/_/g, ' ')
            );
        }

        if (format === 'date') {
            return new Date(`${value}T00:00:00`).toLocaleDateString('en-IN', {
                day: '2-digit',
                month: 'short',
                year: 'numeric',
            });
        }

        return value;
    }

    const number = Number(value);

    if (format === 'money') {
        return `₹${number.toLocaleString('en-IN', { maximumFractionDigits: 0 })}`;
    }

    if (format === 'percent') {
        return `${Number(number.toFixed(1))}%`;
    }

    return number.toLocaleString('en-IN', { maximumFractionDigits: 2 });
}

/** A short money figure for tight spaces: ₹1.2L, ₹3.4Cr. */
export function compactMoney(value: number): string {
    if (Math.abs(value) >= 1e7) {
        return `₹${Number((value / 1e7).toFixed(1))}Cr`;
    }

    if (Math.abs(value) >= 1e5) {
        return `₹${Number((value / 1e5).toFixed(1))}L`;
    }

    if (Math.abs(value) >= 1e3) {
        return `₹${Number((value / 1e3).toFixed(1))}K`;
    }

    return `₹${Math.round(value)}`;
}
