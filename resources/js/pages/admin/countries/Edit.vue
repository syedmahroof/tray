<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit, index, update } from '@/routes/countries';
import type { Country } from '@/types';

const props = defineProps<{
    country: Country;
}>();

defineOptions({
    layout: (props: { country: Country }) => ({
        breadcrumbs: [
            { title: 'Countries', href: index() },
            { title: props.country.name, href: edit(props.country.id) },
        ],
    }),
});
</script>

<template>
    <Head :title="`Edit ${country.name}`" />

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            :title="`Edit ${country.name}`"
            description="Update country details"
        />

        <Form
            v-bind="update.form(props.country.id)"
            class="max-w-xl space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="name">Name</Label>
                <Input
                    id="name"
                    name="name"
                    :default-value="country.name"
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
                    :default-value="country.code ?? undefined"
                />
                <InputError :message="errors.code" />
            </div>

            <Button type="submit" :disabled="processing">Save</Button>
        </Form>
    </div>
</template>
