<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    Building,
    Building2,
    ClipboardCheck,
    ClipboardList,
    Contact,
    FileText,
    Globe,
    HardHat,
    IdCard,
    LayoutGrid,
    Package,
    Shapes,
    Shield,
    Tags,
    UserRound,
    Users,
} from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { index as branchesIndex } from '@/routes/branches';
import { index as buildersIndex } from '@/routes/builders';
import { index as contactTypesIndex } from '@/routes/contact-types';
import { index as contactsIndex } from '@/routes/contacts';
import { index as countriesIndex } from '@/routes/countries';
import { index as customersIndex } from '@/routes/customers';
import {
    index as enquiriesIndex,
    kanban as enquiriesKanban,
} from '@/routes/enquiries';
import { index as productCategoriesIndex } from '@/routes/product-categories';
import { index as productsIndex } from '@/routes/products';
import { index as projectCategoriesIndex } from '@/routes/project-categories';
import { index as projectsIndex } from '@/routes/projects';
import { index as quotationsIndex } from '@/routes/quotations';
import { index as rolesIndex } from '@/routes/roles';
import { index as usersIndex } from '@/routes/users';
import {
    analytics as visitReportsAnalytics,
    index as visitReportsIndex,
} from '@/routes/visit-reports';
import type { NavItem } from '@/types';

const dashboardUrl = dashboard().url;

const permissions = computed(() => usePage().props.auth.permissions);

const mainNavItems = computed<NavItem[]>(() => {
    const items: NavItem[] = [
        {
            title: 'Dashboard',
            href: dashboardUrl,
            icon: LayoutGrid,
            color: '#4f46e5',
        },
    ];

    const masterSubItems = [
        permissions.value.includes('countries.view') && {
            title: 'Countries',
            href: countriesIndex(),
            icon: Globe,
            color: '#16a34a',
        },
        permissions.value.includes('project-categories.view') && {
            title: 'Project Categories',
            href: projectCategoriesIndex(),
            icon: Shapes,
            color: '#7c3aed',
        },
        permissions.value.includes('product-categories.view') && {
            title: 'Product Categories',
            href: productCategoriesIndex(),
            icon: Tags,
            color: '#ca8a04',
        },
        permissions.value.includes('contact-types.view') && {
            title: 'Contact Types',
            href: contactTypesIndex(),
            icon: Contact,
            color: '#0d9488',
        },
    ].filter((item): item is NavItem => Boolean(item));

    if (masterSubItems.length > 0) {
        items.push({
            title: 'Masters',
            href: '#',
            icon: Shapes,
            color: '#7c3aed',
            items: masterSubItems,
        });
    }

    return items;
});

const adminNavItems = computed<NavItem[]>(() => {
    const items: Array<NavItem | false> = [
        permissions.value.includes('users.view') && {
            title: 'Users',
            href: usersIndex(),
            icon: Users,
            color: '#db2777',
        },
        permissions.value.includes('roles.view') && {
            title: 'Roles',
            href: rolesIndex(),
            icon: Shield,
            color: '#9333ea',
        },
        permissions.value.includes('branches.view') && {
            title: 'Branches',
            href: branchesIndex(),
            icon: Building2,
            color: '#0891b2',
        },
    ];

    return items.filter((item): item is NavItem => Boolean(item));
});

const catalogNavItems = computed<NavItem[]>(() => {
    const items: Array<NavItem | false> = [
        permissions.value.includes('builders.view') && {
            title: 'Builders',
            href: buildersIndex(),
            icon: HardHat,
            color: '#ea580c',
        },
        permissions.value.includes('projects.view') && {
            title: 'Projects',
            href: projectsIndex(),
            icon: Building,
            color: '#4f46e5',
        },
        permissions.value.includes('products.view') && {
            title: 'Products',
            href: productsIndex(),
            icon: Package,
            color: '#059669',
        },
    ];

    return items.filter((item): item is NavItem => Boolean(item));
});

const crmNavItems = computed<NavItem[]>(() => {
    const items: Array<NavItem | false> = [
        permissions.value.includes('contacts.view') && {
            title: 'Contacts',
            href: contactsIndex(),
            icon: IdCard,
            color: '#2563eb',
        },
        permissions.value.includes('customers.view') && {
            title: 'Customers',
            href: customersIndex(),
            icon: UserRound,
            color: '#65a30d',
        },
        permissions.value.includes('enquiries.view') && {
            title: 'Enquiries',
            href: enquiriesIndex(),
            icon: ClipboardList,
            color: '#d97706',
            items: [
                { title: 'List', href: enquiriesIndex() },
                { title: 'Kanban', href: enquiriesKanban() },
            ],
        },
        permissions.value.includes('visit-reports.view') && {
            title: 'Visit Reports',
            href: visitReportsIndex(),
            icon: ClipboardCheck,
            color: '#0ea5e9',
            items: [
                { title: 'List', href: visitReportsIndex() },
                { title: 'Analytics', href: visitReportsAnalytics() },
            ],
        },
        permissions.value.includes('quotations.view') && {
            title: 'Quotations',
            href: quotationsIndex(),
            icon: FileText,
            color: '#4f46e5',
        },
    ];

    return items.filter((item): item is NavItem => Boolean(item));
});

const combinedNavItems = computed<NavItem[]>(() => {
    return [...mainNavItems.value, ...catalogNavItems.value];
});
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboardUrl">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="combinedNavItems" />
            <NavMain
                v-if="crmNavItems.length > 0"
                label="CRM"
                :items="crmNavItems"
            />
            <NavMain
                v-if="adminNavItems.length > 0"
                label="Administration"
                :items="adminNavItems"
            />
        </SidebarContent>
    </Sidebar>
</template>
