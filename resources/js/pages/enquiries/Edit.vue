<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import Combobox from '@/components/Combobox.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
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
import { Textarea } from '@/components/ui/textarea';
import { edit, index, show, update } from '@/routes/enquiries';
import {
    contactOptionLabel,
    type Branch,
    type ContactSelectOption,
    type Enquiry,
    type NamedOption,
} from '@/types';

const props = defineProps<{
    enquiry: Enquiry;
    contacts: ContactSelectOption[];
    projects: NamedOption[];
    products: NamedOption[];
    users: NamedOption[];
    statuses: string[];
    branches: Branch[];
}>();

defineOptions({
    layout: (props: { enquiry: { id: number } }) => ({
        breadcrumbs: [
            { title: 'Enquiries', href: index() },
            { title: `#${props.enquiry.id}`, href: show(props.enquiry.id) },
            { title: 'Edit', href: edit(props.enquiry.id) },
        ],
    }),
});

const contactOptions = computed(() =>
    props.contacts.map((contact) => ({
        value: String(contact.id),
        label: contactOptionLabel(contact),
    })),
);
const projectOptions = computed(() =>
    props.projects.map((project) => ({
        value: String(project.id),
        label: project.name,
    })),
);
const userOptions = computed(() =>
    props.users.map((user) => ({ value: String(user.id), label: user.name })),
);
const productOptions = computed(() =>
    props.products.map((product) => ({
        value: String(product.id),
        label: product.name,
    })),
);
</script>

<template>
    <Head :title="`Edit enquiry #${enquiry.id}`" />

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            :title="`Edit enquiry #${enquiry.id}`"
            description="Update enquiry details"
        />

        <Form
            v-bind="update.form(props.enquiry.id)"
            class="max-w-3xl space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="contact_id">Contact</Label>
                <Combobox
                    name="contact_id"
                    placeholder="Select a contact"
                    :options="contactOptions"
                    :model-value="String(enquiry.contact_id)"
                />
                <InputError :message="errors.contact_id" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="project_id">Project</Label>
                    <Combobox
                        name="project_id"
                        placeholder="Select a project"
                        :options="projectOptions"
                        :model-value="
                            enquiry.project_id
                                ? String(enquiry.project_id)
                                : undefined
                        "
                    />
                    <InputError :message="errors.project_id" />
                </div>

                <div class="grid gap-2">
                    <Label for="product_id">Product</Label>
                    <Combobox
                        name="product_id"
                        placeholder="Select a product"
                        :options="productOptions"
                        :model-value="
                            enquiry.product_id
                                ? String(enquiry.product_id)
                                : undefined
                        "
                    />
                    <InputError :message="errors.product_id" />
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="status">Status</Label>
                    <Select name="status" :default-value="enquiry.status">
                        <SelectTrigger class="w-full">
                            <SelectValue placeholder="Select a status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="status in statuses"
                                :key="status"
                                :value="status"
                                class="capitalize"
                            >
                                {{ status.replace('_', ' ') }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="errors.status" />
                </div>

                <div class="grid gap-2">
                    <Label for="source">Source</Label>
                    <Input
                        id="source"
                        name="source"
                        :default-value="enquiry.source ?? undefined"
                        placeholder="e.g. Website, Referral"
                    />
                    <InputError :message="errors.source" />
                </div>
            </div>

            <div class="grid gap-2">
                <Label for="assigned_to">Assigned to</Label>
                <Combobox
                    name="assigned_to"
                    placeholder="Select a user"
                    :options="userOptions"
                    :model-value="
                        enquiry.assigned_to
                            ? String(enquiry.assigned_to)
                            : undefined
                    "
                />
                <InputError :message="errors.assigned_to" />
            </div>

            <div class="grid gap-2">
                <Label for="remarks">Remarks</Label>
                <Textarea
                    id="remarks"
                    name="remarks"
                    rows="4"
                    :default-value="enquiry.remarks ?? undefined"
                />
                <InputError :message="errors.remarks" />
            </div>

            <div v-if="branches.length > 0" class="grid gap-2">
                <Label for="branch_id">Branch</Label>
                <Select
                    name="branch_id"
                    :default-value="String(enquiry.branch_id)"
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
