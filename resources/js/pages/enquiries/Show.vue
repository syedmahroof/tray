<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    Pencil,
    ArrowLeft,
    ClipboardList,
    Info,
    FileText,
    Calendar,
    UserRound,
    Building,
    Package,
    ReceiptText,
} from '@lucide/vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import NotesPanel from '@/components/NotesPanel.vue';
import QuotationsCard from '@/components/QuotationsCard.vue';
import RemindersPanel from '@/components/RemindersPanel.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useTabQuery } from '@/composables/useTabQuery';
import { show as showContact } from '@/routes/contacts';
import { show as showCustomer } from '@/routes/customers';
import { edit, index, show } from '@/routes/enquiries';
import { store as storeNote } from '@/routes/enquiries/notes';
import { store as storeReminder } from '@/routes/enquiries/reminders';
import { destroy as destroyNote } from '@/routes/notes';
import { create as createQuotation } from '@/routes/quotations';
import { destroy as destroyReminder } from '@/routes/reminders';
import type { EnquiryDetail, Note, QuotationSummary, Reminder } from '@/types';

const props = defineProps<{
    enquiry: EnquiryDetail;
    notes: Note[];
    reminders: Reminder[];
    quotations: QuotationSummary[];
}>();

defineOptions({
    layout: (props: { enquiry: { id: number } }) => ({
        breadcrumbs: [
            { title: 'Enquiries', href: index() },
            { title: `#${props.enquiry.id}`, href: show(props.enquiry.id) },
        ],
    }),
});

const permissions = computed(() => usePage().props.auth.permissions);

const activeTab = useTabQuery(
    ['details', 'quotations', 'notes', 'reminders'],
    'details',
);

const statusVariant = computed(() => {
    if (props.enquiry.status === 'converted') {
        return 'default' as const;
    }

    if (props.enquiry.status === 'lost') {
        return 'destructive' as const;
    }

    if (props.enquiry.status === 'in_progress') {
        return 'outline' as const;
    }

    return 'secondary' as const;
});
</script>

<template>
    <Head :title="`Enquiry #${enquiry.id}`" />

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
                    :title="`Enquiry #${enquiry.id}`"
                    :description="enquiry.contact.name"
                />
            </div>

            <Button v-if="permissions.includes('enquiries.update')" as-child>
                <Link :href="edit(enquiry.id)"><Pencil /> Edit</Link>
            </Button>
        </div>

        <Tabs v-model="activeTab">
            <TabsList>
                <TabsTrigger value="details" class="flex items-center gap-1.5">
                    <Info class="h-4 w-4 text-[#d97706]" />
                    Details
                </TabsTrigger>
                <TabsTrigger
                    value="quotations"
                    class="flex items-center gap-1.5"
                >
                    <ReceiptText class="h-4 w-4 text-indigo-500" />
                    Quotations
                    <span
                        v-if="quotations.length"
                        class="rounded-full bg-muted px-1.5 text-xs text-muted-foreground"
                        >{{ quotations.length }}</span
                    >
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
            </TabsList>

            <TabsContent value="details" class="space-y-6">
                <Card>
                    <CardHeader
                        class="flex flex-row items-center gap-2 border-b pb-3"
                    >
                        <ClipboardList class="h-5 w-5 text-[#d97706]" />
                        <CardTitle class="text-base font-semibold"
                            >Enquiry Details</CardTitle
                        >
                    </CardHeader>
                    <CardContent class="grid gap-4 pt-6 sm:grid-cols-2">
                        <div v-if="enquiry.customer">
                            <p class="text-sm text-muted-foreground">Customer</p>
                            <Link
                                :href="showCustomer(enquiry.customer.id)"
                                class="mt-0.5 flex items-center gap-1.5 text-sm font-medium hover:underline"
                            >
                                <UserRound class="h-3.5 w-3.5 text-[#2563eb]" />
                                {{ enquiry.customer.name }}
                            </Link>
                        </div>
                        <div v-if="enquiry.contact">
                            <p class="text-sm text-muted-foreground">Contact</p>
                            <Link
                                :href="showContact(enquiry.contact.id)"
                                class="mt-0.5 flex items-center gap-1.5 text-sm font-medium hover:underline"
                            >
                                <UserRound class="h-3.5 w-3.5 text-[#2563eb]" />
                                {{ enquiry.contact.name }}
                            </Link>
                        </div>
                        <div>
                            <p class="text-sm text-muted-foreground">Status</p>
                            <Badge
                                :variant="statusVariant"
                                class="mt-0.5 capitalize"
                            >
                                {{ enquiry.status.replace('_', ' ') }}
                            </Badge>
                        </div>
                        <div>
                            <p class="text-sm text-muted-foreground">Project</p>
                            <p
                                class="mt-0.5 flex items-center gap-1.5 text-sm font-medium"
                            >
                                <Building class="h-3.5 w-3.5 text-[#4f46e5]" />
                                {{ enquiry.project?.name ?? '—' }}
                            </p>
                        </div>
                        <div>
                            <p class="text-sm text-muted-foreground">Product</p>
                            <p
                                class="mt-0.5 flex items-center gap-1.5 text-sm font-medium"
                            >
                                <Package class="h-3.5 w-3.5 text-[#059669]" />
                                {{ enquiry.product?.name ?? '—' }}
                            </p>
                        </div>
                        <div>
                            <p class="text-sm text-muted-foreground">Source</p>
                            <p class="text-sm font-medium">
                                {{ enquiry.source ?? '—' }}
                            </p>
                        </div>
                        <div>
                            <p class="text-sm text-muted-foreground">
                                Assigned to
                            </p>
                            <p class="text-sm font-medium">
                                {{ enquiry.assignee?.name ?? '—' }}
                            </p>
                        </div>
                        <div>
                            <p class="text-sm text-muted-foreground">Branch</p>
                            <p class="text-sm font-medium">
                                {{ enquiry.branch.name }}
                            </p>
                        </div>
                        <div v-if="enquiry.remarks" class="sm:col-span-2">
                            <p class="text-sm text-muted-foreground">Remarks</p>
                            <p class="text-sm font-medium whitespace-pre-wrap">
                                {{ enquiry.remarks }}
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </TabsContent>

            <TabsContent value="quotations">
                <QuotationsCard
                    :quotations="quotations"
                    :create-href="
                        createQuotation.url({
                            query: { enquiry_id: enquiry.id },
                        })
                    "
                    :can-create="permissions.includes('quotations.create')"
                />
            </TabsContent>

            <TabsContent value="notes">
                <NotesPanel
                    :notes="notes"
                    :store-url="storeNote(enquiry.id).url"
                    :delete-url="(id: number) => destroyNote(id).url"
                    :can-create="permissions.includes('notes.create')"
                    :can-delete="permissions.includes('notes.delete')"
                />
            </TabsContent>

            <TabsContent value="reminders">
                <RemindersPanel
                    :reminders="reminders"
                    :store-url="storeReminder(enquiry.id).url"
                    :delete-url="(id: number) => destroyReminder(id).url"
                    :can-create="permissions.includes('reminders.create')"
                    :can-delete="permissions.includes('reminders.delete')"
                />
            </TabsContent>
        </Tabs>
    </div>
</template>
