<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowLeft, Building2, Calendar, MapPin, Pencil, Plus, User, Phone, Mail, FileText } from '@lucide/vue';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { edit, index, show } from '@/routes/projects';
import { show as showContact } from '@/routes/contacts';
import { show as showVisitReport, create as createVisitReport } from '@/routes/visit-reports';
import type { Project, NamedOption, Country, State, District, VisitReport, ProjectContact, Branch } from '@/types';

type ContactWithDetails = NamedOption & {
    phone: string | null;
    email: string | null;
    contact_type: NamedOption;
};

type VisitReportWithUser = VisitReport & {
    user: { id: number; name: string };
};

type ProjectDetail = Project & {
    builder: NamedOption;
    project_category: NamedOption;
    country: Country | null;
    state: State | null;
    district: District | null;
    assignee: NamedOption | null;
    contacts: ContactWithDetails[];
    project_contacts: ProjectContact[];
    visit_reports: VisitReportWithUser[];
    branch: Branch;
};

const props = defineProps<{
    project: ProjectDetail;
}>();

defineOptions({
    layout: (props: { project: ProjectDetail }) => ({
        breadcrumbs: [
            { title: 'Projects', href: index() },
            { title: props.project.name, href: show(props.project.id) },
        ],
    }),
});

const statusVariant = (status: string) => {
    if (status === 'completed') {
        return 'default' as const;
    }

    if (status === 'ongoing') {
        return 'outline' as const;
    }

    return 'secondary' as const;
};

const permissions = computed(() => usePage().props.auth.permissions);
</script>

