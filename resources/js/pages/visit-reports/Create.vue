<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import MultiCombobox from '@/components/MultiCombobox.vue';
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
import { create, index, store } from '@/routes/visit-reports';
import type { Branch, NamedOption, VisitType } from '@/types';

const props = defineProps<{
    projects: NamedOption[];
    customers: NamedOption[];
    contacts: NamedOption[];
    visitTypes: VisitType[];
    branches: Branch[];
    preselectedProjectId: number | null;
    preselectedCustomerId: number | null;
    preselectedContactId: number | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Visit Reports', href: index() },
            { title: 'New visit report', href: create() },
        ],
    },
});

const projectOptions = computed(() =>
    props.projects.map((project) => ({
        value: String(project.id),
        label: project.name,
    })),
);
const customerOptions = computed(() =>
    props.customers.map((customer) => ({
        value: String(customer.id),
        label: customer.name,
    })),
);
const contactOptions = computed(() =>
    props.contacts.map((contact) => ({
        value: String(contact.id),
        label: contact.name,
    })),
);

const initialProjectIds = computed(() =>
    props.preselectedProjectId ? [String(props.preselectedProjectId)] : [],
);
const initialCustomerIds = computed(() =>
    props.preselectedCustomerId ? [String(props.preselectedCustomerId)] : [],
);
const initialContactIds = computed(() =>
    props.preselectedContactId ? [String(props.preselectedContactId)] : [],
);
</script>

<template>
    <Head title="New visit report" />

    <div class="flex flex-col space-y-6">
        <div>
            <Link
                :href="index()"
                class="inline-flex items-center gap-1 text-sm text-muted-foreground hover:underline"
            >
                <ArrowLeft class="h-4 w-4" /> Back to List
            </Link>
        </div>

        <Heading
            variant="small"
            title="Create Visit Report"
            description="Record a new visit report for projects, customers, or contacts"
        />

        <Form
            v-bind="store.form()"
            class="max-w-3xl space-y-8"
            v-slot="{ errors, processing }"
        >
            <div class="space-y-4 rounded-lg border p-4">
                <Heading
                    variant="small"
                    title="Link Entities"
                    description="Select at least one project, customer, or contact"
                />

                <div class="grid gap-2">
                    <Label for="project_ids">Projects</Label>
                    <MultiCombobox
                        name="project_ids"
                        placeholder="Search and select multiple projects"
                        :options="projectOptions"
                        :model-value="initialProjectIds"
                    />
                    <InputError :message="errors.project_ids" />
                </div>

                <div class="grid gap-2">
                    <Label for="customer_ids">Customers</Label>
                    <MultiCombobox
                        name="customer_ids"
                        placeholder="Search and select multiple customers"
                        :options="customerOptions"
                        :model-value="initialCustomerIds"
                    />
                    <InputError :message="errors.customer_ids" />
                </div>

                <div class="grid gap-2">
                    <Label for="contact_ids">Contacts</Label>
                    <MultiCombobox
                        name="contact_ids"
                        placeholder="Search and select multiple contacts"
                        :options="contactOptions"
                        :model-value="initialContactIds"
                    />
                    <InputError :message="errors.contact_ids" />
                </div>
            </div>

            <div class="space-y-4 rounded-lg border p-4">
                <Heading
                    variant="small"
                    title="Visit Details"
                    description="Enter the visit information"
                />

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="visit_date">Visit Date *</Label>
                        <Input
                            id="visit_date"
                            name="visit_date"
                            type="date"
                            required
                        />
                        <InputError :message="errors.visit_date" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="visit_type">Visit Type *</Label>
                        <Select name="visit_type">
                            <SelectTrigger class="w-full">
                                <SelectValue placeholder="Select a type…" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="visitType in visitTypes"
                                    :key="visitType"
                                    :value="visitType"
                                >
                                    {{ visitType }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="errors.visit_type" />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="objective">Objective of Visiting *</Label>
                    <Input
                        id="objective"
                        name="objective"
                        placeholder="e.g., Site inspection, Client meeting, Follow-up discussion"
                        required
                    />
                    <InputError :message="errors.objective" />
                </div>

                <div class="grid gap-2">
                    <Label for="report"
                        >Daily/Visiting Time Report Update</Label
                    >
                    <Textarea
                        id="report"
                        name="report"
                        rows="4"
                        placeholder="Enter detailed report of the visit, observations, discussions, and any important notes…"
                    />
                    <p class="text-sm text-muted-foreground">
                        Provide a comprehensive summary of the visit
                    </p>
                    <InputError :message="errors.report" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="next_meeting_date">Next Meeting Date</Label>
                        <Input
                            id="next_meeting_date"
                            name="next_meeting_date"
                            type="date"
                        />
                        <InputError :message="errors.next_meeting_date" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="next_call_date">Next Call Date</Label>
                        <Input
                            id="next_call_date"
                            name="next_call_date"
                            type="date"
                        />
                        <InputError :message="errors.next_call_date" />
                    </div>
                </div>
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
                Create visit report
            </Button>
        </Form>
    </div>
</template>
