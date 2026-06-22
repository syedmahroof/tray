<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    Building,
    Building2,
    Clipboard,
    ClipboardList,
    HardHat,
    IdCard,
    Loader2Icon,
    Package,
    Search,
} from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

type Entity = { id: number; name: string; url: string };
type PersonEntity = Entity & { phone: string | null; email: string | null };
type EnquiryEntity = {
    id: number;
    contact_name: string;
    project_name?: string | null;
    product_name?: string | null;
    status: string;
    url: string;
};
type VisitReportEntity = {
    id: number;
    visit_type: string;
    objective: string;
    visit_date: string;
    url: string;
};

type SearchResults = {
    projects: Entity[];
    builders: Entity[];
    contacts: PersonEntity[];
    products: Entity[];
    customers: PersonEntity[];
    enquiries: EnquiryEntity[];
    visit_reports: VisitReportEntity[];
};

const emptyResults = (): SearchResults => ({
    projects: [],
    builders: [],
    contacts: [],
    products: [],
    customers: [],
    enquiries: [],
    visit_reports: [],
});

const query = ref('');
const results = ref<SearchResults>(emptyResults());
const isLoading = ref(false);
const open = ref(false);
const rootRef = ref<HTMLElement | null>(null);
const inputRef = ref<HTMLInputElement | null>(null);

let searchTimeout: ReturnType<typeof setTimeout> | null = null;
let controller: AbortController | null = null;

const totalResults = computed(
    () =>
        results.value.projects.length +
        results.value.builders.length +
        results.value.contacts.length +
        results.value.products.length +
        results.value.customers.length +
        results.value.enquiries.length +
        results.value.visit_reports.length,
);

const performSearch = async (value: string) => {
    const trimmed = value.trim();

    if (!trimmed) {
        results.value = emptyResults();
        isLoading.value = false;
        open.value = false;

        return;
    }

    isLoading.value = true;
    open.value = true;

    controller?.abort();
    controller = new AbortController();

    try {
        const response = await fetch(
            `/global-search?q=${encodeURIComponent(trimmed)}`,
            {
                headers: { Accept: 'application/json' },
                signal: controller.signal,
            },
        );

        if (response.ok) {
            results.value = await response.json();
        }
    } catch (error) {
        if (!(error instanceof DOMException && error.name === 'AbortError')) {
            console.error(error);
        }
    } finally {
        isLoading.value = false;
    }
};

watch(query, (value) => {
    if (searchTimeout) {
        clearTimeout(searchTimeout);
    }

    searchTimeout = setTimeout(() => performSearch(value), 250);
});

const navigateTo = (url: string) => {
    open.value = false;
    query.value = '';
    results.value = emptyResults();
    router.visit(url);
};

const handleClickOutside = (event: MouseEvent) => {
    if (rootRef.value && !rootRef.value.contains(event.target as Node)) {
        open.value = false;
    }
};

const handleKeyDown = (event: KeyboardEvent) => {
    if (event.key === 'k' && (event.metaKey || event.ctrlKey)) {
        event.preventDefault();
        inputRef.value?.focus();
    }

    if (event.key === 'Escape') {
        open.value = false;
        inputRef.value?.blur();
    }
};

const onFocus = () => {
    if (query.value.trim()) {
        open.value = true;
    }
};

onMounted(() => {
    document.addEventListener('keydown', handleKeyDown);
    document.addEventListener('click', handleClickOutside);
});

onBeforeUnmount(() => {
    document.removeEventListener('keydown', handleKeyDown);
    document.removeEventListener('click', handleClickOutside);

    if (searchTimeout) {
        clearTimeout(searchTimeout);
    }

    controller?.abort();
});
</script>

