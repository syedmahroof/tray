<script setup lang="ts">
import { watch, ref, onUnmounted } from 'vue';
import { useCommand } from '@/components/ui/command';
import { router } from '@inertiajs/vue3';
import {
    Building,
    HardHat,
    IdCard,
    Loader2Icon,
    Package,
    Building2,
    ClipboardList,
    Clipboard,
} from '@lucide/vue';
import { useGlobalSearch } from '@/composables/useGlobalSearch';
import {
    CommandEmpty,
    CommandGroup,
    CommandItem,
    CommandList,
    CommandInput,
} from '@/components/ui/command';

const { filterState } = useCommand();
const { close } = useGlobalSearch();

const results = ref<{
    projects: Array<{ id: number; name: string; url: string }>;
    builders: Array<{ id: number; name: string; url: string }>;
    contacts: Array<{
        id: number;
        name: string;
        phone: string;
        email: string;
        url: string;
    }>;
    products: Array<{ id: number; name: string; url: string }>;
    customers: Array<{
        id: number;
        name: string;
        phone: string;
        email: string;
        url: string;
    }>;
    enquiries: Array<{
        id: number;
        contact_name: string;
        project_name?: string;
        product_name?: string;
        status: string;
        url: string;
    }>;
    visit_reports: Array<{
        id: number;
        visit_type: string;
        objective: string;
        visit_date: string;
        url: string;
    }>;
}>({
    projects: [],
    builders: [],
    contacts: [],
    products: [],
    customers: [],
    enquiries: [],
    visit_reports: [],
});
const isLoading = ref(false);
let searchTimeout: any = null;

const performSearch = async (query: string) => {
    if (!query.trim()) {
        results.value = {
            projects: [],
            builders: [],
            contacts: [],
            products: [],
            customers: [],
            enquiries: [],
            visit_reports: [],
        };
        return;
    }
    isLoading.value = true;
    try {
        const response = await fetch(
            `/global-search?q=${encodeURIComponent(query)}`,
        );
        if (response.ok) {
            results.value = await response.json();
        }
    } catch (e) {
        console.error(e);
    } finally {
        isLoading.value = false;
    }
};

watch(
    () => filterState.search,
    (newVal) => {
        if (searchTimeout) {
            clearTimeout(searchTimeout);
        }
        searchTimeout = setTimeout(() => {
            performSearch(newVal);
        }, 300);
    },
);

const navigateTo = (url: string) => {
    close();
    filterState.search = '';
    router.visit(url);
};

onUnmounted(() => {
    if (searchTimeout) {
        clearTimeout(searchTimeout);
    }
});
</script>

