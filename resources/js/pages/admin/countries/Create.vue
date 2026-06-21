<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { create, index, store } from '@/routes/countries';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Countries', href: index() },
            { title: 'New country', href: create() },
        ],
    },
});
</script>

<template>
    <Head title="New country" />

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            title="New country"
            description="Create a new country"
        />

        <Form
            v-bind="store.form()"
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
                <Input id="code" name="code" placeholder="e.g. IN" />
                <InputError :message="errors.code" />
            </div>

            <Button type="submit" :disabled="processing">
                Create country
            </Button>
        </Form>
    </div>
</template>
