<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import LocationSelect from '@/components/LocationSelect.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { create, index, store } from '@/routes/builders';
import type { Branch, Country } from '@/types';

defineProps<{
    countries: Country[];
    branches: Branch[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Builders', href: index() },
            { title: 'New builder', href: create() },
        ],
    },
});
</script>

<template>
    <Head title="New builder" />

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            title="New builder"
            description="Create a new builder"
        />

        <Form
            v-bind="store.form()"
            class="max-w-3xl space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="name">Name</Label>
                <Input id="name" name="name" required autofocus />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="contact_person">Contact person</Label>
                    <Input id="contact_person" name="contact_person" />
                    <InputError :message="errors.contact_person" />
                </div>

                <div class="grid gap-2">
                    <Label for="phone">Phone</Label>
                    <Input id="phone" name="phone" />
                    <InputError :message="errors.phone" />
                </div>

                <div class="grid gap-2">
                    <Label for="email">Email</Label>
                    <Input id="email" name="email" type="email" />
                    <InputError :message="errors.email" />
                </div>

                <div class="grid gap-2">
                    <Label for="address">Address</Label>
                    <Input id="address" name="address" />
                    <InputError :message="errors.address" />
                </div>
            </div>

            <LocationSelect :countries="countries" />

            <div v-if="branches.length > 0" class="grid gap-2">
                <Label for="branch_id">Branch</Label>
                <Select name="branch_id">
                    <SelectTrigger class="w-full">
                        <SelectValue placeholder="Select a branch" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="branch in branches"
                            :key="branch.id"
                            :value="String(branch.id)"
                        >
                            {{ branch.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="errors.branch_id" />
            </div>

            <Label for="is_active" class="flex items-center space-x-3">
                <Checkbox
                    id="is_active"
                    name="is_active"
                    :default-value="true"
                />
                <span>Active</span>
            </Label>

            <Button type="submit" :disabled="processing">
                Create builder
            </Button>
        </Form>
    </div>
</template>
