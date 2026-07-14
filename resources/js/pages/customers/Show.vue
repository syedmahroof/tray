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
} from '@lucide/vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { edit, index, show } from '@/routes/customers';
import type { CustomerDetail } from '@/types';

defineProps<{
    customer: CustomerDetail;
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
    </div>
</template>
