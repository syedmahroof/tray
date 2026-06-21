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
import { edit, index, update } from '@/routes/builders';
import type { Branch, Builder, Country } from '@/types';

const props = defineProps<{
    builder: Builder;
    countries: Country[];
    branches: Branch[];
}>();

defineOptions({
    layout: (props: { builder: Builder }) => ({
        breadcrumbs: [
            { title: 'Builders', href: index() },
            { title: props.builder.name, href: edit(props.builder.id) },
        ],
    }),
});
</script>

<template>
    <Head :title="`Edit ${builder.name}`" />

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            :title="`Edit ${builder.name}`"
            description="Update builder details"
        />

        <Form
            v-bind="update.form(props.builder.id)"
            class="max-w-3xl space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="name">Name</Label>
                <Input
                    id="name"
                    name="name"
                    :default-value="builder.name"
                    required
                    autofocus
                />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="contact_person">Contact person</Label>
                    <Input
                        id="contact_person"
                        name="contact_person"
                        :default-value="builder.contact_person ?? undefined"
                    />
                    <InputError :message="errors.contact_person" />
                </div>

                <div class="grid gap-2">
                    <Label for="phone">Phone</Label>
                    <Input
                        id="phone"
                        name="phone"
                        :default-value="builder.phone ?? undefined"
                    />
                    <InputError :message="errors.phone" />
                </div>

                <div class="grid gap-2">
                    <Label for="email">Email</Label>
                    <Input
                        id="email"
                        name="email"
                        type="email"
                        :default-value="builder.email ?? undefined"
                    />
                    <InputError :message="errors.email" />
                </div>

                <div class="grid gap-2">
                    <Label for="address">Address</Label>
                    <Input
                        id="address"
                        name="address"
                        :default-value="builder.address ?? undefined"
                    />
                    <InputError :message="errors.address" />
                </div>
            </div>

            <LocationSelect
                :countries="countries"
                :initial-country-id="builder.country_id"
                :initial-state-id="builder.state_id"
                :initial-district-id="builder.district_id"
            />

            <div v-if="branches.length > 0" class="grid gap-2">
                <Label for="branch_id">Branch</Label>
                <Select
                    name="branch_id"
                    :default-value="String(builder.branch_id)"
                >
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
                    :default-value="builder.is_active"
                />
                <span>Active</span>
            </Label>

            <Button type="submit" :disabled="processing">Save</Button>
        </Form>
    </div>
</template>
