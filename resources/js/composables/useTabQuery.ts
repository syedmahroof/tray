import { ref, watch } from 'vue';

/**
 * Keep the active tab in the URL (`?tab=…`) so it survives a page refresh and
 * can be shared/bookmarked. The URL is updated in place (history.replaceState)
 * without a server round-trip, preserving Inertia's history state.
 *
 * @param allowed  The valid tab values for this page.
 * @param fallback The tab to use when the URL has no (valid) value.
 * @param key      The query-string key to store the tab under.
 */
export function useTabQuery(
    allowed: readonly string[],
    fallback: string,
    key = 'tab',
) {
    const readFromUrl = (): string => {
        if (typeof window === 'undefined') {
            return fallback;
        }

        const value = new URLSearchParams(window.location.search).get(key);

        return value && allowed.includes(value) ? value : fallback;
    };

    const tab = ref(readFromUrl());

    watch(tab, (value) => {
        if (typeof window === 'undefined') {
            return;
        }

        const url = new URL(window.location.href);

        if (value === fallback) {
            url.searchParams.delete(key);
        } else {
            url.searchParams.set(key, value);
        }

        // Preserve Inertia's existing history state so back/forward keeps working.
        window.history.replaceState(
            window.history.state,
            '',
            `${url.pathname}${url.search}${url.hash}`,
        );
    });

    return tab;
}
