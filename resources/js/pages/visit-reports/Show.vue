<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Building,
    Building2,
    CalendarClock,
    CalendarDays,
    ClipboardCheck,
    Clock,
    Contact as ContactIcon,
    FileText,
    History,
    MapPin,
    Pencil,
    PhoneCall,
    ScrollText,
    Target,
    User,
    UserRound,
    Users,
    HardHat,
} from '@lucide/vue';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useTabQuery } from '@/composables/useTabQuery';
import { formatDate } from '@/lib/utils';
import { show as showContact } from '@/routes/contacts';
import { show as showCustomer } from '@/routes/customers';
import { show as showProject } from '@/routes/projects';
import { edit, index, show } from '@/routes/visit-reports';
import type {
    AuditLogEntry,
    VisitReportDetail,
    VisitReportHistoryItem,
} from '@/types';

const props = defineProps<{
    visitReport: VisitReportDetail;
    history: VisitReportHistoryItem[];
    auditLogs: AuditLogEntry[];
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

const activeTab = useTabQuery(['details', 'history', 'audit'], 'details');

const visitTypeIcon = computed(() => {
    switch (props.visitReport.visit_type) {
        case 'Site Visit':
            return Building2;
        case 'Client Meeting':
            return Users;
        case 'Inspection':
            return ClipboardCheck;
        default:
            return MapPin;
    }
});
</script>

<template>
    <Head :title="`Visit report #${visitReport.id}`" />

    <div class="flex flex-col space-y-6">
        <div>
            <Link
                :href="index()"
                class="mb-3 inline-flex items-center gap-1 text-sm text-muted-foreground hover:underline"
            >
                <ArrowLeft class="h-4 w-4 text-slate-500" /> Back to List
            </Link>

            <!-- Title hero -->
            <div class="flex items-start justify-between gap-4">
                <div class="flex items-start gap-3">
                    <span
                        class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-[#0ea5e9]/10 text-[#0ea5e9]"
                    >
                        <ClipboardCheck class="size-6" />
                    </span>
                    <div>
                        <Heading
                            variant="small"
                            :title="`Visit Report #${visitReport.id}`"
                            :description="visitReport.objective"
                        />
                        <div
                            class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1.5 text-sm text-muted-foreground"
                        >
                            <span class="flex items-center gap-1.5">
                                <component
                                    :is="visitTypeIcon"
                                    class="h-4 w-4 text-[#4f46e5]"
                                />
                                {{ visitReport.visit_type }}
                            </span>
                            <span class="flex items-center gap-1.5">
                                <CalendarDays class="h-4 w-4 text-[#0ea5e9]" />
                                {{ formatDate(visitReport.visit_date) }}
                            </span>
                            <span class="flex items-center gap-1.5">
                                <UserRound class="h-4 w-4 text-[#db2777]" />
                                {{ visitReport.user.name }}
                            </span>
                        </div>
                    </div>
                </div>

                <Button
                    v-if="permissions.includes('visit-reports.update')"
                    as-child
                >
                    <Link :href="edit(visitReport.id)"><Pencil /> Edit</Link>
                </Button>
            </div>
        </div>

        <Tabs v-model="activeTab">
            <TabsList>
                <TabsTrigger value="details" class="flex items-center gap-1.5">
                    <FileText class="h-4 w-4 text-[#0ea5e9]" />
                    Visit Details
                </TabsTrigger>
                <TabsTrigger value="history" class="flex items-center gap-1.5">
                    <History class="h-4 w-4 text-[#f59e0b]" />
                    History
                    <span
                        v-if="history.length"
                        class="rounded-full bg-muted px-1.5 text-xs text-muted-foreground"
                        >{{ history.length }}</span
                    >
                </TabsTrigger>
                <TabsTrigger value="audit" class="flex items-center gap-1.5">
                    <ScrollText class="h-4 w-4 text-slate-500" />
                    Audit Log
                </TabsTrigger>
            </TabsList>

            <!-- Visit Details -->
            <TabsContent value="details" class="mt-4 space-y-6">
                <Card>
                    <CardHeader
                        class="flex flex-row items-center gap-2 border-b pb-3"
                    >
                        <Users class="h-5 w-5 text-[#0ea5e9]" />
                        <CardTitle class="text-base font-semibold"
                            >Linked Entities</CardTitle
                        >
                    </CardHeader>
                    <CardContent class="grid gap-4 pt-6 sm:grid-cols-2 lg:grid-cols-4">
                        <div>
                            <p
                                class="flex items-center gap-1.5 text-sm text-muted-foreground"
                            >
                                <Building2 class="h-4 w-4 text-[#4f46e5]" />
                                Projects
                            </p>
                            <div class="mt-1.5 flex flex-wrap gap-2">
                                <Link
                                    v-for="project in visitReport.projects"
                                    :key="project.id"
                                    :href="showProject(project.id)"
                                >
                                    <Badge
                                        variant="secondary"
                                        class="hover:bg-secondary/80"
                                        >{{ project.name }}</Badge
                                    >
                                </Link>
                                <span
                                    v-if="visitReport.projects.length === 0"
                                    class="text-sm text-muted-foreground"
                                    >—</span
                                >
                            </div>
                        </div>

                        <div>
                            <p
                                class="flex items-center gap-1.5 text-sm text-muted-foreground"
                            >
                                <UserRound class="h-4 w-4 text-[#65a30d]" />
                                Customers
                            </p>
                            <div class="mt-1.5 flex flex-wrap gap-2">
                                <Link
                                    v-for="customer in visitReport.customers"
                                    :key="customer.id"
                                    :href="showCustomer(customer.id)"
                                >
                                    <Badge
                                        variant="secondary"
                                        class="hover:bg-secondary/80"
                                        >{{ customer.name }}</Badge
                                    >
                                </Link>
                                <span
                                    v-if="visitReport.customers.length === 0"
                                    class="text-sm text-muted-foreground"
                                    >—</span
                                >
                            </div>
                        </div>

                        <div>
                            <p
                                class="flex items-center gap-1.5 text-sm text-muted-foreground"
                            >
                                <ContactIcon class="h-4 w-4 text-[#2563eb]" />
                                Contacts
                            </p>
                            <div class="mt-1.5 flex flex-wrap gap-2">
                                <Link
                                    v-for="contact in visitReport.contacts"
                                    :key="contact.id"
                                    :href="showContact(contact.id)"
                                >
                                    <Badge
                                        variant="secondary"
                                        class="hover:bg-secondary/80"
                                        >{{ contact.name }}</Badge
                                    >
                                </Link>
                                <span
                                    v-if="visitReport.contacts.length === 0"
                                    class="text-sm text-muted-foreground"
                                    >—</span
                                >
                            </div>
                        </div>

                        <div>
                            <p
                                class="flex items-center gap-1.5 text-sm text-muted-foreground"
                            >
                                <HardHat class="h-4 w-4 text-[#eab308]" />
                                Builders
                            </p>
                            <div class="mt-1.5 flex flex-wrap gap-2">
                                <Badge
                                    v-for="builder in visitReport.builders"
                                    :key="builder.id"
                                    variant="secondary"
                                    class="hover:bg-secondary/80"
                                    >{{ builder.name }}</Badge
                                >
                                <span
                                    v-if="visitReport.builders.length === 0"
                                    class="text-sm text-muted-foreground"
                                    >—</span
                                >
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader
                        class="flex flex-row items-center gap-2 border-b pb-3"
                    >
                        <FileText class="h-5 w-5 text-[#0ea5e9]" />
                        <CardTitle class="text-base font-semibold"
                            >Visit Details</CardTitle
                        >
                    </CardHeader>
                    <CardContent class="grid gap-5 pt-6 sm:grid-cols-2">
                        <div>
                            <p
                                class="flex items-center gap-1.5 text-sm text-muted-foreground"
                            >
                                <CalendarDays class="h-4 w-4 text-[#0ea5e9]" />
                                Visit Date
                            </p>
                            <p class="mt-0.5 text-sm font-medium">
                                {{ formatDate(visitReport.visit_date) }}
                            </p>
                        </div>
                        <div>
                            <p
                                class="flex items-center gap-1.5 text-sm text-muted-foreground"
                            >
                                <MapPin class="h-4 w-4 text-[#4f46e5]" />
                                Visit Type
                            </p>
                            <p class="mt-0.5 text-sm font-medium">
                                {{ visitReport.visit_type }}
                            </p>
                        </div>
                        <div class="sm:col-span-2">
                            <p
                                class="flex items-center gap-1.5 text-sm text-muted-foreground"
                            >
                                <Target class="h-4 w-4 text-[#db2777]" />
                                Objective
                            </p>
                            <p class="mt-0.5 text-sm font-medium">
                                {{ visitReport.objective }}
                            </p>
                        </div>
                        <div v-if="visitReport.report" class="sm:col-span-2">
                            <p
                                class="flex items-center gap-1.5 text-sm text-muted-foreground"
                            >
                                <FileText class="h-4 w-4 text-[#0ea5e9]" />
                                Report
                            </p>
                            <p
                                class="mt-0.5 text-sm font-medium whitespace-pre-wrap"
                            >
                                {{ visitReport.report }}
                            </p>
                        </div>
                        <div>
                            <p
                                class="flex items-center gap-1.5 text-sm text-muted-foreground"
                            >
                                <CalendarClock class="h-4 w-4 text-[#16a34a]" />
                                Next Meeting Date
                            </p>
                            <p class="mt-0.5 text-sm font-medium">
                                {{ formatDate(visitReport.next_meeting_date) }}
                            </p>
                        </div>
                        <div>
                            <p
                                class="flex items-center gap-1.5 text-sm text-muted-foreground"
                            >
                                <PhoneCall class="h-4 w-4 text-[#0ea5e9]" />
                                Next Call Date
                            </p>
                            <p class="mt-0.5 text-sm font-medium">
                                {{ formatDate(visitReport.next_call_date) }}
                            </p>
                        </div>
                        <div>
                            <p
                                class="flex items-center gap-1.5 text-sm text-muted-foreground"
                            >
                                <User class="h-4 w-4 text-[#db2777]" />
                                Reported by
                            </p>
                            <p class="mt-0.5 text-sm font-medium">
                                {{ visitReport.user.name }}
                            </p>
                        </div>
                        <div>
                            <p
                                class="flex items-center gap-1.5 text-sm text-muted-foreground"
                            >
                                <Building class="h-4 w-4 text-[#4f46e5]" />
                                Branch
                            </p>
                            <p class="mt-0.5 text-sm font-medium">
                                {{ visitReport.branch.name }}
                            </p>
                        </div>
                        <div>
                            <p
                                class="flex items-center gap-1.5 text-sm text-muted-foreground"
                            >
                                <Clock class="h-4 w-4 text-slate-400" />
                                Created on
                            </p>
                            <p class="mt-0.5 text-sm font-medium">
                                {{ formatDate(visitReport.created_at) }}
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </TabsContent>

            <!-- History -->
            <TabsContent value="history" class="mt-4">
                <Card>
                    <CardHeader
                        class="flex flex-row items-center gap-2 border-b pb-3"
                    >
                        <History class="h-5 w-5 text-[#f59e0b]" />
                        <CardTitle class="text-base font-semibold"
                            >Previous Visits to Linked Entities</CardTitle
                        >
                    </CardHeader>
                    <CardContent class="pt-6">
                        <div class="space-y-3">
                            <Link
                                v-for="item in history"
                                :key="item.id"
                                :href="show(item.id)"
                                class="block rounded-lg border p-4 transition-colors hover:bg-muted/50"
                            >
                                <div
                                    class="flex flex-wrap items-center justify-between gap-2"
                                >
                                    <span
                                        class="flex items-center gap-1.5 font-medium"
                                    >
                                        <MapPin
                                            class="h-4 w-4 text-[#4f46e5]"
                                        />
                                        {{ item.visit_type }}
                                        <span class="text-muted-foreground"
                                            >· #{{ item.id }}</span
                                        >
                                    </span>
                                    <time
                                        class="flex items-center gap-1.5 text-sm text-muted-foreground"
                                    >
                                        <CalendarDays class="h-4 w-4" />
                                        {{ formatDate(item.visit_date) }}
                                    </time>
                                </div>
                                <p class="mt-1.5 text-sm text-muted-foreground">
                                    {{ item.objective }}
                                </p>
                                <div
                                    class="mt-2 flex flex-wrap items-center gap-1.5"
                                >
                                    <Badge
                                        v-for="c in item.contacts"
                                        :key="`c-${c.id}`"
                                        variant="outline"
                                        >{{ c.name }}</Badge
                                    >
                                    <Badge
                                        v-for="cu in item.customers"
                                        :key="`cu-${cu.id}`"
                                        variant="outline"
                                        >{{ cu.name }}</Badge
                                    >
                                    <Badge
                                        v-for="p in item.projects"
                                        :key="`p-${p.id}`"
                                        variant="outline"
                                        >{{ p.name }}</Badge
                                    >
                                    <Badge
                                        v-for="b in item.builders"
                                        :key="`b-${b.id}`"
                                        variant="outline"
                                        >{{ b.name }}</Badge
                                    >
                                    <span
                                        class="ml-auto flex items-center gap-1 text-xs text-muted-foreground"
                                    >
                                        <UserRound class="h-3 w-3" />
                                        {{ item.user?.name ?? 'Unknown' }}
                                    </span>
                                </div>
                            </Link>
                            <p
                                v-if="history.length === 0"
                                class="py-8 text-center text-sm text-muted-foreground"
                            >
                                No previous visits found for the linked
                                contacts, customers, projects, or builders.
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </TabsContent>

            <!-- Audit Log -->
            <TabsContent value="audit" class="mt-4">
                <Card>
                    <CardHeader
                        class="flex flex-row items-center gap-2 border-b pb-3"
                    >
                        <ScrollText class="h-5 w-5 text-slate-500" />
                        <CardTitle class="text-base font-semibold"
                            >Audit Log</CardTitle
                        >
                    </CardHeader>
                    <CardContent class="pt-6">
                        <div
                            class="relative ml-3 space-y-8 border-l border-slate-200 pl-6 dark:border-slate-800"
                        >
                            <div
                                v-for="log in auditLogs"
                                :key="log.id"
                                class="relative"
                            >
                                <span
                                    class="absolute top-1 -left-[31px] flex h-4 w-4 items-center justify-center rounded-full border-2 bg-white dark:bg-slate-900"
                                    :class="{
                                        'border-blue-500':
                                            log.action === 'created',
                                        'border-amber-500':
                                            log.action === 'updated',
                                    }"
                                >
                                </span>
                                <div>
                                    <div
                                        class="flex items-center justify-between gap-4"
                                    >
                                        <p
                                            class="text-sm font-semibold capitalize"
                                        >
                                            {{ log.action }}
                                        </p>
                                        <time
                                            class="text-xs whitespace-nowrap text-muted-foreground"
                                        >
                                            {{ formatDate(log.created_at) }}
                                        </time>
                                    </div>
                                    <p
                                        class="mt-1 text-sm text-muted-foreground"
                                    >
                                        {{ log.description }}
                                    </p>
                                    <p
                                        class="mt-1.5 flex items-center gap-1 text-xs text-slate-400 dark:text-slate-500"
                                    >
                                        <User class="h-3 w-3" />
                                        By: {{ log.user?.name ?? 'System' }}
                                    </p>
                                </div>
                            </div>
                            <p
                                v-if="auditLogs.length === 0"
                                class="p-4 text-center text-sm text-muted-foreground"
                            >
                                No history available.
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </TabsContent>
        </Tabs>
    </div>
</template>
