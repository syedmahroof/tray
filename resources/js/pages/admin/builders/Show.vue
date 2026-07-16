<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Building2,
    MapPin,
    Pencil,
    Phone,
    Mail,
    User,
    ClipboardList,
    Plus,
    FileText,
} from '@lucide/vue';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import { formatDate } from '@/lib/utils';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import QuotationsCard from '@/components/QuotationsCard.vue';
import { edit, index, show } from '@/routes/builders';
import { show as showProject } from '@/routes/projects';
import { create as createQuotation } from '@/routes/quotations';
import { show as showVisitReport, create as createVisitReport } from '@/routes/visit-reports';
import type {
    Builder,
    Country,
    State,
    District,
    Project,
    QuotationSummary,
    VisitReport,
    ActivityAuthor,
} from '@/types';

type BuilderVisitReport = VisitReport & {
    user: ActivityAuthor;
};

type BuilderDetail = Builder & {
    country: Country | null;
    state: State | null;
    district: District | null;
    projects: Project[];
};

const props = defineProps<{
    builder: BuilderDetail;
    quotations: QuotationSummary[];
    visitReports: BuilderVisitReport[];
}>();

defineOptions({
    layout: (props: { builder: BuilderDetail }) => ({
        breadcrumbs: [
            { title: 'Builders', href: index() },
            { title: props.builder.name, href: show(props.builder.id) },
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
    <Head :title="builder.name" />

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
                    :title="builder.name"
                    description="Real-estate Builder Details"
                />
            </div>

            <Button v-if="permissions.includes('builders.update')" as-child>
                <Link :href="edit(builder.id)"><Pencil /> Edit</Link>
            </Button>
        </div>

        <div class="grid gap-6 md:grid-cols-2">
            <!-- Profile Info -->
            <Card>
                <CardHeader
                    class="flex flex-row items-center gap-2 border-b pb-3"
                >
                    <User class="h-5 w-5 text-[#ea580c]" />
                    <CardTitle class="text-base font-semibold"
                        >Profile</CardTitle
                    >
                </CardHeader>
                <CardContent class="space-y-3 pt-6">
                    <div>
                        <p class="text-sm text-muted-foreground">
                            Contact Person
                        </p>
                        <p class="text-sm font-medium">
                            {{ builder.contact_person ?? '—' }}
                        </p>
                    </div>
                    <div>
                        <p class="text-sm text-muted-foreground">Phone</p>
                        <p
                            class="flex items-center gap-1.5 text-sm font-medium"
                        >
                            <Phone
                                v-if="builder.phone"
                                class="h-3.5 w-3.5 text-green-600"
                            />
                            {{ builder.phone ?? '—' }}
                        </p>
                    </div>
                    <div>
                        <p class="text-sm text-muted-foreground">Email</p>
                        <p
                            class="flex items-center gap-1.5 text-sm font-medium"
                        >
                            <Mail
                                v-if="builder.email"
                                class="h-3.5 w-3.5 text-blue-500"
                            />
                            {{ builder.email ?? '—' }}
                        </p>
                    </div>
                    <div>
                        <p class="text-sm text-muted-foreground">Status</p>
                        <Badge
                            :variant="
                                builder.is_active ? 'default' : 'secondary'
                            "
                            class="mt-0.5"
                        >
                            {{ builder.is_active ? 'Active' : 'Inactive' }}
                        </Badge>
                    </div>
                </CardContent>
            </Card>

            <!-- Location Info -->
            <Card>
                <CardHeader
                    class="flex flex-row items-center gap-2 border-b pb-3"
                >
                    <MapPin class="h-5 w-5 text-[#ef4444]" />
                    <CardTitle class="text-base font-semibold"
                        >Location</CardTitle
                    >
                </CardHeader>
                <CardContent class="space-y-3 pt-6">
                    <div>
                        <p class="text-sm text-muted-foreground">Address</p>
                        <p class="text-sm font-medium whitespace-pre-wrap">
                            {{ builder.address ?? '—' }}
                        </p>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm text-muted-foreground">
                                District
                            </p>
                            <p class="text-sm font-medium">
                                {{ builder.district?.name ?? '—' }}
                            </p>
                        </div>
                        <div>
                            <p class="text-sm text-muted-foreground">State</p>
                            <p class="text-sm font-medium">
                                {{ builder.state?.name ?? '—' }}
                            </p>
                        </div>
                        <div class="col-span-2">
                            <p class="text-sm text-muted-foreground">Country</p>
                            <p class="text-sm font-medium">
                                {{ builder.country?.name ?? '—' }}
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Projects owned by builder -->
            <Card class="md:col-span-2">
                <CardHeader
                    class="flex flex-row items-center gap-2 border-b pb-3"
                >
                    <Building2 class="h-5 w-5 text-[#4f46e5]" />
                    <CardTitle class="text-base font-semibold"
                        >Projects ({{ builder.projects.length }})</CardTitle
                    >
                </CardHeader>
                <CardContent class="pt-6">
                    <div class="overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Name</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Start Date</TableHead>
                                    <TableHead>End Date</TableHead>
                                    <TableHead class="text-right"
                                        >Actions</TableHead
                                    >
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow
                                    v-for="project in builder.projects"
                                    :key="project.id"
                                >
                                    <TableCell class="font-medium">
                                        <Link
                                            :href="showProject(project.id)"
                                            class="text-sm font-semibold hover:underline"
                                        >
                                            {{ project.name }}
                                        </Link>
                                    </TableCell>
                                    <TableCell>
                                        <Badge
                                            :variant="
                                                statusVariant(project.status)
                                            "
                                            class="capitalize"
                                        >
                                            {{ project.status }}
                                        </Badge>
                                    </TableCell>
                                    <TableCell>{{
                                        project.start_date ?? '—'
                                    }}</TableCell>
                                    <TableCell>{{
                                        project.end_date ?? '—'
                                    }}</TableCell>
                                    <TableCell class="text-right">
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            as-child
                                        >
                                            <Link
                                                :href="showProject(project.id)"
                                                >View Details</Link
                                            >
                                        </Button>
                                    </TableCell>
                                </TableRow>
                                <TableRow v-if="builder.projects.length === 0">
                                    <TableCell
                                        colspan="5"
                                        class="py-8 text-center text-muted-foreground"
                                    >
                                        No projects yet for this builder.
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                </CardContent>
            </Card>

            <div class="md:col-span-2">
                <QuotationsCard
                    :quotations="quotations"
                    :create-href="
                        createQuotation.url({
                            query: { builder_id: builder.id },
                        })
                    "
                    :can-create="permissions.includes('quotations.create')"
                />
            </div>

            <div class="md:col-span-2 space-y-4">
                <Card>
                    <CardHeader
                        class="flex flex-row items-center justify-between border-b pb-3"
                    >
                        <div class="flex items-center gap-2">
                            <ClipboardList class="h-5 w-5 text-[#0ea5e9]" />
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
                                        query: { builder_id: builder.id },
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
                            class="p-6"
                        >
                            <Link
                                :href="showVisitReport(visitReport.id)"
                                class="flex items-center gap-1.5 text-sm font-semibold hover:underline"
                            >
                                <FileText class="h-4 w-4 text-[#0ea5e9]" />
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
            </div>
        </div>
    </div>
</template>
