<script setup lang="ts">
import { useHttp, usePage } from '@inertiajs/vue3';
import { computed, nextTick, ref, watch } from 'vue';
import Combobox from '@/components/Combobox.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { DistrictOption, LocationWithDistrict } from '@/types';

const props = withDefaults(
    defineProps<{
        locations: LocationWithDistrict[];
        /** The districts a new location may be added to; omit to hide the add action. */
        districts?: DistrictOption[];
        name?: string;
        placeholder?: string;
        initialLocationId?: number | null;
    }>(),
    {
        districts: () => [],
        placeholder: 'Select a location…',
    },
);

const locationId = ref<string | undefined>(
    props.initialLocationId ? String(props.initialLocationId) : undefined,
);

/** Kept locally so a location added from here shows up without a page reload. */
const locations = ref<LocationWithDistrict[]>([...props.locations]);

watch(
    () => props.locations,
    (value) => {
        locations.value = [...value];
    },
);

const locationOptions = computed(() =>
    locations.value.map((location) => ({
        value: String(location.id),
        label: `${location.name} — ${location.district.name}`,
    })),
);

const districtOptions = computed(() =>
    props.districts.map((district) => ({
        value: String(district.id),
        label: `${district.name} — ${district.state.name}`,
    })),
);

const permissions = computed(() => usePage().props.auth.permissions);

const createLabel = computed(() =>
    props.districts.length > 0 && permissions.value.includes('locations.create')
        ? 'Add a new location'
        : undefined,
);

const creating = ref(false);
const districtId = ref<string | undefined>();

const createForm = useHttp<
    { name: string; pincode: string },
    { id: number; name: string }
>({
    name: '',
    pincode: '',
});

const openCreate = async (term: string) => {
    createForm.reset();
    createForm.clearErrors();
    createForm.name = term;
    districtId.value = undefined;

    // Let the picker's popover finish closing before the dialog takes focus.
    await nextTick();

    creating.value = true;
};

const submitCreate = () => {
    const district = props.districts.find(
        (option) => String(option.id) === districtId.value,
    );

    if (!district) {
        return;
    }

    createForm.post(`/location/districts/${district.id}/locations`, {
        onSuccess: (created) => {
            const location = created as { id: number; name: string };

            locations.value = [
                ...locations.value,
                {
                    id: location.id,
                    name: location.name,
                    district_id: district.id,
                    district: { id: district.id, name: district.name },
                    pincode: createForm.pincode || null,
                    is_active: true,
                },
            ].sort((a, b) => a.name.localeCompare(b.name));

            locationId.value = String(location.id);
            creating.value = false;
        },
    });
};
</script>

<template>
    <div>
        <Combobox
            v-model="locationId"
            :name="name"
            :placeholder="placeholder"
            empty-text="No locations found."
            :options="locationOptions"
            :create-label="createLabel"
            @create="openCreate"
        />

        <Dialog :open="creating" @update:open="(value) => (creating = value)">
            <DialogContent>
                <form class="space-y-6" @submit.prevent="submitCreate">
                    <DialogHeader>
                        <DialogTitle>New location</DialogTitle>
                    </DialogHeader>

                    <div class="grid gap-2">
                        <Label for="new-location-district">District</Label>
                        <Combobox
                            v-model="districtId"
                            placeholder="Select a district"
                            :options="districtOptions"
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label for="new-location-name">Name</Label>
                        <Input
                            id="new-location-name"
                            v-model="createForm.name"
                            required
                        />
                        <InputError :message="createForm.errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="new-location-pincode">Pincode</Label>
                        <Input
                            id="new-location-pincode"
                            v-model="createForm.pincode"
                        />
                        <InputError :message="createForm.errors.pincode" />
                    </div>

                    <DialogFooter class="gap-2">
                        <Button
                            type="button"
                            variant="secondary"
                            @click="creating = false"
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            data-test="create-location"
                            :disabled="!districtId || createForm.processing"
                        >
                            Create
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
