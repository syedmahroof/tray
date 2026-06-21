<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index as countriesIndex } from '@/routes/countries';
import { edit, index, update } from '@/routes/countries/states';
import type { Country, State } from '@/types';

const props = defineProps<{
    country: Country;
    state: State;
}>();

defineOptions({
    layout: (props: { country: Country; state: State }) => ({
        breadcrumbs: [
            { title: 'Countries', href: countriesIndex() },
            { title: props.country.name, href: index(props.country.id) },
            {
                title: props.state.name,
                href: edit([props.country.id, props.state.id]),
            },
        ],
    }),
});
</script>

<template>
    <Head :title="`Edit ${state.name}`" />

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            :title="`Edit ${state.name}`"
            :description="`Update this state in ${country.name}`"
        />

        <Form
            v-bind="update.form([props.country.id, props.state.id])"
            class="max-w-xl space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="name">Name</Label>
                <Input
                    id="name"
                    name="name"
                    :default-value="state.name"
                    required
                    autofocus
                />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="code">Code</Label>
                <Input
                    id="code"
                    name="code"
                    :default-value="state.code ?? undefined"
                />
                <InputError :message="errors.code" />
            </div>

            <Button type="submit" :disabled="processing">Save</Button>
        </Form>
    </div>
</template>
