<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index as countriesIndex } from '@/routes/countries';
import { index as statesIndex } from '@/routes/countries/states';
import { edit, index, update } from '@/routes/states/districts';
import type { District, StateWithCountry } from '@/types';

const props = defineProps<{
    state: StateWithCountry;
    district: District;
}>();

defineOptions({
    layout: (props: { state: StateWithCountry; district: District }) => ({
        breadcrumbs: [
            { title: 'Countries', href: countriesIndex() },
            {
                title: props.state.country.name,
                href: statesIndex(props.state.country.id),
            },
            { title: props.state.name, href: index(props.state.id) },
            {
                title: props.district.name,
                href: edit([props.state.id, props.district.id]),
            },
        ],
    }),
});
</script>

<template>
    <Head :title="`Edit ${district.name}`" />

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            :title="`Edit ${district.name}`"
            :description="`Update this district in ${state.name}`"
        />

        <Form
            v-bind="update.form([props.state.id, props.district.id])"
            class="max-w-xl space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="name">Name</Label>
                <Input
                    id="name"
                    name="name"
                    :default-value="district.name"
                    required
                    autofocus
                />
                <InputError :message="errors.name" />
            </div>

            <Button type="submit" :disabled="processing">Save</Button>
        </Form>
    </div>
</template>