<template>
    <div ref="rootRef" class="relative w-full max-w-xs sm:w-72 md:w-80">
        <Search
            class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
        />
        <input
            ref="inputRef"
            v-model="query"
            type="search"
            placeholder="Search projects, contacts, builders…"
            data-test="global-search-input"
            class="h-9 w-full rounded-lg border border-input bg-background px-9 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
            @focus="onFocus"
        />
        <kbd
            v-if="!query"
            class="pointer-events-none absolute top-1/2 right-2.5 hidden h-5 -translate-y-1/2 items-center gap-1 rounded border bg-muted px-1.5 font-mono text-[10px] font-medium select-none sm:flex"
        >
            <span class="text-xs">⌘</span>K
        </kbd>

        <div
            v-if="open"
            class="absolute right-0 z-50 mt-2 max-h-[70vh] w-[26rem] max-w-[calc(100vw-2rem)] overflow-y-auto rounded-md border bg-popover p-1 text-popover-foreground shadow-md"
        >
            <div
                v-if="isLoading && totalResults === 0"
                class="flex items-center justify-center p-4"
            >
                <Loader2Icon
                    class="h-5 w-5 animate-spin text-muted-foreground"
                />
            </div>

            <div
                v-else-if="totalResults === 0"
                class="p-4 text-center text-sm text-muted-foreground"
            >
                No results found.
            </div>

            <template v-else>
                <!-- Projects -->
                <div v-if="results.projects.length" class="py-1">
                    <p
                        class="px-2 py-1 text-xs font-medium text-muted-foreground"
                    >
                        Projects
                    </p>
                    <button
                        v-for="project in results.projects"
                        :key="'project-' + project.id"
                        type="button"
                        class="flex w-full items-center gap-2 rounded-sm px-2 py-1.5 text-left text-sm hover:bg-accent hover:text-accent-foreground"
                        @click="navigateTo(project.url)"
                    >
                        <Building class="h-4 w-4 shrink-0 text-indigo-500" />
                        <span class="truncate">{{ project.name }}</span>
                    </button>
                </div>

                <!-- Builders -->
                <div v-if="results.builders.length" class="py-1">
                    <p
                        class="px-2 py-1 text-xs font-medium text-muted-foreground"
                    >
                        Builders
                    </p>
                    <button
                        v-for="builder in results.builders"
                        :key="'builder-' + builder.id"
                        type="button"
                        class="flex w-full items-center gap-2 rounded-sm px-2 py-1.5 text-left text-sm hover:bg-accent hover:text-accent-foreground"
                        @click="navigateTo(builder.url)"
                    >
                        <HardHat class="h-4 w-4 shrink-0 text-orange-500" />
                        <span class="truncate">{{ builder.name }}</span>
                    </button>
                </div>

                <!-- Contacts -->
                <div v-if="results.contacts.length" class="py-1">
                    <p
                        class="px-2 py-1 text-xs font-medium text-muted-foreground"
                    >
                        Contacts
                    </p>
                    <button
                        v-for="contact in results.contacts"
                        :key="'contact-' + contact.id"
                        type="button"
                        class="flex w-full items-center justify-between gap-2 rounded-sm px-2 py-1.5 text-left text-sm hover:bg-accent hover:text-accent-foreground"
                        @click="navigateTo(contact.url)"
                    >
                        <span class="flex min-w-0 items-center gap-2">
                            <IdCard class="h-4 w-4 shrink-0 text-blue-500" />
                            <span class="truncate font-medium">{{
                                contact.name
                            }}</span>
                        </span>
                        <span class="shrink-0 text-xs text-muted-foreground">{{
                            contact.phone ?? contact.email ?? ''
                        }}</span>
                    </button>
                </div>

                <!-- Products -->
                <div v-if="results.products.length" class="py-1">
                    <p
                        class="px-2 py-1 text-xs font-medium text-muted-foreground"
                    >
                        Products
                    </p>
                    <button
                        v-for="product in results.products"
                        :key="'product-' + product.id"
                        type="button"
                        class="flex w-full items-center gap-2 rounded-sm px-2 py-1.5 text-left text-sm hover:bg-accent hover:text-accent-foreground"
                        @click="navigateTo(product.url)"
                    >
                        <Package class="h-4 w-4 shrink-0 text-emerald-500" />
                        <span class="truncate">{{ product.name }}</span>
                    </button>
                </div>

                <!-- Customers -->
                <div v-if="results.customers.length" class="py-1">
                    <p
                        class="px-2 py-1 text-xs font-medium text-muted-foreground"
                    >
                        Customers
                    </p>
                    <button
                        v-for="customer in results.customers"
                        :key="'customer-' + customer.id"
                        type="button"
                        class="flex w-full items-center justify-between gap-2 rounded-sm px-2 py-1.5 text-left text-sm hover:bg-accent hover:text-accent-foreground"
                        @click="navigateTo(customer.url)"
                    >
                        <span class="flex min-w-0 items-center gap-2">
                            <Building2 class="h-4 w-4 shrink-0 text-cyan-500" />
                            <span class="truncate font-medium">{{
                                customer.name
                            }}</span>
                        </span>
                        <span class="shrink-0 text-xs text-muted-foreground">{{
                            customer.phone ?? customer.email ?? ''
                        }}</span>
                    </button>
                </div>

                <!-- Enquiries -->
                <div v-if="results.enquiries.length" class="py-1">
                    <p
                        class="px-2 py-1 text-xs font-medium text-muted-foreground"
                    >
                        Enquiries
                    </p>
                    <button
                        v-for="enquiry in results.enquiries"
                        :key="'enquiry-' + enquiry.id"
                        type="button"
                        class="flex w-full items-center justify-between gap-2 rounded-sm px-2 py-1.5 text-left text-sm hover:bg-accent hover:text-accent-foreground"
                        @click="navigateTo(enquiry.url)"
                    >
                        <span class="flex min-w-0 items-center gap-2">
                            <ClipboardList
                                class="h-4 w-4 shrink-0 text-amber-500"
                            />
                            <span class="truncate font-medium">{{
                                enquiry.contact_name
                            }}</span>
                            <span
                                v-if="
                                    enquiry.project_name || enquiry.product_name
                                "
                                class="truncate text-xs text-muted-foreground"
                            >
                                ({{
                                    [enquiry.project_name, enquiry.product_name]
                                        .filter(Boolean)
                                        .join(' - ')
                                }})
                            </span>
                        </span>
                        <span
                            class="shrink-0 rounded-full bg-muted px-1.5 py-0.5 text-[10px] font-medium capitalize"
                            >{{ enquiry.status.replace('_', ' ') }}</span
                        >
                    </button>
                </div>

                <!-- Visit Reports -->
                <div v-if="results.visit_reports.length" class="py-1">
                    <p
                        class="px-2 py-1 text-xs font-medium text-muted-foreground"
                    >
                        Visit Reports
                    </p>
                    <button
                        v-for="report in results.visit_reports"
                        :key="'report-' + report.id"
                        type="button"
                        class="flex w-full items-center justify-between gap-2 rounded-sm px-2 py-1.5 text-left text-sm hover:bg-accent hover:text-accent-foreground"
                        @click="navigateTo(report.url)"
                    >
                        <span class="flex min-w-0 items-center gap-2">
                            <Clipboard
                                class="h-4 w-4 shrink-0 text-purple-500"
                            />
                            <span class="truncate font-medium">{{
                                report.objective
                            }}</span>
                            <span
                                class="truncate text-xs text-muted-foreground"
                            >
                                ({{ report.visit_type }})
                            </span>
                        </span>
                        <span class="shrink-0 text-xs text-muted-foreground">{{
                            report.visit_date
                        }}</span>
                    </button>
                </div>
            </template>
        </div>
    </div>
</template>
