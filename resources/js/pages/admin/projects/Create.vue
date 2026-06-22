<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { Plus, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import Combobox from '@/components/Combobox.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import LocationSelect from '@/components/LocationSelect.vue';
import MultiCombobox from '@/components/MultiCombobox.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { create, index, store } from '@/routes/projects';
import type { Branch, ContactListItem, Country, NamedOption } from '@/types';

const props = defineProps<{
    builders: NamedOption[];
    projectCategories: NamedOption[];
    countries: Country[];
    statuses: string[];
    branches: Branch[];
    users: NamedOption[];
    contacts: ContactListItem[];
    products: NamedOption[];
}>();

const productOptions = computed(() =>
    props.products.map((product) => ({
        value: String(product.id),
        label: product.name,
    })),
);

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Projects', href: index() },
            { title: 'New project', href: create() },
        ],
    },
});

const builderOptions = computed(() =>
    props.builders.map((builder) => ({
        value: String(builder.id),
        label: builder.name,
    })),
);

const categoryOptions = computed(() =>
    props.projectCategories.map((category) => ({
        value: String(category.id),
        label: category.name,
    })),
);

const assigneeOptions = computed(() =>
    props.users.map((user) => ({
        value: String(user.id),
        label: user.name,
    })),
);

// Team Contacts logic
const contactToAdd = ref<string | undefined>();
const selectedContacts = ref<ContactListItem[]>([]);

const contactOptions = computed(() =>
    props.contacts
        .filter(
            (contact) =>
                !selectedContacts.value.some((c) => c.id === contact.id),
        )
        .map((contact) => ({
            value: String(contact.id),
            label: `${contact.name} (${contact.contact_type.name})`,
        })),
);

const addContact = () => {
    if (!contactToAdd.value) {
        return;
    }

    const contact = props.contacts.find(
        (c) => String(c.id) === contactToAdd.value,
    );

    if (contact && !selectedContacts.value.some((c) => c.id === contact.id)) {
        selectedContacts.value.push(contact);
    }

    contactToAdd.value = undefined;
};

const removeContact = (id: number) => {
    selectedContacts.value = selectedContacts.value.filter((c) => c.id !== id);
};

// Project Contacts logic
let nextProjectContactKey = 0;
const projectContacts = ref<{ key: number }[]>([]);

const addProjectContact = () => {
    projectContacts.value.push({ key: nextProjectContactKey++ });
};

const removeProjectContact = (key: number) => {
    projectContacts.value = projectContacts.value.filter(
        (contact) => contact.key !== key,
    );
};
</script>

