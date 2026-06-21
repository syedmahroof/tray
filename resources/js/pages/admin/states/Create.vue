<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index as countriesIndex } from '@/routes/countries';
import { create, index, store } from '@/routes/countries/states';
import type { Country } from '@/types';

const props = defineProps<{
    country: Country;
}>();

defineOptions({
    layout: (props: { country: Country }) => ({
        breadcrumbs: [
            { title: 'Countries', href: countriesIndex() },
            { title: props.country.name, href: index(props.country.id) },
            { title: 'New state', href: create(props.country.id) },
        ],
    }),
});
</script>

<template>
    <Head title="New state" />

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            title="New state"
            :description="`Create a new state in ${country.name}`"
        />

        <Form
            v-bind="store.form(props.country.id)"
            class="max-w-xl space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="name">Name</Label>
                <Input id="name" name="name" required autofocus />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="code">Code</Label>
                <Input id="code" name="code" />
                <InputError :message="errors.code" />
            </div>

            <Button type="submit" :disabled="processing">Create state</Button>
        </Form>
    </div>
</template>
