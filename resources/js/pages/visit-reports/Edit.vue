<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import MultiCombobox from '@/components/MultiCombobox.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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
import { edit, index, show, update } from '@/routes/visit-reports';
import type {
    Branch,
    NamedOption,
    VisitReportDetail,
    VisitType,
} from '@/types';

const props = defineProps<{
    visitReport: VisitReportDetail;
    projects: NamedOption[];
    customers: NamedOption[];
    contacts: NamedOption[];
    visitTypes: VisitType[];
    branches: Branch[];
}>();

defineOptions({
    layout: (props: { visitReport: { id: number } }) => ({
        breadcrumbs: [
            { title: 'Visit Reports', href: index() },
            {
                title: `#${props.visitReport.id}`,
                href: show(props.visitReport.id),
            },
            { title: 'Edit', href: edit(props.visitReport.id) },
        ],
    }),
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

const selectedProjectIds = computed(() =>
    props.visitReport.projects.map((project) => String(project.id)),
);
const selectedCustomerIds = computed(() =>
    props.visitReport.customers.map((customer) => String(customer.id)),
);
const selectedContactIds = computed(() =>
    props.visitReport.contacts.map((contact) => String(contact.id)),
);
</script>

<template>
    <Head :title="`Edit visit report #${visitReport.id}`" />

    <div class="flex flex-col space-y-6">
        <div>
            <Link
                :href="show(visitReport.id)"
                class="inline-flex items-center gap-1 text-sm text-muted-foreground hover:underline"
            >
                <ArrowLeft class="h-4 w-4" /> Back to List
            </Link>
        </div>

        <Heading
            variant="small"
            :title="`Edit Visit Report #${visitReport.id}`"
            description="Update the visit report details"
        />

        <Form
            v-bind="update.form(props.visitReport.id)"
            class="space-y-8"
            v-slot="{ errors, processing }"
        >
            <Card>
                <CardHeader>
                    <CardTitle>Link Entities</CardTitle>
                    <CardDescription>
                        Select at least one project, customer, or contact
                    </CardDescription>
                </CardHeader>
                <CardContent class="grid gap-4 grid-cols-1 md:grid-cols-3">
                    <div class="grid gap-2">
                        <Label for="project_ids">Projects</Label>
                        <MultiCombobox
                            name="project_ids"
                            placeholder="Search projects"
                            :options="projectOptions"
                            :model-value="selectedProjectIds"
                        />
                        <InputError :message="errors.project_ids" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="customer_ids">Customers</Label>
                        <MultiCombobox
                            name="customer_ids"
                            placeholder="Search customers"
                            :options="customerOptions"
                            :model-value="selectedCustomerIds"
                        />
                        <InputError :message="errors.customer_ids" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="contact_ids">Contacts</Label>
                        <MultiCombobox
                            name="contact_ids"
                            placeholder="Search contacts"
                            :options="contactOptions"
                            :model-value="selectedContactIds"
                        />
                        <InputError :message="errors.contact_ids" />
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Visit Details</CardTitle>
                    <CardDescription>Enter the visit information</CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="grid gap-4 grid-cols-1 sm:grid-cols-2" :class="{ 'md:grid-cols-3': branches.length > 0 }">
                        <div class="grid gap-2">
                            <Label for="visit_date">Visit Date *</Label>
                            <Input
                                id="visit_date"
                                name="visit_date"
                                type="date"
                                :default-value="visitReport.visit_date"
                                required
                            />
                            <InputError :message="errors.visit_date" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="visit_type">Visit Type *</Label>
                            <Select
                                name="visit_type"
                                :default-value="visitReport.visit_type"
                            >
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

                        <div v-if="branches.length > 0" class="grid gap-2">
                            <Label for="branch_id">Branch</Label>
                            <Select
                                name="branch_id"
                                :default-value="String(visitReport.branch_id)"
                            >
                                <SelectTrigger class="w-full">
                                    <SelectValue placeholder="Select a branch…" />
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
                    </div>

                    <div class="grid gap-2">
                        <Label for="objective">Objective of Visiting *</Label>
                        <Input
                            id="objective"
                            name="objective"
                            :default-value="visitReport.objective"
                            placeholder="e.g., Site inspection, Client meeting, Follow-up discussion"
                            required
                        />
                        <InputError :message="errors.objective" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="report">Daily/Visiting Time Report Update</Label>
                        <Textarea
                            id="report"
                            name="report"
                            rows="3"
                            :default-value="visitReport.report ?? undefined"
                            placeholder="Enter detailed report of the visit, observations, discussions, and any important notes…"
                        />
                        <InputError :message="errors.report" />
                    </div>

                    <div class="grid gap-4 grid-cols-1 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="next_meeting_date">Next Meeting Date</Label>
                            <Input
                                id="next_meeting_date"
                                name="next_meeting_date"
                                type="date"
                                :default-value="
                                    visitReport.next_meeting_date ?? undefined
                                "
                            />
                            <InputError :message="errors.next_meeting_date" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="next_call_date">Next Call Date</Label>
                            <Input
                                id="next_call_date"
                                name="next_call_date"
                                type="date"
                                :default-value="
                                    visitReport.next_call_date ?? undefined
                                "
                            />
                            <InputError :message="errors.next_call_date" />
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Button type="submit" :disabled="processing">Save</Button>
        </Form>
    </div>
</template>
