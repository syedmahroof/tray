<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index as countriesIndex } from '@/routes/countries';
import { index as statesIndex } from '@/routes/countries/states';
import { edit, index, update } from '@/routes/districts/locations';
import { index as districtsIndex } from '@/routes/states/districts';
import type { DistrictWithState, Location } from '@/types';

const props = defineProps<{
    district: DistrictWithState;
    location: Location;
}>();

defineOptions({
    layout: (props: { district: DistrictWithState; location: Location }) => ({
        breadcrumbs: [
            { title: 'Countries', href: countriesIndex() },
            {
                title: props.district.state.country.name,
                href: statesIndex(props.district.state.country.id),
            },
            {
                title: props.district.state.name,
                href: districtsIndex(props.district.state.id),
            },
            { title: props.district.name, href: index(props.district.id) },
            {
                title: props.location.name,
                href: edit([props.district.id, props.location.id]),
            },
        ],
    }),
});
</script>

<template>
    <Head :title="`Edit ${location.name}`" />

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            :title="`Edit ${location.name}`"
            :description="`Update this location in ${district.name}`"
        />

        <Form
            v-bind="update.form([props.district.id, props.location.id])"
            class="max-w-xl space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="name">Name</Label>
                <Input
                    id="name"
                    name="name"
                    :default-value="location.name"
                    required
                    autofocus
                />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="pincode">Pincode</Label>
                <Input
                    id="pincode"
                    name="pincode"
                    :default-value="location.pincode ?? ''"
                />
                <InputError :message="errors.pincode" />
            </div>

            <Label for="is_active" class="flex items-center space-x-3">
                <Checkbox
                    id="is_active"
                    name="is_active"
                    :default-value="location.is_active"
                />
                <span>Active</span>
            </Label>

            <Button type="submit" :disabled="processing">Save</Button>
        </Form>
    </div>
</template>
