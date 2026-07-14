<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Building2,
    Calendar,
    MapPin,
    Pencil,
    Plus,
    User,
    Phone,
    Mail,
    FileText,
    Info,
    HardHat,
    Users,
    ClipboardList,
    Eye,
    Package,
} from '@lucide/vue';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import QuotationsCard from '@/components/QuotationsCard.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { formatDate } from '@/lib/utils';
import { show as showContact } from '@/routes/contacts';
import { show as showProduct } from '@/routes/products';
import { edit, index, show } from '@/routes/projects';
import { create as createQuotation } from '@/routes/quotations';
import {
    show as showVisitReport,
    create as createVisitReport,
} from '@/routes/visit-reports';
import type {
    Project,
    NamedOption,
    Country,
    State,
    District,
    VisitReport,
    ProjectContact,
    QuotationSummary,
    Branch,
} from '@/types';

type ContactWithDetails = NamedOption & {
    phone: string | null;
    email: string | null;
    contact_type: NamedOption;
};

type ProductWithDetails = NamedOption & {
    price: string | null;
    product_category: NamedOption;
};

type VisitReportWithUser = VisitReport & {
    user: { id: number; name: string };
};

type ProjectDetail = Project & {
    builder: NamedOption | null;
    project_category: NamedOption;
    country: Country | null;
    state: State | null;
    district: District | null;
    assignee: NamedOption | null;
    creator: NamedOption | null;
    contacts: ContactWithDetails[];
    project_contacts: ProjectContact[];
    products: ProductWithDetails[];
    visit_reports: VisitReportWithUser[];
    branch: Branch;
};

