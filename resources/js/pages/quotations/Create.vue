<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import QuotationForm from '@/components/QuotationForm.vue';
import { create, index } from '@/routes/quotations';
import type {
    Branch,
    NamedOption,
    QuotationProductOption,
    RateTier,
} from '@/types';

type ContactOption = NamedOption & {
    phone: string | null;
    email: string | null;
    contact_type: NamedOption | null;
};

defineProps<{
    customers: NamedOption[];
    contacts: ContactOption[];
    projects: NamedOption[];
    enquiries: NamedOption[];
    builders: NamedOption[];
    products: QuotationProductOption[];
    statuses: string[];
    rateTiers: Record<RateTier, string>;
    gstSlabs: Record<string, number>;
    branches: Branch[];
    defaultBranchId: number | null;
    defaults: {
        customer_id: number | null;
        contact_id: number | null;
        project_id: number | null;
        enquiry_id: number | null;
        builder_id: number | null;
    };
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
            :customers="customers"
            :contacts="contacts"
            :projects="projects"
            :enquiries="enquiries"
            :builders="builders"
            :products="products"
            :statuses="statuses"
            :rate-tiers="rateTiers"
            :gst-slabs="gstSlabs"
            :branches="branches"
            :default-branch-id="defaultBranchId"
            :defaults="defaults"
        />
    </div>
</template>