<template>
    <Head :title="project.name" />

    <div class="flex flex-col space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <Link
                    :href="index()"
                    class="mb-2 inline-flex items-center gap-1 text-sm text-muted-foreground hover:underline"
                >
                    <ArrowLeft class="h-4 w-4" /> Back to List
                </Link>
                <Heading
                    variant="small"
                    :title="project.name"
                    :description="`Builder: ${project.builder.name} · Category: ${project.project_category.name}`"
                />
            </div>

            <div class="flex items-center gap-2">
                <Button
                    v-if="permissions.includes('visit-reports.create')"
                    variant="outline"
                    as-child
                >
                    <Link :href="createVisitReport({ query: { project_id: project.id } })">
                        <Plus /> New visit report
                    </Link>
                </Button>
                <Button v-if="permissions.includes('projects.update')" as-child>
                    <Link :href="edit(project.id)"><Pencil /> Edit</Link>
                </Button>
            </div>
        </div>

        <Tabs default-value="details">
            <TabsList>
                <TabsTrigger value="details">Details</TabsTrigger>
                <TabsTrigger value="internal-contacts">Internal Contacts ({{ project.project_contacts.length }})</TabsTrigger>
                <TabsTrigger value="associated-contacts">Contacts ({{ project.contacts.length }})</TabsTrigger>
                <TabsTrigger value="visit-reports" data-test="visit-reports-tab">
                    Visit Reports ({{ project.visit_reports.length }})
                </TabsTrigger>
            </TabsList>

            <TabsContent value="details" class="space-y-6 mt-4">
                <div class="grid gap-6 md:grid-cols-2">
                    <!-- Project Information -->
                    <div class="rounded-lg border p-4 space-y-4">
                        <div class="flex items-center gap-2 border-b pb-2">
                            <Building2 class="h-5 w-5 text-muted-foreground" />
                            <h3 class="font-semibold text-base">Project Information</h3>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-sm text-muted-foreground">Builder</p>
                                <p class="text-sm font-medium">{{ project.builder.name }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-muted-foreground">Category</p>
                                <p class="text-sm font-medium">{{ project.project_category.name }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-muted-foreground">Status</p>
                                <Badge :variant="statusVariant(project.status)" class="capitalize mt-0.5">
                                    {{ project.status }}
                                </Badge>
                            </div>
                            <div>
                                <p class="text-sm text-muted-foreground">Branch</p>
                                <p class="text-sm font-medium">{{ project.branch.name }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-muted-foreground">Start Date</p>
                                <p class="text-sm font-medium">{{ project.start_date ?? '—' }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-muted-foreground">End Date</p>
                                <p class="text-sm font-medium">{{ project.end_date ?? '—' }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-muted-foreground">Expected Maturity</p>
                                <p class="text-sm font-medium">{{ project.expected_maturity ?? '—' }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-muted-foreground">Preferred Material</p>
                                <p class="text-sm font-medium">{{ project.preferred_material ?? '—' }}</p>
                            </div>
                            <div class="col-span-2">
                                <p class="text-sm text-muted-foreground">Assigned to</p>
                                <p class="text-sm font-medium">{{ project.assignee?.name ?? '—' }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Owner Details -->
                    <div class="rounded-lg border p-4 space-y-4">
                        <div class="flex items-center gap-2 border-b pb-2">
                            <User class="h-5 w-5 text-muted-foreground" />
                            <h3 class="font-semibold text-base">Owner Details</h3>
                        </div>
                        <div class="space-y-3">
                            <div>
                                <p class="text-sm text-muted-foreground">Owner Name</p>
                                <p class="text-sm font-medium">{{ project.owner_name ?? '—' }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-muted-foreground">Owner Phone</p>
                                <p class="text-sm font-medium flex items-center gap-1.5">
                                    <Phone v-if="project.owner_phone" class="h-3.5 w-3.5 text-muted-foreground" />
                                    {{ project.owner_phone ?? '—' }}
                                </p>
                            </div>
                            <div>
                                <p class="text-sm text-muted-foreground">Owner Email</p>
                                <p class="text-sm font-medium flex items-center gap-1.5">
                                    <Mail v-if="project.owner_email" class="h-3.5 w-3.5 text-muted-foreground" />
                                    {{ project.owner_email ?? '—' }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Location & Address -->
                    <div class="rounded-lg border p-4 space-y-4 md:col-span-2">
                        <div class="flex items-center gap-2 border-b pb-2">
                            <MapPin class="h-5 w-5 text-muted-foreground" />
                            <h3 class="font-semibold text-base">Location Details</h3>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2 md:grid-cols-3">
                            <div class="sm:col-span-2 md:col-span-3">
                                <p class="text-sm text-muted-foreground">Address</p>
                                <p class="text-sm font-medium whitespace-pre-wrap">{{ project.address ?? '—' }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-muted-foreground">Location / City</p>
                                <p class="text-sm font-medium">{{ project.location ?? '—' }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-muted-foreground">Pincode</p>
                                <p class="text-sm font-medium">{{ project.pincode ?? '—' }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-muted-foreground">District</p>
                                <p class="text-sm font-medium">{{ project.district?.name ?? '—' }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-muted-foreground">State</p>
                                <p class="text-sm font-medium">{{ project.state?.name ?? '—' }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-muted-foreground">Country</p>
                                <p class="text-sm font-medium">{{ project.country?.name ?? '—' }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Description -->
                    <div v-if="project.description" class="rounded-lg border p-4 space-y-2 md:col-span-2">
                        <p class="text-sm text-muted-foreground">Description</p>
                        <p class="text-sm font-medium whitespace-pre-wrap">{{ project.description }}</p>
                    </div>
                </div>
            </TabsContent>

            <!-- Internal Contacts Tab -->
            <TabsContent value="internal-contacts" class="mt-4">
                <div class="rounded-lg border">
                    <div class="p-4 border-b">
                        <h3 class="font-semibold text-base">Internal Contacts</h3>
                        <p class="text-sm text-muted-foreground">Inline contact details for this specific project site.</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-muted/50 text-muted-foreground font-medium border-b">
                                <tr>
                                    <th class="p-3">Name</th>
                                    <th class="p-3">Role</th>
                                    <th class="p-3">Phone</th>
                                    <th class="p-3">Email</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <tr v-for="contact in project.project_contacts" :key="contact.id">
                                    <td class="p-3 font-medium">{{ contact.name }}</td>
                                    <td class="p-3 text-muted-foreground">{{ contact.role ?? '—' }}</td>
                                    <td class="p-3">{{ contact.phone ?? '—' }}</td>
                                    <td class="p-3">{{ contact.email ?? '—' }}</td>
                                </tr>
                                <tr v-if="project.project_contacts.length === 0">
                                    <td colspan="4" class="p-8 text-center text-muted-foreground">
                                        No internal contacts added for this project.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </TabsContent>

            <!-- Associated Contacts Tab -->
            <TabsContent value="associated-contacts" class="mt-4 space-y-4">
                <div class="rounded-lg border">
                    <div class="p-4 border-b">
                        <h3 class="font-semibold text-base">Associated Contacts</h3>
                        <p class="text-sm text-muted-foreground">List of CRM contacts linked to this project.</p>
                    </div>
                    <div class="divide-y">
                        <div v-for="contact in project.contacts" :key="contact.id" class="p-4 flex items-center justify-between">
                            <div>
                                <Link :href="showContact(contact.id)" class="font-medium hover:underline text-sm block">
                                    {{ contact.name }}
                                </Link>
                                <span class="text-xs text-muted-foreground capitalize">
                                    {{ contact.contact_type.name }}
                                </span>
                            </div>
                            <div class="text-sm text-right space-y-0.5">
                                <p v-if="contact.phone" class="text-muted-foreground flex items-center gap-1 justify-end">
                                    <Phone class="h-3 w-3" /> {{ contact.phone }}
                                </p>
                                <p v-if="contact.email" class="text-muted-foreground flex items-center gap-1 justify-end">
                                    <Mail class="h-3 w-3" /> {{ contact.email }}
                                </p>
                            </div>
                        </div>
                        <div v-if="project.contacts.length === 0" class="p-8 text-center text-muted-foreground">
                            No associated contacts found for this project.
                        </div>
                    </div>
                </div>
            </TabsContent>

            <!-- Visit Reports Tab -->
            <TabsContent value="visit-reports" class="mt-4 space-y-4">
                <div class="rounded-lg border">
                    <div class="p-4 border-b flex justify-between items-center">
                        <div>
                            <h3 class="font-semibold text-base">Visit Reports</h3>
                            <p class="text-sm text-muted-foreground">Historical visit logs and followups.</p>
                        </div>
                        <Button
                            v-if="permissions.includes('visit-reports.create')"
                            size="sm"
                            as-child
                        >
                            <Link :href="createVisitReport({ query: { project_id: project.id } })">
                                <Plus /> New visit report
                            </Link>
                        </Button>
                    </div>
                    <div class="divide-y">
                        <div v-for="report in project.visit_reports" :key="report.id" class="p-4">
                            <div class="flex items-start justify-between">
                                <div>
                                    <Link :href="showVisitReport(report.id)" class="font-semibold text-sm hover:underline flex items-center gap-1.5">
                                        <FileText class="h-4 w-4 text-muted-foreground" />
                                        {{ report.visit_type }} — {{ report.objective }}
                                    </Link>
                                    <p class="text-xs text-muted-foreground mt-1">
                                        Date: {{ report.visit_date }} · Reported by: {{ report.user.name }}
                                    </p>
                                </div>
                                <div class="text-xs text-right space-y-1">
                                    <div v-if="report.next_meeting_date" class="text-muted-foreground">
                                        Next Meeting: {{ report.next_meeting_date }}
                                    </div>
                                    <div v-if="report.next_call_date" class="text-muted-foreground">
                                        Next Call: {{ report.next_call_date }}
                                    </div>
                                </div>
                            </div>
                            <div v-if="report.report" class="mt-2 text-sm text-muted-foreground whitespace-pre-wrap line-clamp-3">
                                {{ report.report }}
                            </div>
                        </div>
                        <div v-if="project.visit_reports.length === 0" class="p-8 text-center text-muted-foreground">
                            No visit reports found for this project.
                        </div>
                    </div>
                </div>
            </TabsContent>
        </Tabs>
    </div>
</template>
