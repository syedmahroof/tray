<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import Combobox from '@/components/Combobox.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import LocationSelect from '@/components/LocationSelect.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { create, index, store } from '@/routes/customers';
import type { Branch, Country, NamedOption } from '@/types';

const props = defineProps<{
    countries: Country[];
    users: NamedOption[];
    branches: Branch[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Customers', href: index() },
            { title: 'New customer', href: create() },
        ],
    },
});

const userOptions = computed(() =>
    props.users.map((user) => ({ value: String(user.id), label: user.name })),
);
</script>

<template>
    <Head title="New customer" />

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            title="New customer"
            description="Create a new customer"
        />

        <Form
            v-bind="store.form()"
            class="max-w-3xl space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="name">Name</Label>
                    <Input id="name" name="name" required autofocus />
                    <InputError :message="errors.name" />
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
            </div>

            <div class="grid gap-2">
                <Label for="address">Address</Label>
                <Input id="address" name="address" />
                <InputError :message="errors.address" />
            </div>

            <LocationSelect :countries="countries" />

            <div class="grid gap-2">
                <Label for="assigned_to">Assigned to</Label>
                <Combobox
                    name="assigned_to"
                    placeholder="Select a user"
                    :options="userOptions"
                />
                <InputError :message="errors.assigned_to" />
            </div>

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

            <Button type="submit" :disabled="processing">
                Create customer
            </Button>
        </Form>
    </div>
</template>