defineProps<{
    project: ProjectDetail;
    quotations: QuotationSummary[];
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
                    <ArrowLeft class="h-4 w-4 text-slate-500" /> Back to List
                </Link>
                <Heading
                    variant="small"
                    :title="project.name"
                    :description="`Builder: ${project.builder?.name ?? '—'} · Category: ${project.project_category.name}`"
                />
            </div>

            <div class="flex items-center gap-2">
                <Button
                    v-if="permissions.includes('visit-reports.create')"
                    variant="outline"
                    as-child
                >
                    <Link
                        :href="
                            createVisitReport({
                                query: { project_id: project.id },
                            })
                        "
                    >
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
                <TabsTrigger value="details" class="flex items-center gap-1.5">
                    <Info class="h-4 w-4 text-[#4f46e5]" />
                    Details
                </TabsTrigger>
                <TabsTrigger
                    value="internal-contacts"
                    class="flex items-center gap-1.5"
                >
                    <HardHat class="h-4 w-4 text-[#ea580c]" />
                    Internal Contacts ({{ project.project_contacts.length }})
                </TabsTrigger>
                <TabsTrigger
                    value="associated-contacts"
                    class="flex items-center gap-1.5"
                >
                    <Users class="h-4 w-4 text-[#2563eb]" />
                    Contacts ({{ project.contacts.length }})
                </TabsTrigger>
                <TabsTrigger
                    value="visit-reports"
                    data-test="visit-reports-tab"
                    class="flex items-center gap-1.5"
                >
                    <ClipboardList class="h-4 w-4 text-[#0ea5e9]" />
                    Visit Reports ({{ project.visit_reports.length }})
                </TabsTrigger>
            </TabsList>

            <TabsContent value="details" class="mt-4 space-y-6">
                <div class="grid gap-6 md:grid-cols-2">
                    <!-- Project Information -->
                    <Card>
                        <CardHeader
                            class="flex flex-row items-center gap-2 border-b pb-3"
                        >
                            <Building2 class="h-5 w-5 text-[#4f46e5]" />
                            <CardTitle class="text-base font-semibold"
                                >Project Information</CardTitle
                            >
                        </CardHeader>
                        <CardContent class="grid grid-cols-2 gap-4 pt-6">
                            <div>
                                <p class="text-sm text-muted-foreground">
                                    Builder
                                </p>
                                <p class="text-sm font-medium">
                                    {{ project.builder?.name ?? '—' }}
                                </p>
                            </div>
                            <div>
                                <p class="text-sm text-muted-foreground">
                                    Category
                                </p>
                                <p class="text-sm font-medium">
                                    {{ project.project_category.name }}
                                </p>
                            </div>
                            <div>
                                <p class="text-sm text-muted-foreground">
                                    Status
                                </p>
                                <Badge
                                    :variant="statusVariant(project.status)"
                                    class="mt-0.5 capitalize"
                                >
                                    {{ project.status }}
                                </Badge>
                            </div>
                            <div>
                                <p class="text-sm text-muted-foreground">
                                    Branch
                                </p>
                                <p class="text-sm font-medium">
                                    {{ project.branch.name }}
                                </p>
                            </div>
                            <div>
                                <p class="text-sm text-muted-foreground">
                                    Start Date
                                </p>
                                <p class="text-sm font-medium">
                                    {{ project.start_date ?? '—' }}
                                </p>
                            </div>
                            <div>
                                <p class="text-sm text-muted-foreground">
                                    End Date
                                </p>
                                <p class="text-sm font-medium">
                                    {{ project.end_date ?? '—' }}
                                </p>
                            </div>
                            <div>
                                <p class="text-sm text-muted-foreground">
                                    Expected Maturity
                                </p>
                                <p class="text-sm font-medium">
                                    {{ project.expected_maturity ?? '—' }}
                                </p>
                            </div>
                            <div>
                                <p class="text-sm text-muted-foreground">
                                    Preferred Material
                                </p>
                                <p class="text-sm font-medium">
                                    {{ project.preferred_material ?? '—' }}
                                </p>
                            </div>
                            <div class="col-span-2">
                                <p class="text-sm text-muted-foreground">
                                    Assigned to
                                </p>
                                <p class="text-sm font-medium">
                                    {{ project.assignee?.name ?? '—' }}
                                </p>
                            </div>
                            <div>
                                <p class="text-sm text-muted-foreground">
                                    Created on
                                </p>
                                <p
                                    class="flex items-center gap-1.5 text-sm font-medium"
                                >
                                    <Calendar
                                        class="h-3.5 w-3.5 text-[#16a34a]"
                                    />
                                    {{ formatDate(project.created_at) }}
                                </p>
                            </div>
                            <div>
                                <p class="text-sm text-muted-foreground">
                                    Created by
                                </p>
                                <p
                                    class="flex items-center gap-1.5 text-sm font-medium"
                                >
                                    <User class="h-3.5 w-3.5 text-[#0ea5e9]" />
                                    {{ project.creator?.name ?? '—' }}
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Owner Details -->
                    <Card>
                        <CardHeader
                            class="flex flex-row items-center gap-2 border-b pb-3"
                        >
                            <User class="h-5 w-5 text-[#db2777]" />
                            <CardTitle class="text-base font-semibold"
                                >Owner Details</CardTitle
                            >
                        </CardHeader>
                        <CardContent class="space-y-3 pt-6">
                            <div>
                                <p class="text-sm text-muted-foreground">
                                    Owner Name
                                </p>
                                <p class="text-sm font-medium">
                                    {{ project.owner_name ?? '—' }}
                                </p>
                            </div>
                            <div>
                                <p class="text-sm text-muted-foreground">
                                    Owner Phone
                                </p>
                                <p
                                    class="flex items-center gap-1.5 text-sm font-medium"
                                >
                                    <Phone
                                        v-if="project.owner_phone"
                                        class="h-3.5 w-3.5 text-green-600"
                                    />
                                    {{ project.owner_phone ?? '—' }}
                                </p>
                            </div>
                            <div>
                                <p class="text-sm text-muted-foreground">
                                    Owner Email
                                </p>
                                <p
                                    class="flex items-center gap-1.5 text-sm font-medium"
                                >
                                    <Mail
                                        v-if="project.owner_email"
                                        class="h-3.5 w-3.5 text-blue-500"
                                    />
                                    {{ project.owner_email ?? '—' }}
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Location & Address -->
                    <Card class="md:col-span-2">
                        <CardHeader
                            class="flex flex-row items-center gap-2 border-b pb-3"
                        >
                            <MapPin class="h-5 w-5 text-[#ef4444]" />
                            <CardTitle class="text-base font-semibold"
                                >Location Details</CardTitle
                            >
                        </CardHeader>
                        <CardContent
                            class="grid gap-4 pt-6 sm:grid-cols-2 md:grid-cols-3"
                        >
                            <div class="sm:col-span-2 md:col-span-3">
                                <p class="text-sm text-muted-foreground">
                                    Address
                                </p>
                                <p
                                    class="text-sm font-medium whitespace-pre-wrap"
                                >
                                    {{ project.address ?? '—' }}
                                </p>
                            </div>
                            <div>
                                <p class="text-sm text-muted-foreground">
                                    Location / City
                                </p>
                                <p class="text-sm font-medium">
                                    {{ project.location ?? '—' }}
                                </p>
                            </div>
                            <div>
                                <p class="text-sm text-muted-foreground">
                                    Pincode
                                </p>
                                <p class="text-sm font-medium">
                                    {{ project.pincode ?? '—' }}
                                </p>
                            </div>
                            <div>
                                <p class="text-sm text-muted-foreground">
                                    District
                                </p>
                                <p class="text-sm font-medium">
                                    {{ project.district?.name ?? '—' }}
                                </p>
                            </div>
                            <div>
                                <p class="text-sm text-muted-foreground">
                                    State
                                </p>
                                <p class="text-sm font-medium">
                                    {{ project.state?.name ?? '—' }}
                                </p>
                            </div>
                            <div>
                                <p class="text-sm text-muted-foreground">
                                    Country
                                </p>
                                <p class="text-sm font-medium">
                                    {{ project.country?.name ?? '—' }}
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Description -->
                    <Card v-if="project.description" class="md:col-span-2">
                        <CardHeader
                            class="flex flex-row items-center gap-2 border-b pb-3"
                        >
                            <Info class="h-5 w-5 text-[#4f46e5]" />
                            <CardTitle class="text-base font-semibold"
                                >Description</CardTitle
                            >
                        </CardHeader>
                        <CardContent class="pt-6">
                            <p class="text-sm font-medium whitespace-pre-wrap">
                                {{ project.description }}
                            </p>
                        </CardContent>
                    </Card>

                    <!-- Products -->
                    <Card class="md:col-span-2">
                        <CardHeader
                            class="flex flex-row items-center gap-2 border-b pb-3"
                        >
                            <Package class="h-5 w-5 text-[#10b981]" />
                            <CardTitle class="text-base font-semibold"
                                >Products</CardTitle
                            >
                        </CardHeader>
                        <CardContent class="pt-6">
                            <div
                                v-if="project.products.length"
                                class="flex flex-wrap gap-2"
                            >
                                <Link
                                    v-for="product in project.products"
                                    :key="product.id"
                                    :href="showProduct(product.id)"
                                    class="flex items-center gap-1 hover:underline"
                                >
                                    <Badge variant="secondary" class="gap-1.5">
                                        {{ product.name }}
                                        <span class="text-muted-foreground">
                                            {{ product.product_category.name }}
                                        </span>
                                    </Badge>
                                </Link>
                            </div>
                            <p v-else class="text-sm text-muted-foreground">
                                No products linked to this project.
                            </p>
                        </CardContent>
                    </Card>
                </div>

                <QuotationsCard
                    :quotations="quotations"
                    :create-href="
                        createQuotation.url({
                            query: { project_id: project.id },
                        })
                    "
                    :can-create="permissions.includes('quotations.create')"
                />
            </TabsContent>

            <!-- Internal Contacts Tab -->
            <TabsContent value="internal-contacts" class="mt-4">
                <Card>
                    <CardHeader
                        class="flex flex-row items-center gap-2 border-b p-4 pb-3"
                    >
                        <HardHat class="h-5 w-5 text-[#ea580c]" />
                        <div>
                            <CardTitle class="text-base font-semibold"
                                >Internal Contacts</CardTitle
                            >
                            <p class="mt-0.5 text-sm text-muted-foreground">
                                Inline contact details for this specific project
                                site.
                            </p>
                        </div>
                    </CardHeader>
                    <CardContent class="p-0">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-sm">
                                <thead
                                    class="border-b bg-muted/50 font-medium text-muted-foreground"
                                >
                                    <tr>
                                        <th class="p-3">Name</th>
                                        <th class="p-3">Role</th>
                                        <th class="p-3">Phone</th>
                                        <th class="p-3">Email</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y">
                                    <tr
                                        v-for="contact in project.project_contacts"
                                        :key="contact.id"
                                    >
                                        <td class="p-3 font-medium">
                                            {{ contact.name }}
                                        </td>
                                        <td class="p-3 text-muted-foreground">
                                            {{ contact.role ?? '—' }}
                                        </td>
                                        <td class="p-3">
                                            {{ contact.phone ?? '—' }}
                                        </td>
                                        <td class="p-3">
                                            {{ contact.email ?? '—' }}
                                        </td>
                                    </tr>
                                    <tr
                                        v-if="
                                            project.project_contacts.length ===
                                            0
                                        "
                                    >
                                        <td
                                            colspan="4"
                                            class="p-8 text-center text-muted-foreground"
                                        >
                                            No internal contacts added for this
                                            project.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            </TabsContent>

            <!-- Associated Contacts Tab -->
            <TabsContent value="associated-contacts" class="mt-4 space-y-4">
                <Card>
                    <CardHeader
                        class="flex flex-row items-center gap-2 border-b p-4 pb-3"
                    >
                        <Users class="h-5 w-5 text-[#2563eb]" />
                        <div>
                            <CardTitle class="text-base font-semibold"
                                >Associated Contacts</CardTitle
                            >
                            <p class="mt-0.5 text-sm text-muted-foreground">
                                List of CRM contacts linked to this project.
                            </p>
                        </div>
                    </CardHeader>
                    <CardContent class="divide-y p-0">
                        <div
                            v-for="contact in project.contacts"
                            :key="contact.id"
                            class="flex items-center justify-between p-4"
                        >
                            <div>
                                <Link
                                    :href="showContact(contact.id)"
                                    class="block text-sm font-medium hover:underline"
                                >
                                    {{ contact.name }}
                                </Link>
                                <span
                                    class="text-xs text-muted-foreground capitalize"
                                >
                                    {{ contact.contact_type.name }}
                                </span>
                            </div>
                            <div class="flex items-center gap-3">
                                <div class="space-y-0.5 text-right text-sm">
                                    <p
                                        v-if="contact.phone"
                                        class="flex items-center justify-end gap-1 text-muted-foreground"
                                    >
                                        <Phone class="h-3 w-3 text-green-600" />
                                        {{ contact.phone }}
                                    </p>
                                    <p
                                        v-if="contact.email"
                                        class="flex items-center justify-end gap-1 text-muted-foreground"
                                    >
                                        <Mail class="h-3 w-3 text-blue-500" />
                                        {{ contact.email }}
                                    </p>
                                </div>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    as-child
                                    class="bg-blue-50 text-blue-600 hover:bg-blue-100 hover:text-blue-800 dark:bg-blue-950/30 dark:text-blue-400 dark:hover:bg-blue-900/40 dark:hover:text-blue-300"
                                    :aria-label="`View ${contact.name}`"
                                >
                                    <Link :href="showContact(contact.id)">
                                        <Eye class="h-4 w-4" />
                                    </Link>
                                </Button>
                            </div>
                        </div>
                        <div
                            v-if="project.contacts.length === 0"
                            class="p-8 text-center text-muted-foreground"
                        >
                            No associated contacts found for this project.
                        </div>
                    </CardContent>
                </Card>
            </TabsContent>

            <!-- Visit Reports Tab -->
            <TabsContent value="visit-reports" class="mt-4 space-y-4">
                <Card>
                    <CardHeader
                        class="flex flex-row items-center justify-between border-b p-4 pb-3"
                    >
                        <div class="flex items-center gap-2">
                            <ClipboardList class="h-5 w-5 text-[#0ea5e9]" />
                            <div>
                                <CardTitle class="text-base font-semibold"
                                    >Visit Reports</CardTitle
                                >
                                <p class="mt-0.5 text-sm text-muted-foreground">
                                    Historical visit logs and followups.
                                </p>
                            </div>
                        </div>
                        <Button
                            v-if="permissions.includes('visit-reports.create')"
                            size="sm"
                            as-child
                        >
                            <Link
                                :href="
                                    createVisitReport({
                                        query: { project_id: project.id },
                                    })
                                "
                            >
                                <Plus /> New visit report
                            </Link>
                        </Button>
                    </CardHeader>
                    <CardContent class="divide-y p-0">
                        <div
                            v-for="report in project.visit_reports"
                            :key="report.id"
                            class="p-4"
                        >
                            <div class="flex items-start justify-between">
                                <div>
                                    <Link
                                        :href="showVisitReport(report.id)"
                                        class="flex items-center gap-1.5 text-sm font-semibold hover:underline"
                                    >
                                        <FileText
                                            class="h-4 w-4 text-[#0ea5e9]"
                                        />
                                        {{ report.visit_type }} —
                                        {{ report.objective }}
                                    </Link>
                                    <p
                                        class="mt-1 text-xs text-muted-foreground"
                                    >
                                        Date:
                                        {{ formatDate(report.visit_date) }} ·
                                        Reported by: {{ report.user.name }}
                                    </p>
                                </div>
                                <div class="space-y-1 text-right text-xs">
                                    <div
                                        v-if="report.next_meeting_date"
                                        class="text-muted-foreground"
                                    >
                                        Next Meeting:
                                        {{
                                            formatDate(report.next_meeting_date)
                                        }}
                                    </div>
                                    <div
                                        v-if="report.next_call_date"
                                        class="text-muted-foreground"
                                    >
                                        Next Call:
                                        {{ formatDate(report.next_call_date) }}
                                    </div>
                                </div>
                            </div>
                            <div
                                v-if="report.report"
                                class="mt-2 line-clamp-3 text-sm whitespace-pre-wrap text-muted-foreground"
                            >
                                {{ report.report }}
                            </div>
                        </div>
                        <div
                            v-if="project.visit_reports.length === 0"
                            class="p-8 text-center text-muted-foreground"
                        >
                            No visit reports found for this project.
                        </div>
                    </CardContent>
                </Card>
            </TabsContent>
        </Tabs>
    </div>
</template>
