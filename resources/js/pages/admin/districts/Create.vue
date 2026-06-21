<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index as countriesIndex } from '@/routes/countries';
import { index as statesIndex } from '@/routes/countries/states';
import { create, index, store } from '@/routes/states/districts';
import type { StateWithCountry } from '@/types';

const props = defineProps<{
    state: StateWithCountry;
}>();

defineOptions({
    layout: (props: { state: StateWithCountry }) => ({
        breadcrumbs: [
            { title: 'Countries', href: countriesIndex() },
            {
                title: props.state.country.name,
                href: statesIndex(props.state.country.id),
            },
            { title: props.state.name, href: index(props.state.id) },
            { title: 'New district', href: create(props.state.id) },
        ],
    }),
});
</script>

<template>
    <Head title="New district" />

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            title="New district"
            :description="`Create a new district in ${state.name}`"
        />

        <Form
            v-bind="store.form(props.state.id)"
            class="max-w-xl space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="name">Name</Label>
                <Input id="name" name="name" required autofocus />
                <InputError :message="errors.name" />
            </div>

            <Button type="submit" :disabled="processing">
                Create district
            </Button>
        </Form>
    </div>
</template>
