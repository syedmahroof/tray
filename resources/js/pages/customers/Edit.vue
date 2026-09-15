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
import { RATE_TIERS, RATE_TIER_KEYS } from '@/lib/products';
import { edit, index, show, update } from '@/routes/customers';
import type { Branch, Country, Customer, NamedOption } from '@/types';

const props = defineProps<{
    customer: Customer;
    countries: Country[];
    users: NamedOption[];
    branches: Branch[];
}>();

defineOptions({
    layout: (props: { customer: Customer & { name: string } }) => ({
        breadcrumbs: [
            { title: 'Customers', href: index() },
            { title: props.customer.name, href: show(props.customer.id) },
            { title: 'Edit', href: edit(props.customer.id) },
        ],
    }),
});

const userOptions = computed(() =>
    props.users.map((user) => ({ value: String(user.id), label: user.name })),
);
</script>

<template>
    <Head :title="`Edit ${customer.name}`" />

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            :title="`Edit ${customer.name}`"
            description="Update customer details"
        />

        <Form
            v-bind="update.form(props.customer.id)"
            class="max-w-3xl space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="name">Name</Label>
                    <Input
                        id="name"
                        name="name"
                        :default-value="customer.name"
                        required
                        autofocus
                    />
                    <InputError :message="errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="phone">Phone</Label>
                    <Input
                        id="phone"
                        name="phone"
                        :default-value="customer.phone ?? undefined"
                    />
                    <InputError :message="errors.phone" />
                </div>

                <div class="grid gap-2">
                    <Label for="email">Email</Label>
                    <Input
                        id="email"
                        name="email"
                        type="email"
                        :default-value="customer.email ?? undefined"
                    />
                    <InputError :message="errors.email" />
                </div>

                <div class="grid content-start gap-2">
                    <Label for="rate_tier">Price Type</Label>
                    <Select
                        name="rate_tier"
                        :default-value="customer.rate_tier ?? 'none'"
                    >
                        <SelectTrigger id="rate_tier" class="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="none">No default</SelectItem>
                            <SelectItem
                                v-for="tier in RATE_TIER_KEYS"
                                :key="tier"
                                :value="tier"
                            >
                                {{ tier }} — {{ RATE_TIERS[tier] }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <p class="text-xs text-muted-foreground">
                        New quotations for this customer are priced at this
                        rate.
                    </p>
                    <InputError :message="errors.rate_tier" />
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="gst_number">GST Number</Label>
                    <Input
                        id="gst_number"
                        name="gst_number"
                        placeholder="e.g. 29ABCDE1234F1Z5"
                        :default-value="customer.gst_number ?? undefined"
                    />
                    <InputError :message="errors.gst_number" />
                </div>

                <div class="grid gap-2">
                    <Label for="address">Address</Label>
                    <Input
                        id="address"
                        name="address"
                        :default-value="customer.address ?? undefined"
                    />
                    <InputError :message="errors.address" />
                </div>
            </div>

            <LocationSelect
                :countries="countries"
                :initial-country-id="customer.country_id"
                :initial-state-id="customer.state_id"
                :initial-district-id="customer.district_id"
            />

            <div class="grid gap-2">
                <Label for="assigned_to">Assigned to</Label>
                <Combobox
                    name="assigned_to"
                    placeholder="Select a user"
                    :options="userOptions"
                    :model-value="
                        customer.assigned_to
                            ? String(customer.assigned_to)
                            : undefined
                    "
                />
                <InputError :message="errors.assigned_to" />
            </div>

            <div v-if="branches.length > 0" class="grid gap-2">
                <Label for="branch_id">Branch</Label>
                <Select
                    name="branch_id"
                    :default-value="String(customer.branch_id)"
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
        </Form>
    </div>
</template>
