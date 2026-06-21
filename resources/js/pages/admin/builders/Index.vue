<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Eye, Pencil, Plus, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import ConfirmDeleteModal from '@/components/ConfirmDeleteModal.vue';
import Heading from '@/components/Heading.vue';
import SearchInput from '@/components/SearchInput.vue';
import TablePagination from '@/components/TablePagination.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { create, destroy, edit, index, show } from '@/routes/builders';
import type { BuilderListItem, Filters, Paginated } from '@/types';

defineProps<{
    builders: Paginated<BuilderListItem>;
    filters: Filters;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Builders', href: index() }],
    },
});

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
            <CardContent>
                <div class="mb-4">
                    <SearchInput
                        :model-value="filters.search"
                        placeholder="Search builders…"
                    />
                </div>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Name</TableHead>
                            <TableHead>Contact</TableHead>
                            <TableHead>Location</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead class="text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="builder in builders.data"
                            :key="builder.id"
                        >
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
                            <TableCell class="text-right">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    as-child
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
                                :colspan="5"
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
