<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Pencil, Plus } from '@lucide/vue';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import NotesPanel from '@/components/NotesPanel.vue';
import RemindersPanel from '@/components/RemindersPanel.vue';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { edit, index, show } from '@/routes/contacts';
import { store as storeNote } from '@/routes/contacts/notes';
import { store as storeReminder } from '@/routes/contacts/reminders';
import {
    create as createEnquiry,
    show as showEnquiry,
} from '@/routes/enquiries';
import { destroy as destroyNote } from '@/routes/notes';
import { destroy as destroyReminder } from '@/routes/reminders';
import {
    create as createVisitReport,
    show as showVisitReport,
} from '@/routes/visit-reports';
import type {
    ActivityAuthor,
    ContactDetail,
    Enquiry,
    NamedOption,
    Note,
    Reminder,
    VisitReport,
} from '@/types';

type ContactEnquiry = Enquiry & {
    project: NamedOption | null;
    product: NamedOption | null;
};

type ContactVisitReport = VisitReport & {
    user: ActivityAuthor;
};

defineProps<{
    contact: ContactDetail;
    notes: Note[];
    reminders: Reminder[];
    visitReports: ContactVisitReport[];
    enquiries: ContactEnquiry[];
}>();

defineOptions({
    layout: (props: { contact: ContactDetail & { name: string } }) => ({
        breadcrumbs: [
            { title: 'Contacts', href: index() },
            { title: props.contact.name, href: show(props.contact.id) },
        ],
    }),
});

const permissions = computed(() => usePage().props.auth.permissions);
</script>

<template>
    <Head :title="contact.name" />

    <div class="flex flex-col space-y-6">
        <div class="flex items-center justify-between">
            <Heading
                variant="small"
                :title="contact.name"
                :description="contact.contact_type.name"
            />

            <div class="flex items-center gap-2">
                <Button
                    v-if="permissions.includes('enquiries.create')"
                    variant="outline"
                    as-child
                >
                    <Link
                        :href="
                            createEnquiry({ query: { contact_id: contact.id } })
                        "
                    >
                        <Plus /> New enquiry
                    </Link>
                </Button>
                <Button v-if="permissions.includes('contacts.update')" as-child>
                    <Link :href="edit(contact.id)"><Pencil /> Edit</Link>
                </Button>
            </div>
        </div>

        <Tabs default-value="details">
            <TabsList>
                <TabsTrigger value="details">Details</TabsTrigger>
                <TabsTrigger value="notes">Notes</TabsTrigger>
                <TabsTrigger value="reminders">Reminders</TabsTrigger>
                <TabsTrigger value="visit-reports" data-test="visit-reports-tab"
                    >Visit Reports</TabsTrigger
                >
            </TabsList>

            <TabsContent value="details" class="space-y-6">
                <div class="grid gap-4 rounded-lg border p-4 sm:grid-cols-2">
                    <div>
                        <p class="text-sm text-muted-foreground">Phone</p>
                        <p class="text-sm font-medium">
                            {{ contact.phone ?? '—' }}
                        </p>
                    </div>
                    <div>
                        <p class="text-sm text-muted-foreground">Email</p>
                        <p class="text-sm font-medium">
                            {{ contact.email ?? '—' }}
                        </p>
                    </div>
                    <div>
                        <p class="text-sm text-muted-foreground">Address</p>
                        <p class="text-sm font-medium">
                            {{
                                [
                                    contact.address,
                                    contact.district?.name,
                                    contact.state?.name,
                                    contact.country?.name,
                                ]
                                    .filter(Boolean)
                                    .join(', ') || '—'
                            }}
                        </p>
                    </div>
                    <div>
                        <p class="text-sm text-muted-foreground">Assigned to</p>
                        <p class="text-sm font-medium">
                            {{ contact.assignee?.name ?? '—' }}
                        </p>
                    </div>
                    <div>
                        <p class="text-sm text-muted-foreground">Branch</p>
                        <p class="text-sm font-medium">
                            {{ contact.branch.name }}
                        </p>
                    </div>
                </div>

                <div class="space-y-3">
                    <Heading variant="small" title="Enquiries" />
                    <div
                        v-for="enquiry in enquiries"
                        :key="enquiry.id"
                        class="rounded-lg border p-4"
                    >
                        <Link
                            :href="showEnquiry(enquiry.id)"
                            class="font-medium hover:underline"
                        >
                            {{ enquiry.project?.name ?? 'General enquiry' }}
                        </Link>
                        <p class="text-sm text-muted-foreground capitalize">
                            {{ enquiry.status.replace('_', ' ') }}
                        </p>
                    </div>
                    <p
                        v-if="enquiries.length === 0"
                        class="text-sm text-muted-foreground"
                    >
                        No enquiries yet.
                    </p>
                </div>
            </TabsContent>

            <TabsContent value="notes">
                <NotesPanel
                    :notes="notes"
                    :store-url="storeNote(contact.id).url"
                    :delete-url="(id: number) => destroyNote(id).url"
                    :can-create="permissions.includes('notes.create')"
                    :can-delete="permissions.includes('notes.delete')"
                />
            </TabsContent>

            <TabsContent value="reminders">
                <RemindersPanel
                    :reminders="reminders"
                    :store-url="storeReminder(contact.id).url"
                    :delete-url="(id: number) => destroyReminder(id).url"
                    :can-create="permissions.includes('reminders.create')"
                    :can-delete="permissions.includes('reminders.delete')"
                />
            </TabsContent>

            <TabsContent value="visit-reports" class="space-y-3">
                <div class="flex justify-end">
                    <Button
                        v-if="permissions.includes('visit-reports.create')"
                        size="sm"
                        as-child
                    >
                        <Link
                            :href="
                                createVisitReport({
                                    query: { contact_id: contact.id },
                                })
                            "
                        >
                            <Plus /> New visit report
                        </Link>
                    </Button>
                </div>

                <div
                    v-for="visitReport in visitReports"
                    :key="visitReport.id"
                    class="rounded-lg border p-4"
                >
                    <Link
                        :href="showVisitReport(visitReport.id)"
                        class="font-medium hover:underline"
                    >
                        {{ visitReport.visit_type }} —
                        {{ visitReport.objective }}
                    </Link>
                    <p class="text-sm text-muted-foreground">
                        {{ visitReport.visit_date }} ·
                        {{ visitReport.user.name }}
                    </p>
                </div>
                <p
                    v-if="visitReports.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    No visit reports yet.
                </p>
            </TabsContent>
        </Tabs>
    </div>
</template>
