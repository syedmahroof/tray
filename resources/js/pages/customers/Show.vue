<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Pencil } from '@lucide/vue';
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
            <Heading variant="small" :title="customer.name" />

            <Button v-if="permissions.includes('customers.update')" as-child>
                <Link :href="edit(customer.id)"><Pencil /> Edit</Link>
            </Button>
        </div>

        <div class="grid gap-4 rounded-lg border p-4 sm:grid-cols-2">
            <div>
                <p class="text-sm text-muted-foreground">Phone</p>
                <p class="text-sm font-medium">{{ customer.phone ?? '—' }}</p>
            </div>
            <div>
                <p class="text-sm text-muted-foreground">Email</p>
                <p class="text-sm font-medium">{{ customer.email ?? '—' }}</p>
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
                <p class="text-sm font-medium">
                    {{ customer.assignee?.name ?? '—' }}
                </p>
            </div>
            <div>
                <p class="text-sm text-muted-foreground">Branch</p>
                <p class="text-sm font-medium">{{ customer.branch.name }}</p>
            </div>
        </div>
    </div>
</template>
