<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Bell } from '@lucide/vue';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { index as remindersIndex } from '@/routes/reminders';

const notifications = computed(() => usePage().props.notifications);

const isOverdue = (remindAt: string) => new Date(remindAt) < new Date();

const formatRemindAt = (remindAt: string) =>
    new Date(remindAt).toLocaleString(undefined, {
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button
                variant="ghost"
                size="icon"
                class="relative size-9"
                aria-label="Notifications"
                data-test="notifications-toggle"
            >
                <Bell class="size-5" />
                <span
                    v-if="notifications.total > 0"
                    class="absolute -top-1 -right-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-medium text-white"
                >
                    {{ notifications.total > 9 ? '9+' : notifications.total }}
                </span>
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="w-80">
            <DropdownMenuLabel>Reminders</DropdownMenuLabel>
            <DropdownMenuSeparator />

            <div
                v-if="notifications.items.length === 0"
                class="px-2 py-4 text-center text-sm text-muted-foreground"
            >
                You're all caught up.
            </div>

            <DropdownMenuItem
                v-for="item in notifications.items"
                :key="item.id"
                as-child
                class="flex-col items-start gap-1 whitespace-normal"
            >
                <Link :href="item.url ?? remindersIndex()" class="w-full">
                    <div class="flex w-full items-start justify-between gap-2">
                        <span class="text-sm font-medium">{{
                            item.title
                        }}</span>
                        <Badge
                            v-if="isOverdue(item.remind_at)"
                            variant="destructive"
                            class="shrink-0"
                        >
                            Overdue
                        </Badge>
                    </div>
                    <p
                        v-if="item.subject"
                        class="text-xs text-muted-foreground"
                    >
                        {{ item.subject }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        {{ formatRemindAt(item.remind_at) }}
                    </p>
                </Link>
            </DropdownMenuItem>

            <DropdownMenuSeparator />
            <DropdownMenuItem as-child>
                <Link
                    :href="remindersIndex()"
                    class="justify-center text-sm font-medium"
                >
                    View more
                </Link>
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
