<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    Pencil,
    ArrowLeft,
    UserRound,
    Phone,
    Mail,
    MapPin,
    User,
    Info,
    ClipboardList,
    ReceiptText,
} from '@lucide/vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import QuotationsCard from '@/components/QuotationsCard.vue';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useTabQuery } from '@/composables/useTabQuery';
import { edit, index, show } from '@/routes/customers';
import {
    create as createEnquiry,
    show as showEnquiry,
} from '@/routes/enquiries';
import { create as createQuotation } from '@/routes/quotations';
import type { CustomerDetail, Enquiry, NamedOption, QuotationSummary } from '@/types';

type CustomerEnquiry = Enquiry & {
    contact: NamedOption | null;
    project: NamedOption | null;
};

defineProps<{
    customer: CustomerDetail;
    quotations: QuotationSummary[];
    enquiries: CustomerEnquiry[];
}>();

defineOptions({
    layout: (props: { customer: { id: number; name: string } }) => ({
        breadcrumbs: [
            { title: 'Customers', href: index() },
            { title: props.customer.name, href: show(props.customer.id) },
        ],
    }),
});

const permissions = computed(() => usePage().props.auth.permissions);

const activeTab = useTabQuery(
    ['details', 'enquiries', 'quotations'],
    'details',
);
</script>

<template>
    <Head :title="customer.name" />

    <div class="flex flex-col space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <Link
                    :href="index()"
                    class="mb-2 inline-flex items-center gap-1 text-sm text-muted-foreground hover:underline"
                >
                    <ArrowLeft class="h-4 w-4 text-slate-500" /> Back to List
                </Link>
                <Heading variant="small" :title="customer.name" />
            </div>

            <Button v-if="permissions.includes('customers.update')" as-child>
                <Link :href="edit(customer.id)"><Pencil /> Edit</Link>
            </Button>
        </div>

        <Tabs v-model="activeTab">
            <TabsList>
                <TabsTrigger value="details" class="flex items-center gap-1.5">
                    <Info class="h-4 w-4 text-[#2563eb]" />
                    Details
                </TabsTrigger>
                <TabsTrigger
                    value="enquiries"
                    class="flex items-center gap-1.5"
                >
                    <ClipboardList class="h-4 w-4 text-[#d97706]" />
                    Enquiries
                    <span
                        v-if="enquiries.length"
                        class="rounded-full bg-muted px-1.5 text-xs text-muted-foreground"
                        >{{ enquiries.length }}</span
                    >
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
            </TabsList>

            <TabsContent value="details">
                <Card>
                    <CardHeader class="flex flex-row items-center gap-2 border-b pb-3">
                <UserRound class="h-5 w-5 text-[#65a30d]" />
                <CardTitle class="text-base font-semibold"
                    >Customer Details</CardTitle
                >
            </CardHeader>
            <CardContent class="grid gap-4 pt-6 sm:grid-cols-2">
                <div>
                    <p class="text-sm text-muted-foreground">Phone</p>
                    <p class="flex items-center gap-1.5 text-sm font-medium">
                        <Phone class="h-3.5 w-3.5 text-green-600" />
                        {{ customer.phone ?? '—' }}
                    </p>
                </div>
                <div>
                    <p class="text-sm text-muted-foreground">Email</p>
                    <p class="flex items-center gap-1.5 text-sm font-medium">
                        <Mail class="h-3.5 w-3.5 text-blue-500" />
                        {{ customer.email ?? '—' }}
                    </p>
                </div>
                <div>
                    <p class="text-sm text-muted-foreground">GST Number</p>
                    <p class="text-sm font-medium">
                        {{ customer.gst_number ?? '—' }}
                    </p>
                </div>
                <div>
                    <p class="text-sm text-muted-foreground">Address</p>
                    <p class="text-sm font-medium">
                        {{
                            [
                                customer.address,
                                customer.district?.name,
                                customer.state?.name,
                                customer.country?.name,
                            ]
                                .filter(Boolean)
                                .join(', ') || '—'
                        }}
                    </p>
                </div>
                <div>
                    <p class="text-sm text-muted-foreground">Assigned to</p>
                    <p class="flex items-center gap-1.5 text-sm font-medium">
                        <User class="h-3.5 w-3.5 text-purple-500" />
                        {{ customer.assignee?.name ?? '—' }}
                    </p>
                </div>
                <div>
                    <p class="text-sm text-muted-foreground">Branch</p>
                    <p class="text-sm font-medium">
                        {{ customer.branch.name }}
                    </p>
                </div>
            </CardContent>
        </Card>
        </TabsContent>

        <TabsContent value="enquiries">
        <Card>
            <CardHeader
                class="flex flex-row items-center justify-between border-b pb-3"
            >
                <div class="flex items-center gap-2">
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
                                query: { customer_id: customer.id },
                            })
                        "
                    >
                        New enquiry
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

        <TabsContent value="quotations">
        <QuotationsCard
            :quotations="quotations"
            :create-href="
                createQuotation.url({
                    query: { customer_id: customer.id },
                })
            "
            :can-create="permissions.includes('quotations.create')"
        />
        </TabsContent>
        </Tabs>
    </div>
</template>
