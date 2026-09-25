<script setup lang="ts">
import { computed } from 'vue';
import Combobox from '@/components/Combobox.vue';
import type { PlaceFilterOptions, PlaceFilterValues } from '@/types';

const props = defineProps<PlaceFilterOptions>();

const place = defineModel<PlaceFilterValues>({ required: true });

const emit = defineEmits<{ change: [] }>();

/** The tiers of the location tree, widest first. */
const TIERS = ['country_id', 'state_id', 'district_id', 'location_id'] as const;

/**
 * Narrowing by a tier makes anything chosen below it meaningless, so the
 * tiers under the one being set are cleared.
 *
 * Combobox clears to undefined when its selected row is clicked again, which
 * for a filter means "no filter" — the same thing as the "all" sentinel.
 */
const pick = (key: keyof PlaceFilterValues, value: string | undefined) => {
    const next = { ...place.value, [key]: value ?? 'all' };
    const tier = TIERS.indexOf(key as (typeof TIERS)[number]);

    if (tier !== -1) {
        for (const narrower of TIERS.slice(tier + 1)) {
            next[narrower] = 'all';
        }
    }

    place.value = next;
    emit('change');
};

const bind = (key: keyof PlaceFilterValues) =>
    computed({
        get: () => place.value[key],
        set: (value: string | undefined) => pick(key, value),
    });

const selectedCountry = bind('country_id');
const selectedState = bind('state_id');
const selectedDistrict = bind('district_id');
const selectedLocation = bind('location_id');
const selectedRoute = bind('route_id');

const visibleStates = computed(() =>
    props.states.filter(
        (state) =>
            place.value.country_id === 'all' ||
            String(state.country_id) === place.value.country_id,
    ),
);

const visibleDistricts = computed(() => {
    const stateIds = new Set(visibleStates.value.map((state) => state.id));

    return props.districts.filter((district) => {
        if (place.value.state_id !== 'all') {
            return String(district.state_id) === place.value.state_id;
        }

        return (
            place.value.country_id === 'all' || stateIds.has(district.state_id)
        );
    });
});

const visibleLocations = computed(() => {
    const districtIds = new Set(
        visibleDistricts.value.map((district) => district.id),
    );

    return props.locations.filter((location) => {
        if (place.value.district_id !== 'all') {
            return String(location.district_id) === place.value.district_id;
        }

        return (
            (place.value.country_id === 'all' &&
                place.value.state_id === 'all') ||
            districtIds.has(location.district_id)
        );
    });
});

const withAll = (
    label: string,
    options: { value: string; label: string }[],
) => [{ value: 'all', label }, ...options];

const countryOptions = computed(() =>
    withAll(
        'All countries',
        props.countries.map((country) => ({
            value: String(country.id),
            label: country.name,
        })),
    ),
);

const stateOptions = computed(() =>
    withAll(
        'All states',
        visibleStates.value.map((state) => ({
            value: String(state.id),
            label: state.name,
        })),
    ),
);

const districtOptions = computed(() =>
    withAll(
        'All districts',
        visibleDistricts.value.map((district) => ({
            value: String(district.id),
            label: district.name,
        })),
    ),
);

const locationOptions = computed(() =>
    withAll(
        'All locations',
        visibleLocations.value.map((location) => ({
            value: String(location.id),
            label: `${location.name} — ${location.district.name}`,
        })),
    ),
);

const routeOptions = computed(() =>
    withAll(
        'All routes',
        props.routes.map((route) => ({
            value: String(route.id),
            label: route.name,
        })),
    ),
);
</script>

<template>
    <div
        v-if="countries.length > 0"
        class="w-full sm:w-[160px]"
        data-test="country-filter"
    >
        <Combobox
            v-model="selectedCountry"
            placeholder="All countries"
            empty-text="No countries found."
            :options="countryOptions"
        />
    </div>

    <div
        v-if="states.length > 0"
        class="w-full sm:w-[160px]"
        data-test="state-filter"
    >
        <Combobox
            v-model="selectedState"
            placeholder="All states"
            empty-text="No states found."
            :options="stateOptions"
        />
    </div>

    <div
        v-if="districts.length > 0"
        class="w-full sm:w-[180px]"
        data-test="district-filter"
    >
        <Combobox
            v-model="selectedDistrict"
            placeholder="All districts"
            empty-text="No districts found."
            :options="districtOptions"
        />
    </div>

    <div class="w-full sm:w-[200px]" data-test="location-filter">
        <Combobox
            v-model="selectedLocation"
            placeholder="All locations"
            empty-text="No locations found."
            :options="locationOptions"
        />
    </div>

    <div class="w-full sm:w-[180px]" data-test="route-filter">
        <Combobox
            v-model="selectedRoute"
            placeholder="All routes"
            empty-text="No routes found."
            :options="routeOptions"
        />
    </div>
</template>