<template>
    <CommandInput
        placeholder="Search projects, builders, contacts, products, customers, enquiries..."
    />
    <CommandList class="max-h-[300px] overflow-y-auto">
        <div v-if="isLoading" class="flex items-center justify-center p-4">
            <Loader2Icon class="h-5 w-5 animate-spin text-muted-foreground" />
        </div>

        <template v-else>
            <CommandEmpty
                v-if="
                    filterState.search.trim() &&
                    !results.projects.length &&
                    !results.builders.length &&
                    !results.contacts.length &&
                    !results.products.length &&
                    !results.customers.length &&
                    !results.enquiries.length &&
                    !results.visit_reports.length
                "
            >
                No results found.
            </CommandEmpty>

            <CommandGroup v-if="results.projects.length" heading="Projects">
                <CommandItem
                    v-for="project in results.projects"
                    :key="'project-' + project.id"
                    :value="project.name"
                    @select="navigateTo(project.url)"
                    class="flex cursor-pointer items-center"
                >
                    <Building class="mr-2 h-4 w-4 shrink-0 text-indigo-500" />
                    <span class="truncate">{{ project.name }}</span>
                </CommandItem>
            </CommandGroup>

            <CommandGroup v-if="results.builders.length" heading="Builders">
                <CommandItem
                    v-for="builder in results.builders"
                    :key="'builder-' + builder.id"
                    :value="builder.name"
                    @select="navigateTo(builder.url)"
                    class="flex cursor-pointer items-center"
                >
                    <HardHat class="mr-2 h-4 w-4 shrink-0 text-orange-500" />
                    <span class="truncate">{{ builder.name }}</span>
                </CommandItem>
            </CommandGroup>

            <CommandGroup v-if="results.contacts.length" heading="Contacts">
                <CommandItem
                    v-for="contact in results.contacts"
                    :key="'contact-' + contact.id"
                    :value="
                        contact.name +
                        ' ' +
                        (contact.phone || '') +
                        ' ' +
                        (contact.email || '')
                    "
                    @select="navigateTo(contact.url)"
                    class="flex cursor-pointer items-center justify-between gap-2"
                >
                    <div class="flex min-w-0 items-center">
                        <IdCard class="mr-2 h-4 w-4 shrink-0 text-blue-500" />
                        <span class="truncate font-medium">{{
                            contact.name
                        }}</span>
                    </div>
                    <div
                        class="flex shrink-0 gap-3 text-xs text-muted-foreground"
                    >
                        <span v-if="contact.phone" class="hidden sm:inline">{{
                            contact.phone
                        }}</span>
                        <span v-if="contact.email" class="hidden md:inline">{{
                            contact.email
                        }}</span>
                    </div>
                </CommandItem>
            </CommandGroup>

            <CommandGroup v-if="results.products.length" heading="Products">
                <CommandItem
                    v-for="product in results.products"
                    :key="'product-' + product.id"
                    :value="product.name"
                    @select="navigateTo(product.url)"
                    class="flex cursor-pointer items-center"
                >
                    <Package class="mr-2 h-4 w-4 shrink-0 text-emerald-500" />
                    <span class="truncate">{{ product.name }}</span>
                </CommandItem>
            </CommandGroup>

            <CommandGroup v-if="results.customers.length" heading="Customers">
                <CommandItem
                    v-for="customer in results.customers"
                    :key="'customer-' + customer.id"
                    :value="
                        customer.name +
                        ' ' +
                        (customer.phone || '') +
                        ' ' +
                        (customer.email || '')
                    "
                    @select="navigateTo(customer.url)"
                    class="flex cursor-pointer items-center justify-between gap-2"
                >
                    <div class="flex min-w-0 items-center">
                        <Building2
                            class="mr-2 h-4 w-4 shrink-0 text-cyan-500"
                        />
                        <span class="truncate font-medium">{{
                            customer.name
                        }}</span>
                    </div>
                    <div
                        class="flex shrink-0 gap-3 text-xs text-muted-foreground"
                    >
                        <span v-if="customer.phone" class="hidden sm:inline">{{
                            customer.phone
                        }}</span>
                        <span v-if="customer.email" class="hidden md:inline">{{
                            customer.email
                        }}</span>
                    </div>
                </CommandItem>
            </CommandGroup>

            <CommandGroup v-if="results.enquiries.length" heading="Enquiries">
                <CommandItem
                    v-for="enquiry in results.enquiries"
                    :key="'enquiry-' + enquiry.id"
                    :value="
                        enquiry.contact_name +
                        ' ' +
                        (enquiry.project_name || '') +
                        ' ' +
                        (enquiry.product_name || '') +
                        ' ' +
                        enquiry.status
                    "
                    @select="navigateTo(enquiry.url)"
                    class="flex cursor-pointer items-center justify-between gap-2"
                >
                    <div class="flex min-w-0 items-center">
                        <ClipboardList
                            class="mr-2 h-4 w-4 shrink-0 text-amber-500"
                        />
                        <span class="truncate font-medium">{{
                            enquiry.contact_name
                        }}</span>
                        <span
                            v-if="enquiry.project_name || enquiry.product_name"
                            class="ml-2 truncate text-xs text-muted-foreground"
                        >
                            ({{
                                [enquiry.project_name, enquiry.product_name]
                                    .filter(Boolean)
                                    .join(' - ')
                            }})
                        </span>
                    </div>
                    <div class="shrink-0 text-xs">
                        <span
                            class="rounded-full bg-muted px-1.5 py-0.5 text-[10px] font-medium capitalize"
                            >{{ enquiry.status.replace('_', ' ') }}</span
                        >
                    </div>
                </CommandItem>
            </CommandGroup>

            <CommandGroup
                v-if="results.visit_reports.length"
                heading="Visit Reports"
            >
                <CommandItem
                    v-for="report in results.visit_reports"
                    :key="'report-' + report.id"
                    :value="
                        report.visit_type +
                        ' ' +
                        report.objective +
                        ' ' +
                        report.visit_date
                    "
                    @select="navigateTo(report.url)"
                    class="flex cursor-pointer items-center justify-between gap-2"
                >
                    <div class="flex min-w-0 items-center">
                        <Clipboard
                            class="mr-2 h-4 w-4 shrink-0 text-purple-500"
                        />
                        <span class="truncate font-medium">{{
                            report.objective
                        }}</span>
                        <span
                            class="ml-2 truncate text-xs text-muted-foreground"
                        >
                            ({{ report.visit_type }})
                        </span>
                    </div>
                    <div class="shrink-0 text-xs text-muted-foreground">
                        {{ report.visit_date }}
                    </div>
                </CommandItem>
            </CommandGroup>
        </template>
    </CommandList>
</template>
