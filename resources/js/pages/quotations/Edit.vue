<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import QuotationForm from '@/components/QuotationForm.vue';
import { edit, index, show } from '@/routes/quotations';
import type {
    Branch,
    NamedOption,
    QuotationDetail,
    QuotationProductOption,
    RateTier,
} from '@/types';

type ContactOption = NamedOption & {
    phone: string | null;
    email: string | null;
    contact_type: NamedOption | null;
};

defineProps<{
    quotation: QuotationDetail;
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
}>();

defineOptions({
    layout: (props: { quotation: QuotationDetail }) => ({
        breadcrumbs: [
            { title: 'Quotations', href: index() },
            {
                title: props.quotation.number,
                href: show(props.quotation.id),
            },
            { title: 'Edit', href: edit(props.quotation.id) },
        ],
    }),
});
</script>

<template>
    <Head :title="`Edit ${quotation.number}`" />

    <div class="flex flex-col space-y-6">
        <div>
            <Link
                :href="show(quotation.id)"
                class="inline-flex items-center gap-1 text-sm text-muted-foreground hover:underline"
            >
                <ArrowLeft class="h-4 w-4" /> Back
            </Link>
        </div>

        <Heading
            variant="small"
            :title="`Edit ${quotation.number}`"
            description="Update quotation details and items"
        />

        <QuotationForm
            :quotation="quotation"
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
        />
    </div>
</template>
