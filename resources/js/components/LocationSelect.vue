<script setup lang="ts">
import { useHttp, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import Combobox from '@/components/Combobox.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Country, Route } from '@/types';

/** The shape every tier of the location tree is picked by. */
type Place = { id: number; name: string };

const props = withDefaults(
    defineProps<{
        countries: Country[];
        /** The shared route master; omit to hide the route picker. */
        routes?: Route[];
        initialCountryId?: number | null;
        initialStateId?: number | null;
        initialDistrictId?: number | null;
        initialLocationId?: number | null;
        initialRouteId?: number | null;
        hideCountry?: boolean;
    }>(),
    {
        routes: () => [],
        hideCountry: false,
    },
);

/**
 * India is this CRM's home country, so it is preselected whenever nothing
 * else is. Falls back to the first country when India is not seeded.
 */
const defaultCountryId = computed(() => {
    const india = props.countries.find(
        (country) =>
            country.code?.toUpperCase() === 'IN' || country.name === 'India',
    );

    const fallback = india ?? props.countries[0];

    return fallback ? String(fallback.id) : undefined;
});

const countryId = ref<string | undefined>(
    props.initialCountryId ? String(props.initialCountryId) : undefined,
);
const stateId = ref<string | undefined>(
    props.initialStateId ? String(props.initialStateId) : undefined,
);
const districtId = ref<string | undefined>(
    props.initialDistrictId ? String(props.initialDistrictId) : undefined,
);
const locationId = ref<string | undefined>(
    props.initialLocationId ? String(props.initialLocationId) : undefined,
);
const routeId = ref<string | undefined>(
    props.initialRouteId ? String(props.initialRouteId) : undefined,
);

watch(
    defaultCountryId,
    (value) => {
        if (!countryId.value && value) {
            countryId.value = value;
        }
    },
    { immediate: true },
);

const states = ref<Place[]>([]);
const districts = ref<Place[]>([]);
const locations = ref<Place[]>([]);

const statesHttp = useHttp();
const districtsHttp = useHttp();
const locationsHttp = useHttp();

function loadStates(id: string | undefined) {
    states.value = [];

    if (!id) {
        return;
    }

    statesHttp.get(`/location/states?country_id=${id}`, {
        onSuccess: (response) => {
            states.value = response as Place[];
        },
    });
}

function loadDistricts(id: string | undefined) {
    districts.value = [];

    if (!id) {
        return;
    }

    districtsHttp.get(`/location/districts?state_id=${id}`, {
        onSuccess: (response) => {
            districts.value = response as Place[];
        },
    });
}

function loadLocations(id: string | undefined) {
    locations.value = [];

    if (!id) {
        return;
    }

    locationsHttp.get(`/location/locations?district_id=${id}`, {
        onSuccess: (response) => {
            locations.value = response as Place[];
        },
    });
}

onMounted(() => {
    loadStates(countryId.value);
    loadDistricts(stateId.value);
    loadLocations(districtId.value);
});

watch(countryId, (value, previous) => {
    if (value === previous) {
        return;
    }

    stateId.value = undefined;
    districtId.value = undefined;
    locationId.value = undefined;
    loadStates(value);
});

watch(stateId, (value, previous) => {
    if (value === previous) {
        return;
    }

    districtId.value = undefined;
    locationId.value = undefined;
    loadDistricts(value);
});

watch(districtId, (value, previous) => {
    if (value === previous) {
        return;
    }

    locationId.value = undefined;
    loadLocations(value);
});

const toOptions = (items: { id: number; name: string }[]) =>
    items.map((item) => ({ value: String(item.id), label: item.name }));

const countryOptions = computed(() => toOptions(props.countries));
const stateOptions = computed(() => toOptions(states.value));
const districtOptions = computed(() => toOptions(districts.value));
const locationOptions = computed(() => toOptions(locations.value));
const routeOptions = computed(() => toOptions(props.routes));

/** The tiers a missing place can be added at, without leaving this form. */
type Tier = 'state' | 'district' | 'location';

const tierLabels: Record<Tier, string> = {
    state: 'state',
    district: 'district',
    location: 'location',
};

const permissions = computed(() => usePage().props.auth.permissions);

const canCreate = (tier: Tier) => permissions.value.includes(`${tier}s.create`);

/**
 * The label for a tier's inline create action, or undefined when the user may
 * not add one or has not picked its parent yet.
 */
const createLabelFor = (tier: Tier): string | undefined => {
    const parentPicked = {
        state: countryId.value,
        district: stateId.value,
        location: districtId.value,
    }[tier];

    return parentPicked && canCreate(tier)
        ? `Add a new ${tierLabels[tier]}`
        : undefined;
};

const tierBeingCreated = ref<Tier | null>(null);

const createForm = useHttp<
    { name: string; code: string; pincode: string },
    Place
>({
    name: '',
    code: '',
    pincode: '',
});

const openCreate = async (tier: Tier, term: string) => {
    createForm.reset();
    createForm.clearErrors();
    createForm.name = term;

    // Let the picker's popover finish closing before the dialog takes focus.
    await nextTick();

    tierBeingCreated.value = tier;
};

const createEndpoint = computed(() => {
    switch (tierBeingCreated.value) {
        case 'state':
            return `/location/countries/${countryId.value}/states`;
        case 'district':
            return `/location/states/${stateId.value}/districts`;
        case 'location':
            return `/location/districts/${districtId.value}/locations`;
        default:
            return null;
    }
});

const createParentName = computed(() => {
    switch (tierBeingCreated.value) {
        case 'state':
            return countryOptions.value.find(
                (option) => option.value === countryId.value,
            )?.label;
        case 'district':
            return stateOptions.value.find(
                (option) => option.value === stateId.value,
            )?.label;
        case 'location':
            return districtOptions.value.find(
                (option) => option.value === districtId.value,
            )?.label;
        default:
            return undefined;
    }
});

const byName = (a: Place, b: Place) => a.name.localeCompare(b.name);

/** Slot the freshly created place into its picker and select it. */
const selectCreated = (tier: Tier, created: Place) => {
    if (tier === 'state') {
        states.value = [...states.value, created].sort(byName);
        stateId.value = String(created.id);

        return;
    }

    if (tier === 'district') {
        districts.value = [...districts.value, created].sort(byName);
        districtId.value = String(created.id);

        return;
    }

    locations.value = [...locations.value, created].sort(byName);
    locationId.value = String(created.id);
};

const submitCreate = () => {
    const tier = tierBeingCreated.value;
    const endpoint = createEndpoint.value;

    if (!tier || !endpoint) {
        return;
    }

    createForm.post(endpoint, {
        onSuccess: (created) => {
            selectCreated(tier, created as Place);
            tierBeingCreated.value = null;
        },
    });
};
</script>

<template>
    <div>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div v-if="!hideCountry" class="grid gap-2">
                <Label for="country_id">Country</Label>
                <Combobox
                    v-model="countryId"
                    name="country_id"
                    placeholder="Select a country"
                    :options="countryOptions"
                />
            </div>
            <div class="grid gap-2">
                <Label for="state_id">State</Label>
                <Combobox
                    v-model="stateId"
                    name="state_id"
                    placeholder="Select a state"
                    :disabled="!countryId"
                    :options="stateOptions"
                    :create-label="createLabelFor('state')"
                    @create="openCreate('state', $event)"
                />
            </div>
            <div class="grid gap-2">
                <Label for="district_id">District</Label>
                <Combobox
                    v-model="districtId"
                    name="district_id"
                    placeholder="Select a district"
                    :disabled="!stateId"
                    :options="districtOptions"
                    :create-label="createLabelFor('district')"
                    @create="openCreate('district', $event)"
                />
            </div>
            <div class="grid gap-2">
                <Label for="location_id">Location</Label>
                <Combobox
                    v-model="locationId"
                    name="location_id"
                    placeholder="Select a location"
                    empty-text="No locations in this district yet."
                    :disabled="!districtId"
                    :options="locationOptions"
                    :create-label="createLabelFor('location')"
                    @create="openCreate('location', $event)"
                />
            </div>
            <div v-if="routes.length > 0" class="grid gap-2">
                <Label for="route_id">Route</Label>
                <Combobox
                    v-model="routeId"
                    name="route_id"
                    placeholder="Select a route"
                    :options="routeOptions"
                />
            </div>
        </div>

        <Dialog
            :open="tierBeingCreated !== null"
            @update:open="
                (value) => (tierBeingCreated = value ? tierBeingCreated : null)
            "
        >
            <DialogContent v-if="tierBeingCreated">
                <form class="space-y-6" @submit.prevent="submitCreate">
                    <DialogHeader>
                        <DialogTitle class="capitalize">
                            New {{ tierLabels[tierBeingCreated] }}
                        </DialogTitle>
                        <DialogDescription v-if="createParentName">
                            Added under {{ createParentName }} and selected here
                            straight away.
                        </DialogDescription>
                    </DialogHeader>

                    <div class="grid gap-2">
                        <Label for="new-place-name">Name</Label>
                        <Input
                            id="new-place-name"
                            v-model="createForm.name"
                            required
                            autofocus
                        />
                        <InputError :message="createForm.errors.name" />
                    </div>

                    <div v-if="tierBeingCreated === 'state'" class="grid gap-2">
                        <Label for="new-place-code">Code</Label>
                        <Input id="new-place-code" v-model="createForm.code" />
                        <InputError :message="createForm.errors.code" />
                    </div>

                    <div
                        v-if="tierBeingCreated === 'location'"
                        class="grid gap-2"
                    >
                        <Label for="new-place-pincode">Pincode</Label>
                        <Input
                            id="new-place-pincode"
                            v-model="createForm.pincode"
                        />
                        <InputError :message="createForm.errors.pincode" />
                    </div>

                    <DialogFooter class="gap-2">
                        <Button
                            type="button"
                            variant="secondary"
                            @click="tierBeingCreated = null"
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            data-test="create-place"
                            :disabled="createForm.processing"
                        >
                            Create
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
