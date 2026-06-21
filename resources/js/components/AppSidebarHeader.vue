<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Building2, ClipboardList, HardHat, Search, Users } from '@lucide/vue';
import AppearanceToggleDropdown from '@/components/AppearanceToggleDropdown.vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import HeaderUserMenu from '@/components/HeaderUserMenu.vue';
import NotificationsDropdown from '@/components/NotificationsDropdown.vue';
import { Button } from '@/components/ui/button';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { useGlobalSearch } from '@/composables/useGlobalSearch';
import { index as buildersIndex } from '@/routes/builders';
import { index as contactsIndex } from '@/routes/contacts';
import { index as projectsIndex } from '@/routes/projects';
import { index as visitReportsIndex } from '@/routes/visit-reports';
import type { BreadcrumbItem } from '@/types';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);

const { open } = useGlobalSearch();

const shortcuts = [
    {
        label: 'Builders',
        href: buildersIndex(),
        icon: HardHat,
        color: '#ea580c',
    },
    {
        label: 'Projects',
        href: projectsIndex(),
        icon: Building2,
        color: '#4f46e5',
    },
    { label: 'Contacts', href: contactsIndex(), icon: Users, color: '#2563eb' },
    {
        label: 'Visit Reports',
        href: visitReportsIndex(),
        icon: ClipboardList,
        color: '#0ea5e9',
    },
];
</script>

<template>
    <header
        class="flex h-16 shrink-0 items-center justify-between gap-2 border-b border-sidebar-border/70 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4"
    >
        <div class="flex items-center gap-2">
            <SidebarTrigger class="-ml-1" />
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </template>
        </div>

        <div class="flex items-center gap-1">
            <nav class="mr-1 hidden items-center gap-0.5 md:flex">
                <Button
                    v-for="shortcut in shortcuts"
                    :key="shortcut.label"
                    variant="ghost"
                    size="icon"
                    as-child
                    :title="shortcut.label"
                    :aria-label="shortcut.label"
                >
                    <Link :href="shortcut.href">
                        <component
                            :is="shortcut.icon"
                            class="h-4 w-4"
                            :style="{ color: shortcut.color }"
                        />
                    </Link>
                </Button>
            </nav>

            <Button
                variant="outline"
                class="relative mr-2 h-9 w-40 cursor-pointer justify-start rounded-lg text-sm text-muted-foreground sm:pr-12 md:w-60"
                @click="open"
            >
                <Search class="mr-2 h-4 w-4" />
                <span class="inline-flex">Search...</span>
                <kbd
                    class="pointer-events-none absolute top-2 right-1.5 hidden h-5 items-center gap-1 rounded border bg-muted px-1.5 font-mono text-[10px] font-medium opacity-100 select-none sm:flex"
                >
                    <span class="text-xs">⌘</span>K
                </kbd>
            </Button>
            <AppearanceToggleDropdown />
            <NotificationsDropdown />
            <HeaderUserMenu />
        </div>
    </header>
</template>
