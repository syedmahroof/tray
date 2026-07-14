<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import QuotationForm from '@/components/QuotationForm.vue';
import { edit, index, show } from '@/routes/quotations';
import type { Branch, NamedOption, QuotationDetail } from '@/types';

type ProductOption = NamedOption & {
    price: string | null;
    taxable_amount: string | null;
    hsn_code: string | null;
    tax_percentage: string;
};
type ContactOption = NamedOption & {
    phone: string | null;
    email: string | null;
    contact_type: NamedOption | null;
};

defineProps<{
    quotation: QuotationDetail;
    contacts: ContactOption[];
    projects: NamedOption[];
    enquiries: NamedOption[];
    builders: NamedOption[];
    products: ProductOption[];
    statuses: string[];
    gstSlabs: Record<string, number>;
    branches: Branch[];
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
            :contacts="contacts"
            :projects="projects"
            :enquiries="enquiries"
            :builders="builders"
            :products="products"
            :statuses="statuses"
            :gst-slabs="gstSlabs"
            :branches="branches"
        />
    </div>
</template>
