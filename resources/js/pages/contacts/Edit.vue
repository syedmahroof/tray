<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import Combobox from '@/components/Combobox.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import LocationSelect from '@/components/LocationSelect.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { edit, index, show, update } from '@/routes/contacts';
import type { Branch, Contact, Country, NamedOption } from '@/types';

const props = defineProps<{
    contact: Contact;
    contactTypes: NamedOption[];
    countries: Country[];
    users: NamedOption[];
    branches: Branch[];
}>();

defineOptions({
    layout: (props: { contact: Contact & { name: string } }) => ({
        breadcrumbs: [
            { title: 'Contacts', href: index() },
            { title: props.contact.name, href: show(props.contact.id) },
            { title: 'Edit', href: edit(props.contact.id) },
        ],
    }),
});

const userOptions = computed(() =>
    props.users.map((user) => ({ value: String(user.id), label: user.name })),
);
</script>

<template>
    <Head :title="`Edit ${contact.name}`" />

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            :title="`Edit ${contact.name}`"
            description="Update contact details"
        />

        <Form
            v-bind="update.form(props.contact.id)"
            class="max-w-3xl space-y-6"
            v-slot="{ errors, processing }"
        >
            <Card>
                <CardContent class="space-y-6 pt-6">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="name">Name</Label>
                            <Input
                                id="name"
                                name="name"
                                :default-value="contact.name"
                                required
                                autofocus
                            />
                            <InputError :message="errors.name" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="contact_type_id">Type</Label>
                            <Select
                                name="contact_type_id"
                                :default-value="String(contact.contact_type_id)"
                            >
                                <SelectTrigger class="w-full">
                                    <SelectValue placeholder="Select a type" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="contactType in contactTypes"
                                        :key="contactType.id"
                                        :value="String(contactType.id)"
                                    >
                                        {{ contactType.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="errors.contact_type_id" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="phone">Phone</Label>
                            <Input
                                id="phone"
                                name="phone"
                                :default-value="contact.phone ?? undefined"
                            />
                            <InputError :message="errors.phone" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="email">Email</Label>
                            <Input
                                id="email"
                                name="email"
                                type="email"
                                :default-value="contact.email ?? undefined"
                            />
                            <InputError :message="errors.email" />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="address">Address</Label>
                        <Input
                            id="address"
                            name="address"
                            :default-value="contact.address ?? undefined"
                        />
                        <InputError :message="errors.address" />
                    </div>

                    <LocationSelect
                        :countries="countries"
                        :initial-country-id="contact.country_id"
                        :initial-state-id="contact.state_id"
                        :initial-district-id="contact.district_id"
                    />

                    <div class="grid gap-2">
                        <Label for="assigned_to">Assigned to</Label>
                        <Combobox
                            name="assigned_to"
                            placeholder="Select a user"
                            :options="userOptions"
                            :model-value="
                                contact.assigned_to
                                    ? String(contact.assigned_to)
                                    : undefined
                            "
                        />
                        <InputError :message="errors.assigned_to" />
                    </div>

                    <div v-if="branches.length > 0" class="grid gap-2">
                        <Label for="branch_id">Branch</Label>
                        <Select
                            name="branch_id"
                            :default-value="String(contact.branch_id)"
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

                    <Button type="submit" :disabled="processing">Save</Button>
                </CardContent>
            </Card>
        </Form>
    </div>
</template>
