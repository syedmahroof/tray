<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    Pencil,
    Plus,
    User,
    Phone,
    Mail,
    ClipboardList,
    ClipboardCheck,
    ArrowLeft,
    Info,
    FileText,
    Calendar,
} from '@lucide/vue';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import NotesPanel from '@/components/NotesPanel.vue';
import RemindersPanel from '@/components/RemindersPanel.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { formatDate } from '@/lib/utils';
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
            <div>
                <Link
                    :href="index()"
                    class="mb-2 inline-flex items-center gap-1 text-sm text-muted-foreground hover:underline"
                >
                    <ArrowLeft class="h-4 w-4 text-slate-500" /> Back to List
                </Link>
                <Heading
                    variant="small"
                    :title="contact.name"
                    :description="contact.contact_type.name"
                />
            </div>

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
                <TabsTrigger value="details" class="flex items-center gap-1.5">
                    <Info class="h-4 w-4 text-[#2563eb]" />
                    Details
                </TabsTrigger>
                <TabsTrigger value="notes" class="flex items-center gap-1.5">
                    <FileText class="h-4 w-4 text-emerald-500" />
                    Notes
                </TabsTrigger>
                <TabsTrigger
                    value="reminders"
                    class="flex items-center gap-1.5"
                >
                    <Calendar class="h-4 w-4 text-rose-500" />
                    Reminders
                </TabsTrigger>
                <TabsTrigger
                    value="visit-reports"
                    data-test="visit-reports-tab"
                    class="flex items-center gap-1.5"
                >
                    <ClipboardCheck class="h-4 w-4 text-[#0ea5e9]" />
                    Visit Reports
                </TabsTrigger>
            </TabsList>

            <TabsContent value="details" class="space-y-6">
                <Card>
                    <CardHeader
                        class="flex flex-row items-center gap-2 border-b pb-3"
                    >
                        <User class="h-5 w-5 text-[#2563eb]" />
                        <CardTitle class="text-base font-semibold"
                            >Contact Details</CardTitle
                        >
                    </CardHeader>
                    <CardContent class="grid gap-4 pt-6 sm:grid-cols-2">
                        <div>
                            <p class="text-sm text-muted-foreground">Phone</p>
                            <p
                                class="flex items-center gap-1.5 text-sm font-medium"
                            >
                                <Phone class="h-3.5 w-3.5 text-green-600" />
                                {{ contact.phone ?? '—' }}
                            </p>
                        </div>
                        <div>
                            <p class="text-sm text-muted-foreground">Email</p>
                            <p
                                class="flex items-center gap-1.5 text-sm font-medium"
                            >
                                <Mail class="h-3.5 w-3.5 text-blue-500" />
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
                            <p class="text-sm text-muted-foreground">
                                Assigned to
                            </p>
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
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader
                        class="flex flex-row items-center justify-between border-b pb-3"
                    >
                        <div class="flex items-center gap-2">
                            <ClipboardList class="h-5 w-5 text-[#d97706]" />
                            <CardTitle class="text-base font-semibold"
                                >Enquiries ({{ enquiries.length }})</CardTitle
                            >
                        </div>
                        <Button
                            v-if="permissions.includes('enquiries.create')"
                            size="sm"
                            variant="outline"
                            as-child
                        >
                            <Link
                                :href="
                                    createEnquiry({
                                        query: { contact_id: contact.id },
                                    })
                                "
                            >
                                <Plus /> New enquiry
                            </Link>
                        </Button>
                    </CardHeader>
                    <CardContent class="divide-y p-0">
                        <div
                            v-for="enquiry in enquiries"
                            :key="enquiry.id"
                            class="p-4"
                        >
                            <Link
                                :href="showEnquiry(enquiry.id)"
                                class="block text-sm font-medium hover:underline"
                            >
                                {{ enquiry.project?.name ?? 'General enquiry' }}
                            </Link>
                            <span
                                class="mt-1 inline-block text-xs text-muted-foreground capitalize"
                            >
                                Status: {{ enquiry.status.replace('_', ' ') }}
                            </span>
                        </div>
                        <div
                            v-if="enquiries.length === 0"
                            class="p-8 text-center text-sm text-muted-foreground"
                        >
                            No enquiries yet.
                        </div>
                    </CardContent>
                </Card>
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

            <TabsContent value="visit-reports" class="space-y-4">
                <Card>
                    <CardHeader
                        class="flex flex-row items-center justify-between border-b pb-3"
                    >
                        <div class="flex items-center gap-2">
                            <ClipboardCheck class="h-5 w-5 text-[#0ea5e9]" />
                            <CardTitle class="text-base font-semibold"
                                >Visit Reports ({{
                                    visitReports.length
                                }})</CardTitle
                            >
                        </div>
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
                    </CardHeader>
                    <CardContent class="divide-y p-0">
                        <div
                            v-for="visitReport in visitReports"
                            :key="visitReport.id"
                            class="p-4"
                        >
                            <Link
                                :href="showVisitReport(visitReport.id)"
                                class="block text-sm font-medium hover:underline"
                            >
                                {{ visitReport.visit_type }} —
                                {{ visitReport.objective }}
                            </Link>
                            <p class="mt-1 text-xs text-muted-foreground">
                                Date: {{ formatDate(visitReport.visit_date) }} ·
                                Reported by: {{ visitReport.user.name }}
                            </p>
                        </div>
                        <div
                            v-if="visitReports.length === 0"
                            class="p-8 text-center text-sm text-muted-foreground"
                        >
                            No visit reports yet.
                        </div>
                    </CardContent>
                </Card>
            </TabsContent>
        </Tabs>
    </div>
</template>
