import { usePage } from '@inertiajs/vue3';
import type { InertiaLinkProps } from '@inertiajs/vue3';
import { clsx } from 'clsx';
import type { ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function toUrl(href: NonNullable<InertiaLinkProps['href']>) {
    return typeof href === 'string' ? href : href?.url;
}

export function formatDate(value: string | null | undefined): string {
    if (!value) {
        return '—';
    }

    try {
        const page = usePage();
        const timezone = page?.props?.auth?.user?.timezone || 'UTC';
        
        const date = new Date(value);
        if (isNaN(date.getTime())) {
            return value;
        }

        // Check if value is a datetime (contains time portion) or just a date
        const hasTime = value.includes('T') || value.includes(':') || value.includes(' ');

        if (hasTime) {
            const formatter = new Intl.DateTimeFormat('en-GB', {
                timeZone: timezone,
                day: '2-digit',
                month: '2-digit',
                year: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
                hour12: false,
            });
            const parts = formatter.formatToParts(date);
            const day = parts.find((p) => p.type === 'day')?.value;
            const month = parts.find((p) => p.type === 'month')?.value;
            const year = parts.find((p) => p.type === 'year')?.value;
            const hour = parts.find((p) => p.type === 'hour')?.value;
            const minute = parts.find((p) => p.type === 'minute')?.value;
            return `${day}/${month}/${year} ${hour}:${minute}`;
        } else {
            // Just a date - format in UTC to avoid date shifting
            const formatter = new Intl.DateTimeFormat('en-GB', {
                timeZone: 'UTC',
                day: '2-digit',
                month: '2-digit',
                year: '2-digit',
            });
            const parts = formatter.formatToParts(date);
            const day = parts.find((p) => p.type === 'day')?.value;
            const month = parts.find((p) => p.type === 'month')?.value;
            const year = parts.find((p) => p.type === 'year')?.value;
            return `${day}/${month}/${year}`;
        }
    } catch (e) {
        const date = new Date(value);
        if (isNaN(date.getTime())) {
            return value;
        }
        const hasTime = value.includes('T') || value.includes(':') || value.includes(' ');
        
        const formatter = new Intl.DateTimeFormat('en-GB', {
            timeZone: 'UTC',
            day: '2-digit',
            month: '2-digit',
            year: '2-digit',
            ...(hasTime ? { hour: '2-digit', minute: '2-digit', hour12: false } : {}),
        });
        const parts = formatter.formatToParts(date);
        const day = parts.find((p) => p.type === 'day')?.value;
        const month = parts.find((p) => p.type === 'month')?.value;
        const year = parts.find((p) => p.type === 'year')?.value;
        
        if (hasTime) {
            const hour = parts.find((p) => p.type === 'hour')?.value;
            const minute = parts.find((p) => p.type === 'minute')?.value;
            return `${day}/${month}/${year} ${hour}:${minute}`;
        }
        return `${day}/${month}/${year}`;
    }
}
