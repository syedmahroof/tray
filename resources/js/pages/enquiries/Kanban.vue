<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { LayoutGrid, Plus } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    create,
    index as enquiriesIndex,
    kanban,
    show,
    status as updateStatus,
} from '@/routes/enquiries';
import type { EnquiryListItem, EnquiryStatus } from '@/types';

const props = defineProps<{
    enquiriesByStatus: Record<EnquiryStatus, EnquiryListItem[]>;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Enquiries', href: enquiriesIndex() },
            { title: 'Kanban', href: kanban() },
        ],
    },
});

const columns: { status: EnquiryStatus; label: string; color: string }[] = [
    { status: 'new', label: 'New', color: '#3b82f6' },
    { status: 'in_progress', label: 'In progress', color: '#f59e0b' },
    { status: 'converted', label: 'Converted', color: '#10b981' },
    { status: 'lost', label: 'Lost', color: '#ef4444' },
];

const board = ref(cloneBoard(props.enquiriesByStatus));

watch(
    () => props.enquiriesByStatus,
    (value) => (board.value = cloneBoard(value)),
);

function cloneBoard(value: Record<EnquiryStatus, EnquiryListItem[]>) {
    return Object.fromEntries(
        Object.entries(value).map(([key, items]) => [key, [...items]]),
    ) as Record<EnquiryStatus, EnquiryListItem[]>;
}

const draggedEnquiry = ref<EnquiryListItem | null>(null);
const draggedFrom = ref<EnquiryStatus | null>(null);
const dragOverStatus = ref<EnquiryStatus | null>(null);

const onDragStart = (enquiry: EnquiryListItem, from: EnquiryStatus) => {
    draggedEnquiry.value = enquiry;
    draggedFrom.value = from;
};

const onDragEnd = () => {
    draggedEnquiry.value = null;
    draggedFrom.value = null;
    dragOverStatus.value = null;
};

const onDrop = (to: EnquiryStatus) => {
    const enquiry = draggedEnquiry.value;
    const from = draggedFrom.value;
    dragOverStatus.value = null;

    if (!enquiry || !from || from === to) {
        return;
    }

    board.value[from] = board.value[from].filter((e) => e.id !== enquiry.id);
    board.value[to] = [...board.value[to], { ...enquiry, status: to }];

    router.patch(
        updateStatus.url(enquiry.id),
        { status: to },
        {
            preserveScroll: true,
            preserveState: true,
            onError: () => (board.value = cloneBoard(props.enquiriesByStatus)),
        },
    );
};

const statusVariant = (status: string) => {
    if (status === 'converted') {
        return 'default' as const;
    }

    if (status === 'lost') {
        return 'destructive' as const;
    }

    if (status === 'in_progress') {
        return 'outline' as const;
    }

    return 'secondary' as const;
};

const columnCounts = computed(() =>
    columns.map((column) => ({
        ...column,
        count: board.value[column.status]?.length ?? 0,
    })),
);
</script>

<template>
    <Head title="Enquiries — Kanban" />

    <div class="flex flex-col space-y-6">
        <div class="flex items-center justify-between">
            <Heading
                variant="small"
                title="Enquiries"
                description="Drag a card to a different column to update its status"
            />

            <div class="flex items-center gap-2">
                <Button variant="outline" as-child>
                    <Link :href="enquiriesIndex()">
                        <LayoutGrid /> List view
                    </Link>
                </Button>
                <Button as-child>
                    <Link :href="create()"><Plus /> New enquiry</Link>
                </Button>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-4">
            <div
                v-for="column in columnCounts"
                :key="column.status"
                class="flex flex-col gap-3 rounded-lg border bg-muted/30 p-3 transition-colors"
                :class="
                    dragOverStatus === column.status
                        ? 'border-primary bg-muted/60'
                        : ''
                "
                :data-test="`kanban-column-${column.status}`"
                @dragover.prevent="dragOverStatus = column.status"
                @dragleave="dragOverStatus = null"
                @drop.prevent="onDrop(column.status)"
            >
                <div class="flex items-center justify-between px-1">
                    <div class="flex items-center gap-2">
                        <span
                            class="size-2 rounded-full"
                            :style="{ backgroundColor: column.color }"
                        />
                        <span class="text-sm font-medium">{{
                            column.label
                        }}</span>
                    </div>
                    <Badge variant="secondary">{{ column.count }}</Badge>
                </div>

                <div class="flex min-h-16 flex-col gap-2">
                    <Card
                        v-for="enquiry in board[column.status]"
                        :key="enquiry.id"
                        draggable="true"
                        class="cursor-grab gap-2 py-3 active:cursor-grabbing"
                        :data-test="`kanban-card-${enquiry.id}`"
                        @dragstart="onDragStart(enquiry, column.status)"
                        @dragend="onDragEnd"
                    >
                        <CardContent class="space-y-1 px-3">
                            <Link
                                :href="show(enquiry.id)"
                                class="text-sm font-medium hover:underline"
                            >
                                {{ enquiry.contact.name }}
                            </Link>
                            <p class="text-xs text-muted-foreground">
                                {{ enquiry.project?.name ?? 'No project' }}
                            </p>
                            <div class="flex items-center justify-between pt-1">
                                <Badge
                                    :variant="statusVariant(enquiry.status)"
                                    class="capitalize"
                                >
                                    {{ enquiry.status.replace('_', ' ') }}
                                </Badge>
                                <span class="text-xs text-muted-foreground">
                                    {{ enquiry.assignee?.name ?? 'Unassigned' }}
                                </span>
                            </div>
                        </CardContent>
                    </Card>

                    <p
                        v-if="board[column.status]?.length === 0"
                        class="px-1 py-4 text-center text-xs text-muted-foreground"
                    >
                        No enquiries
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