<template>
    <Head title="New project" />

    <div class="flex flex-col space-y-6">
        <div class="flex items-center justify-between">
            <Heading
                variant="small"
                title="New project"
                description="Enter the details below to create a new project"
            />
            <Button variant="outline" as-child>
                <Link :href="index()">Back</Link>
            </Button>
        </div>

        <Form
            v-bind="store.form()"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <!-- Hidden inputs for selected team contacts -->
            <input
                v-for="contact in selectedContacts"
                :key="contact.id"
                type="hidden"
                name="contacts[]"
                :value="contact.id"
            />

            <div class="space-y-6">
                <!-- Project Details Card -->
                <Card>
                    <CardHeader>
                        <CardTitle>Project Details</CardTitle>
                    </CardHeader>
                    <CardContent
                        class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4"
                    >
                        <div class="grid gap-2 lg:col-span-2">
                            <Label for="name">Project Name *</Label>
                            <Input id="name" name="name" required autofocus />
                            <InputError :message="errors.name" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="project_category_id"
                                >Project Type *</Label
                            >
                            <Combobox
                                name="project_category_id"
                                placeholder="Select a category"
                                :options="categoryOptions"
                            />
                            <InputError :message="errors.project_category_id" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="builder_id">Builder</Label>
                            <Combobox
                                name="builder_id"
                                placeholder="Select a builder"
                                :options="builderOptions"
                            />
                            <InputError :message="errors.builder_id" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="assignee_id">Assignee</Label>
                            <Combobox
                                name="assignee_id"
                                placeholder="Select an assignee..."
                                :options="assigneeOptions"
                            />
                            <InputError :message="errors.assignee_id" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="status">Status</Label>
                            <Select name="status" default-value="planning">
                                <SelectTrigger class="w-full">
                                    <SelectValue placeholder="Select status" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="status in statuses"
                                        :key="status"
                                        :value="status"
                                        class="capitalize"
                                    >
                                        {{ status }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="errors.status" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="expected_maturity"
                                >Expected Maturity</Label
                            >
                            <Input
                                id="expected_maturity"
                                name="expected_maturity"
                                type="date"
                            />
                            <InputError :message="errors.expected_maturity" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="preferred_material"
                                >Preferred Material</Label
                            >
                            <Input
                                id="preferred_material"
                                name="preferred_material"
                                placeholder="Enter preferred material"
                            />
                            <InputError :message="errors.preferred_material" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="start_date">Start Date</Label>
                            <Input
                                id="start_date"
                                name="start_date"
                                type="date"
                            />
                            <InputError :message="errors.start_date" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="end_date">End Date</Label>
                            <Input id="end_date" name="end_date" type="date" />
                            <InputError :message="errors.end_date" />
                        </div>

                        <div
                            v-if="branches.length > 0"
                            class="grid gap-2 lg:col-span-2"
                        >
                            <Label for="branch_id">Branch *</Label>
                            <Select name="branch_id">
                                <SelectTrigger class="w-full">
                                    <SelectValue
                                        placeholder="Select a branch"
                                    />
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
                    </CardContent>
                </Card>

                <!-- Owner Details Card -->
                <Card>
                    <CardHeader>
                        <CardTitle>Owner Details</CardTitle>
                    </CardHeader>
                    <CardContent class="grid grid-cols-1 gap-4 md:grid-cols-3">
                        <div class="grid gap-2">
                            <Label for="owner_name">Name</Label>
                            <Input
                                id="owner_name"
                                name="owner_name"
                                placeholder="Owner Name"
                            />
                            <InputError :message="errors.owner_name" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="owner_phone">Phone</Label>
                            <Input
                                id="owner_phone"
                                name="owner_phone"
                                placeholder="Owner Phone"
                            />
                            <InputError :message="errors.owner_phone" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="owner_email">Email</Label>
                            <Input
                                id="owner_email"
                                name="owner_email"
                                type="email"
                                placeholder="Owner Email"
                            />
                            <InputError :message="errors.owner_email" />
                        </div>
                    </CardContent>
                </Card>

                <!-- Project Location Card -->
                <Card>
                    <CardHeader>
                        <CardTitle>Project Location</CardTitle>
                    </CardHeader>
                    <CardContent
                        class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4"
                    >
                        <div class="grid gap-2 lg:col-span-2">
                            <Label for="address">Address</Label>
                            <Input
                                id="address"
                                name="address"
                                placeholder="Address line"
                            />
                            <InputError :message="errors.address" />
                        </div>

                        <div class="lg:col-span-2">
                            <LocationSelect
                                :countries="countries"
                                hide-country
                            />
                            <div class="mt-1 flex flex-col gap-1">
                                <InputError :message="errors.state_id" />
                                <InputError :message="errors.district_id" />
                            </div>
                        </div>

                        <div class="grid gap-2 lg:col-span-2">
                            <Label for="location">Location</Label>
                            <Input
                                id="location"
                                name="location"
                                placeholder="Location area"
                            />
                            <InputError :message="errors.location" />
                        </div>

                        <div class="grid gap-2 lg:col-span-2">
                            <Label for="pincode">Pincode</Label>
                            <Input
                                id="pincode"
                                name="pincode"
                                placeholder="Pincode"
                            />
                            <InputError :message="errors.pincode" />
                        </div>
                    </CardContent>
                </Card>

                <!-- Project Contacts Card -->
                <Card>
                    <CardHeader>
                        <CardTitle>Project Contacts</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div
                            v-for="(contact, index) in projectContacts"
                            :key="contact.key"
                            class="space-y-3 rounded-lg border p-4"
                        >
                            <div class="flex items-center justify-between">
                                <span
                                    class="text-sm font-medium text-muted-foreground"
                                    >Contact {{ index + 1 }}</span
                                >
                                <Button
                                    type="button"
                                    size="icon"
                                    variant="ghost"
                                    @click="removeProjectContact(contact.key)"
                                >
                                    <X class="h-4 w-4" />
                                </Button>
                            </div>

                            <div
                                class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4"
                            >
                                <div class="grid gap-2">
                                    <Label :for="`pc-name-${contact.key}`"
                                        >Name *</Label
                                    >
                                    <Input
                                        :id="`pc-name-${contact.key}`"
                                        :name="`project_contacts[${index}][name]`"
                                        placeholder="Contact name"
                                        required
                                    />
                                    <InputError
                                        :message="
                                            errors[
                                                `project_contacts.${index}.name`
                                            ]
                                        "
                                    />
                                </div>

                                <div class="grid gap-2">
                                    <Label :for="`pc-role-${contact.key}`"
                                        >Role</Label
                                    >
                                    <Input
                                        :id="`pc-role-${contact.key}`"
                                        :name="`project_contacts[${index}][role]`"
                                        placeholder="e.g. Site Engineer"
                                    />
                                    <InputError
                                        :message="
                                            errors[
                                                `project_contacts.${index}.role`
                                            ]
                                        "
                                    />
                                </div>

                                <div class="grid gap-2">
                                    <Label :for="`pc-phone-${contact.key}`"
                                        >Phone</Label
                                    >
                                    <Input
                                        :id="`pc-phone-${contact.key}`"
                                        :name="`project_contacts[${index}][phone]`"
                                        placeholder="Phone number"
                                    />
                                    <InputError
                                        :message="
                                            errors[
                                                `project_contacts.${index}.phone`
                                            ]
                                        "
                                    />
                                </div>

                                <div class="grid gap-2">
                                    <Label :for="`pc-email-${contact.key}`"
                                        >Email</Label
                                    >
                                    <Input
                                        :id="`pc-email-${contact.key}`"
                                        :name="`project_contacts[${index}][email]`"
                                        type="email"
                                        placeholder="Email address"
                                    />
                                    <InputError
                                        :message="
                                            errors[
                                                `project_contacts.${index}.email`
                                            ]
                                        "
                                    />
                                </div>
                            </div>
                        </div>

                        <p
                            v-if="projectContacts.length === 0"
                            class="rounded-lg border border-dashed py-4 text-center text-sm text-muted-foreground"
                        >
                            No project contacts added yet.
                        </p>

                        <Button
                            type="button"
                            variant="outline"
                            class="w-full"
                            @click="addProjectContact"
                        >
                            <Plus class="mr-2 h-4 w-4" /> Add project contact
                        </Button>
                    </CardContent>
                </Card>

                <!-- Bottom Section: Team Contacts & Additional Info side-by-side -->
                <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <!-- Team Contacts Card -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Team Contacts</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="flex gap-2">
                                <div class="flex-1">
                                    <Combobox
                                        v-model="contactToAdd"
                                        placeholder="Select contact..."
                                        :options="contactOptions"
                                    />
                                </div>
                                <Button
                                    type="button"
                                    size="icon"
                                    variant="outline"
                                    @click="addContact"
                                >
                                    <Plus class="h-4 w-4" />
                                </Button>
                            </div>
                            <InputError :message="errors.contacts" />

                            <div
                                v-if="selectedContacts.length > 0"
                                class="flex flex-wrap gap-2 pt-2"
                            >
                                <Badge
                                    v-for="contact in selectedContacts"
                                    :key="contact.id"
                                    variant="secondary"
                                    class="gap-1.5 rounded-full px-3 py-1 text-sm font-medium"
                                >
                                    {{ contact.name }}
                                    <span class="text-xs text-muted-foreground">
                                        {{ contact.contact_type.name }}
                                    </span>
                                    <button
                                        type="button"
                                        class="rounded-full outline-hidden hover:bg-muted focus:ring-2 focus:ring-ring"
                                        @click="removeContact(contact.id)"
                                    >
                                        <X class="h-3.5 w-3.5" />
                                    </button>
                                </Badge>
                            </div>
                            <p
                                v-else
                                class="rounded-lg border border-dashed py-4 text-center text-sm text-muted-foreground"
                            >
                                No contacts added yet.
                            </p>
                        </CardContent>
                    </Card>

                    <!-- Additional Info Card -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Additional Info</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="grid gap-2">
                                <Label for="product_ids">Products</Label>
                                <MultiCombobox
                                    name="product_ids"
                                    placeholder="Select products (optional)"
                                    :options="productOptions"
                                />
                                <InputError :message="errors.product_ids" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="description">Description</Label>
                                <Textarea
                                    id="description"
                                    name="description"
                                    rows="5"
                                    placeholder="Enter notes or description..."
                                />
                                <InputError :message="errors.description" />
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>

            <!-- Submit Button -->
            <div class="flex items-center justify-end gap-4 border-t pt-4">
                <Button type="submit" size="lg" :disabled="processing">
                    Create project
                </Button>
            </div>
        </Form>
    </div>
</template>
