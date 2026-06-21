<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronRight } from '@lucide/vue';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import type { NavItem } from '@/types';

defineProps<{
    label: string;
    items: NavItem[];
}>();

const { isCurrentUrl } = useCurrentUrl();

const isParentActive = (item: NavItem) =>
    item.items?.some((child) => isCurrentUrl(child.href)) ?? false;
</script>

<template>
    <SidebarGroup class="px-2 py-0">
        <SidebarGroupLabel>{{ label }}</SidebarGroupLabel>
        <SidebarMenu>
            <template v-for="item in items" :key="item.title">
                <Collapsible
                    v-if="item.items && item.items.length > 0"
                    as-child
                    :default-open="isParentActive(item)"
                    class="group/collapsible"
                >
                    <SidebarMenuItem>
                        <CollapsibleTrigger as-child>
                            <SidebarMenuButton
                                :is-active="isParentActive(item)"
                                :tooltip="item.title"
                            >
                                <component
                                    :is="item.icon"
                                    :style="
                                        item.color
                                            ? { color: item.color }
                                            : undefined
                                    "
                                />
                                <span>{{ item.title }}</span>
                                <ChevronRight
                                    class="ml-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90"
                                />
                            </SidebarMenuButton>
                        </CollapsibleTrigger>
                        <CollapsibleContent>
                            <SidebarMenuSub>
                                <SidebarMenuSubItem
                                    v-for="child in item.items"
                                    :key="child.title"
                                >
                                    <SidebarMenuSubButton
                                        as-child
                                        :is-active="isCurrentUrl(child.href)"
                                    >
                                        <Link :href="child.href">
                                            <component
                                                :is="child.icon"
                                                :style="
                                                    child.color
                                                        ? { color: child.color }
                                                        : undefined
                                                "
                                            />
                                            <span>{{ child.title }}</span>
                                        </Link>
                                    </SidebarMenuSubButton>
                                </SidebarMenuSubItem>
                            </SidebarMenuSub>
                        </CollapsibleContent>
                    </SidebarMenuItem>
                </Collapsible>

                <SidebarMenuItem v-else>
                    <SidebarMenuButton
                        as-child
                        :is-active="isCurrentUrl(item.href)"
                        :tooltip="item.title"
                    >
                        <Link :href="item.href">
                            <component
                                :is="item.icon"
                                :style="
                                    item.color
                                        ? { color: item.color }
                                        : undefined
                                "
                            />
                            <span>{{ item.title }}</span>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </template>
        </SidebarMenu>
    </SidebarGroup>
</template>
