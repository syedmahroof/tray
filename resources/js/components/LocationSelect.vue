<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import { computed, ref, watch, onMounted } from 'vue';
import Combobox from '@/components/Combobox.vue';
import { Label } from '@/components/ui/label';
import type { Country, District, State } from '@/types';

const props = withDefaults(
    defineProps<{
        countries: Country[];
        initialCountryId?: number | null;
        initialStateId?: number | null;
        initialDistrictId?: number | null;
        hideCountry?: boolean;
    }>(),
    {
        hideCountry: false,
    },
);

const defaultCountryId = computed(() => {
    return props.countries.length > 0
        ? String(props.countries[0].id)
        : undefined;
});

const countryId = ref<string | undefined>(
    props.initialCountryId ? String(props.initialCountryId) : undefined,
);

watch(
    defaultCountryId,
    (newVal) => {
        if (props.hideCountry && !countryId.value && newVal) {
            countryId.value = newVal;
        }
    },
    { immediate: true },
);
const stateId = ref<string | undefined>(
    props.initialStateId ? String(props.initialStateId) : undefined,
);
const districtId = ref<string | undefined>(
    props.initialDistrictId ? String(props.initialDistrictId) : undefined,
);

const states = ref<State[]>([]);
const districts = ref<District[]>([]);

const statesHttp = useHttp();
const districtsHttp = useHttp();

function loadStates(id: string | undefined) {
    states.value = [];

    if (!id) {
        return;
    }

    statesHttp.get(`/location/states?country_id=${id}`, {
        onSuccess: (response) => {
            states.value = response as State[];
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
            districts.value = response as District[];
        },
    });
}

onMounted(() => {
    loadStates(countryId.value);
    loadDistricts(stateId.value);
});

watch(countryId, (value, previous) => {
    if (value === previous) {
        return;
    }

    stateId.value = undefined;
    districtId.value = undefined;
    loadStates(value);
});

watch(stateId, (value, previous) => {
    if (value === previous) {
        return;
    }

    districtId.value = undefined;
    loadDistricts(value);
});

const countryOptions = computed(() =>
    props.countries.map((country) => ({
        value: String(country.id),
        label: country.name,
    })),
);
const stateOptions = computed(() =>
    states.value.map((state) => ({
        value: String(state.id),
        label: state.name,
    })),
);
const districtOptions = computed(() =>
    districts.value.map((district) => ({
        value: String(district.id),
        label: district.name,
    })),
);
</script>

<template>
    <div
        :class="[
            'grid gap-4',
            hideCountry ? 'sm:grid-cols-2' : 'sm:grid-cols-3',
        ]"
    >
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
            />
        </div>
    </div>
</template>
