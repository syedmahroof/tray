<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Pencil } from '@lucide/vue';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import NotesPanel from '@/components/NotesPanel.vue';
import RemindersPanel from '@/components/RemindersPanel.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { show as showContact } from '@/routes/contacts';
import { edit, index, show } from '@/routes/enquiries';
import { store as storeNote } from '@/routes/enquiries/notes';
import { store as storeReminder } from '@/routes/enquiries/reminders';
import { destroy as destroyNote } from '@/routes/notes';
import { destroy as destroyReminder } from '@/routes/reminders';
import type { EnquiryDetail, Note, Reminder } from '@/types';

const props = defineProps<{
    enquiry: EnquiryDetail;
    notes: Note[];
    reminders: Reminder[];
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
            <Heading
                variant="small"
                :title="`Enquiry #${enquiry.id}`"
                :description="enquiry.contact.name"
            />

            <Button v-if="permissions.includes('enquiries.update')" as-child>
                <Link :href="edit(enquiry.id)"><Pencil /> Edit</Link>
            </Button>
        </div>

        <Tabs default-value="details">
            <TabsList>
                <TabsTrigger value="details">Details</TabsTrigger>
                <TabsTrigger value="notes">Notes</TabsTrigger>
                <TabsTrigger value="reminders">Reminders</TabsTrigger>
            </TabsList>

            <TabsContent value="details" class="space-y-6">
                <div class="grid gap-4 rounded-lg border p-4 sm:grid-cols-2">
                    <div>
                        <p class="text-sm text-muted-foreground">Contact</p>
                        <Link
                            :href="showContact(enquiry.contact.id)"
                            class="text-sm font-medium hover:underline"
                        >
                            {{ enquiry.contact.name }}
                        </Link>
                    </div>
                    <div>
                        <p class="text-sm text-muted-foreground">Status</p>
                        <Badge :variant="statusVariant" class="capitalize">
                            {{ enquiry.status.replace('_', ' ') }}
                        </Badge>
                    </div>
                    <div>
                        <p class="text-sm text-muted-foreground">Project</p>
                        <p class="text-sm font-medium">
                            {{ enquiry.project?.name ?? '—' }}
                        </p>
                    </div>
                    <div>
                        <p class="text-sm text-muted-foreground">Product</p>
                        <p class="text-sm font-medium">
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
                        <p class="text-sm text-muted-foreground">Assigned to</p>
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
                </div>
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
