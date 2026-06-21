<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Eye,
    Pencil,
    Plus,
    Trash2,
    Search,
    User,
    CalendarDays,
    X,
} from '@lucide/vue';
import { watchDebounced } from '@vueuse/core';
import { computed, ref } from 'vue';
import ConfirmDeleteModal from '@/components/ConfirmDeleteModal.vue';
import Heading from '@/components/Heading.vue';
import TablePagination from '@/components/TablePagination.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDate } from '@/lib/utils';
import { create, destroy, edit, index, show } from '@/routes/builders';
import type { BuilderListItem, Filters, NamedOption, Paginated } from '@/types';

const props = defineProps<{
    builders: Paginated<BuilderListItem>;
    users: NamedOption[];
    filters: Filters & {
        created_by?: string | number;
        created_from?: string;
        created_to?: string;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Builders', href: index() }],
    },
});

const search = ref(props.filters.search ?? '');
const createdBy = ref(
    props.filters.created_by ? String(props.filters.created_by) : 'all',
);
const createdFrom = ref(props.filters.created_from ?? '');
const createdTo = ref(props.filters.created_to ?? '');

const updateFilters = () => {
    router.get(
        window.location.pathname,
        {
            search: search.value || undefined,
            created_by: createdBy.value !== 'all' ? createdBy.value : undefined,
            created_from: createdFrom.value || undefined,
            created_to: createdTo.value || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
};

const hasActiveFilters = computed(
    () =>
        search.value !== '' ||
        createdBy.value !== 'all' ||
        createdFrom.value !== '' ||
        createdTo.value !== '',
);

const clearFilters = () => {
    search.value = '';
    createdBy.value = 'all';
    createdFrom.value = '';
    createdTo.value = '';
    updateFilters();
};

watchDebounced(search, () => updateFilters(), { debounce: 300 });

const deleteDialogOpen = ref(false);
const builderToDelete = ref<BuilderListItem | null>(null);

const confirmDelete = (builder: BuilderListItem) => {
    builderToDelete.value = builder;
    deleteDialogOpen.value = true;
};
</script>

<template>
    <Head title="Builders" />

    <div class="flex flex-col space-y-6">
        <div class="flex items-center justify-between">
            <Heading
                variant="small"
                title="Builders"
                description="Manage the developers behind your projects"
            />

            <Button as-child>
                <Link :href="create()"><Plus /> New builder</Link>
            </Button>
        </div>

        <Card>
            <CardHeader class="border-b">
                <div
                    class="flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-center"
                >
                    <div class="relative w-full max-w-sm">
                        <Search
                            class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                        />
                        <Input
                            v-model="search"
                            type="search"
                            placeholder="Search builders…"
                            class="px-9"
                            data-test="search-input"
                        />
                    </div>

                    <!-- Created By Filter -->
                    <Select
                        v-model="createdBy"
                        @update:model-value="updateFilters"
                    >
                        <SelectTrigger
                            class="flex w-full items-center gap-1.5 sm:w-[180px]"
                        >
                            <User class="h-4 w-4 text-[#0ea5e9]" />
                            <SelectValue placeholder="Created by" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Creators</SelectItem>
                            <SelectItem
                                v-for="user in users"
                                :key="user.id"
                                :value="String(user.id)"
                            >
                                {{ user.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>

                    <!-- Created Date Range -->
                    <div class="flex items-center gap-1.5">
                        <CalendarDays class="h-4 w-4 text-[#16a34a]" />
                        <Input
                            v-model="createdFrom"
                            type="date"
                            class="w-[150px]"
                            aria-label="Created from"
                            @change="updateFilters"
                        />
                        <span class="text-sm text-muted-foreground">to</span>
                        <Input
                            v-model="createdTo"
                            type="date"
                            class="w-[150px]"
                            aria-label="Created to"
                            @change="updateFilters"
                        />
                    </div>

                    <!-- Clear Filters -->
                    <Button
                        v-if="hasActiveFilters"
                        variant="ghost"
                        size="sm"
                        class="text-muted-foreground"
                        data-test="clear-filters"
                        @click="clearFilters"
                    >
                        <X class="h-4 w-4" /> Clear
                    </Button>
                </div>
            </CardHeader>
            <CardContent>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead class="w-12">S.No.</TableHead>
                            <TableHead>Name</TableHead>
                            <TableHead>Contact</TableHead>
                            <TableHead>Location</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead>Created</TableHead>
                            <TableHead class="text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="(builder, index) in builders.data"
                            :key="builder.id"
                        >
                            <TableCell class="font-medium text-muted-foreground">
                                {{ (builders.from ?? 1) + index }}
                            </TableCell>
                            <TableCell class="font-medium">
                                <Link
                                    :href="show(builder.id)"
                                    class="font-semibold text-foreground hover:underline"
                                >
                                    {{ builder.name }}
                                </Link>
                            </TableCell>
                            <TableCell>
                                <div>{{ builder.contact_person ?? '—' }}</div>
                                <div class="text-sm text-muted-foreground">
                                    {{ builder.phone ?? builder.email ?? '' }}
                                </div>
                            </TableCell>
                            <TableCell>
                                {{
                                    [
                                        builder.district?.name,
                                        builder.state?.name,
                                        builder.country?.name,
                                    ]
                                        .filter(Boolean)
                                        .join(', ') || '—'
                                }}
                            </TableCell>
                            <TableCell>
                                <Badge
                                    :variant="
                                        builder.is_active
                                            ? 'default'
                                            : 'secondary'
                                    "
                                >
                                    {{
                                        builder.is_active
                                            ? 'Active'
                                            : 'Inactive'
                                    }}
                                </Badge>
                            </TableCell>
                            <TableCell>
                                <div class="text-sm">
                                    {{ formatDate(builder.created_at) }}
                                </div>
                                <div class="text-xs text-muted-foreground">
                                    {{ builder.creator?.name ?? '—' }}
                                </div>
                            </TableCell>
                            <TableCell class="space-x-1.5 text-right">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    as-child
                                    class="bg-blue-50 text-blue-600 hover:bg-blue-100 hover:text-blue-800 dark:bg-blue-950/30 dark:text-blue-400 dark:hover:bg-blue-900/40 dark:hover:text-blue-300"
                                    :aria-label="`View ${builder.name}`"
                                    :data-test="`view-builder-${builder.id}`"
                                >
                                    <Link :href="show(builder.id)">
                                        <Eye class="h-4 w-4" />
                                    </Link>
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    as-child
                                    class="bg-amber-50 text-amber-600 hover:bg-amber-100 hover:text-amber-800 dark:bg-amber-950/30 dark:text-amber-400 dark:hover:bg-amber-900/40 dark:hover:text-amber-300"
                                    :aria-label="`Edit ${builder.name}`"
                                    :data-test="`edit-builder-${builder.id}`"
                                >
                                    <Link :href="edit(builder.id)">
                                        <Pencil class="h-4 w-4" />
                                    </Link>
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    class="bg-red-50 text-red-600 hover:bg-red-100 hover:text-red-800 dark:bg-red-950/30 dark:text-red-400 dark:hover:bg-red-900/40 dark:hover:text-red-300"
                                    :aria-label="`Delete ${builder.name}`"
                                    :data-test="`delete-builder-${builder.id}`"
                                    @click="confirmDelete(builder)"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </TableCell>
                        </TableRow>
                        <TableRow v-if="builders.data.length === 0">
                            <TableCell
                                :colspan="7"
                                class="text-center text-muted-foreground"
                            >
                                No builders yet.
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </CardContent>
        </Card>

        <TablePagination :links="builders.links" />
    </div>

    <ConfirmDeleteModal
        :open="deleteDialogOpen"
        title="Delete builder"
        :description="`This will permanently delete “${builderToDelete?.name}”.`"
        :delete-url="builderToDelete ? destroy.url(builderToDelete.id) : null"
        @update:open="deleteDialogOpen = $event"
    />
</template>
