<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowLeft, Pencil } from '@lucide/vue';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { show as showContact } from '@/routes/contacts';
import { show as showCustomer } from '@/routes/customers';
import { show as showProject } from '@/routes/projects';
import { edit, index } from '@/routes/visit-reports';
import type { VisitReportDetail } from '@/types';

defineProps<{
    visitReport: VisitReportDetail;
}>();

defineOptions({
    layout: (props: { visitReport: { id: number } }) => ({
        breadcrumbs: [
            { title: 'Visit Reports', href: index() },
            { title: `#${props.visitReport.id}`, href: index() },
        ],
    }),
});

const permissions = computed(() => usePage().props.auth.permissions);
</script>

<template>
    <Head :title="`Visit report #${visitReport.id}`" />

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
                    :title="`Visit Report #${visitReport.id}`"
                    :description="visitReport.objective"
                />
            </div>

            <Button
                v-if="permissions.includes('visit-reports.update')"
                as-child
            >
                <Link :href="edit(visitReport.id)"><Pencil /> Edit</Link>
            </Button>
        </div>

        <div class="space-y-4 rounded-lg border p-4">
            <Heading variant="small" title="Linked Entities" />

            <div>
                <p class="text-sm text-muted-foreground">Projects</p>
                <div class="mt-1 flex flex-wrap gap-2">
                    <Link
                        v-for="project in visitReport.projects"
                        :key="project.id"
                        :href="showProject(project.id)"
                    >
                        <Badge variant="secondary">{{ project.name }}</Badge>
                    </Link>
                    <span
                        v-if="visitReport.projects.length === 0"
                        class="text-sm text-muted-foreground"
                        >—</span
                    >
                </div>
            </div>

            <div>
                <p class="text-sm text-muted-foreground">Customers</p>
                <div class="mt-1 flex flex-wrap gap-2">
                    <Link
                        v-for="customer in visitReport.customers"
                        :key="customer.id"
                        :href="showCustomer(customer.id)"
                    >
                        <Badge variant="secondary">{{ customer.name }}</Badge>
                    </Link>
                    <span
                        v-if="visitReport.customers.length === 0"
                        class="text-sm text-muted-foreground"
                        >—</span
                    >
                </div>
            </div>

            <div>
                <p class="text-sm text-muted-foreground">Contacts</p>
                <div class="mt-1 flex flex-wrap gap-2">
                    <Link
                        v-for="contact in visitReport.contacts"
                        :key="contact.id"
                        :href="showContact(contact.id)"
                    >
                        <Badge variant="secondary">{{ contact.name }}</Badge>
                    </Link>
                    <span
                        v-if="visitReport.contacts.length === 0"
                        class="text-sm text-muted-foreground"
                        >—</span
                    >
                </div>
            </div>
        </div>

        <div class="grid gap-4 rounded-lg border p-4 sm:grid-cols-2">
            <div>
                <p class="text-sm text-muted-foreground">Visit Date</p>
                <p class="text-sm font-medium">{{ visitReport.visit_date }}</p>
            </div>
            <div>
                <p class="text-sm text-muted-foreground">Visit Type</p>
                <p class="text-sm font-medium">{{ visitReport.visit_type }}</p>
            </div>
            <div class="sm:col-span-2">
                <p class="text-sm text-muted-foreground">Objective</p>
                <p class="text-sm font-medium">{{ visitReport.objective }}</p>
            </div>
            <div v-if="visitReport.report" class="sm:col-span-2">
                <p class="text-sm text-muted-foreground">Report</p>
                <p class="text-sm font-medium whitespace-pre-wrap">
                    {{ visitReport.report }}
                </p>
            </div>
            <div>
                <p class="text-sm text-muted-foreground">Next Meeting Date</p>
                <p class="text-sm font-medium">
                    {{ visitReport.next_meeting_date ?? '—' }}
                </p>
            </div>
            <div>
                <p class="text-sm text-muted-foreground">Next Call Date</p>
                <p class="text-sm font-medium">
                    {{ visitReport.next_call_date ?? '—' }}
                </p>
            </div>
            <div>
                <p class="text-sm text-muted-foreground">Reported by</p>
                <p class="text-sm font-medium">{{ visitReport.user.name }}</p>
            </div>
            <div>
                <p class="text-sm text-muted-foreground">Branch</p>
                <p class="text-sm font-medium">{{ visitReport.branch.name }}</p>
            </div>
        </div>
    </div>
</template>
