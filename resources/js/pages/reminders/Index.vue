<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import SearchInput from '@/components/SearchInput.vue';
import TablePagination from '@/components/TablePagination.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { index } from '@/routes/reminders';
import type { Filters, Paginated, ReminderNotification } from '@/types';

defineProps<{
    reminders: Paginated<ReminderNotification>;
    filters: Filters;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Reminders', href: index() }],
    },
});

const isOverdue = (remindAt: string) => new Date(remindAt) < new Date();

const formatRemindAt = (remindAt: string) =>
    new Date(remindAt).toLocaleString(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
</script>

<template>
    <Head title="Reminders" />

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            title="Reminders"
            description="Your due and overdue reminders"
        />

        <Card>
            <CardContent>
                <div class="mb-4">
                    <SearchInput
                        :model-value="filters.search"
                        placeholder="Search reminders…"
                    />
                </div>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Title</TableHead>
                            <TableHead>Related to</TableHead>
                            <TableHead>Due</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="reminder in reminders.data"
                            :key="reminder.id"
                        >
                            <TableCell class="font-medium">
                                <Link
                                    v-if="reminder.url"
                                    :href="reminder.url"
                                    class="hover:underline"
                                >
                                    {{ reminder.title }}
                                </Link>
                                <span v-else>{{ reminder.title }}</span>
                            </TableCell>
                            <TableCell>{{ reminder.subject ?? '—' }}</TableCell>
                            <TableCell>
                                <div class="flex items-center gap-2">
                                    {{ formatRemindAt(reminder.remind_at) }}
                                    <Badge
                                        v-if="isOverdue(reminder.remind_at)"
                                        variant="destructive"
                                    >
                                        Overdue
                                    </Badge>
                                </div>
                            </TableCell>
                        </TableRow>
                        <TableRow v-if="reminders.data.length === 0">
                            <TableCell
                                :colspan="3"
                                class="text-center text-muted-foreground"
                            >
                                You're all caught up.
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </CardContent>
        </Card>

        <TablePagination :links="reminders.links" />
    </div>
</template>
