<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import QuotationForm from '@/components/QuotationForm.vue';
import { create, index } from '@/routes/quotations';
import type { Branch, NamedOption } from '@/types';

type ProductOption = NamedOption & { price: string | null };
type ContactOption = NamedOption & {
    phone: string | null;
    email: string | null;
};

defineProps<{
    contacts: ContactOption[];
    projects: NamedOption[];
    products: ProductOption[];
    statuses: string[];
    branches: Branch[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Quotations', href: index() },
            { title: 'New quotation', href: create() },
        ],
    },
});
</script>

<template>
    <Head title="New quotation" />

    <div class="flex flex-col space-y-6">
        <div>
            <Link
                :href="index()"
                class="inline-flex items-center gap-1 text-sm text-muted-foreground hover:underline"
            >
                <ArrowLeft class="h-4 w-4" /> Back to List
            </Link>
        </div>

        <Heading
            variant="small"
            title="Create Quotation"
            description="Build a price quotation with line items"
        />

        <QuotationForm
            :contacts="contacts"
            :projects="projects"
            :products="products"
            :statuses="statuses"
            :branches="branches"
        />
    </div>
</template>
