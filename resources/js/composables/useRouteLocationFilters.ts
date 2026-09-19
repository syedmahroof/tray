import { computed, ref } from 'vue';
import type { PlaceFilterValues } from '@/types';

/** The sentinel the place pickers use for "don't filter by this". */
const ALL = 'all';

type PlaceFilterKey = keyof PlaceFilterValues;

const KEYS: PlaceFilterKey[] = [
    'country_id',
    'state_id',
    'district_id',
    'location_id',
    'route_id',
];

/**
 * Hold the country/state/district/location/route filters a listing screen
 * shares with `RouteLocationFilters`, and translate them to query params.
 *
 * @param filters The filter values the server echoed back to the page.
 */
export function useRouteLocationFilters(
    filters: Partial<Record<PlaceFilterKey, string | number>>,
) {
    const blank = () =>
        Object.fromEntries(
            KEYS.map((key) => [key, ALL]),
        ) as unknown as PlaceFilterValues;

    const place = ref<PlaceFilterValues>(
        Object.fromEntries(
            KEYS.map((key) => [key, filters[key] ? String(filters[key]) : ALL]),
        ) as unknown as PlaceFilterValues,
    );

    /** The chosen places as query params, dropping the "all" sentinel. */
    const placeQuery = computed(
        () =>
            Object.fromEntries(
                KEYS.map((key) => [
                    key,
                    place.value[key] !== ALL ? place.value[key] : undefined,
                ]),
            ) as Record<PlaceFilterKey, string | undefined>,
    );

    const placeIsActive = computed(() =>
        KEYS.some((key) => place.value[key] !== ALL),
    );

    const resetPlace = () => {
        place.value = blank();
    };

    return { place, placeQuery, placeIsActive, resetPlace };
}
