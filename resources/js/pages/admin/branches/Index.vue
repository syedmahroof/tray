<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2 } from '@lucide/vue';
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
import { create, destroy, edit, index } from '@/routes/branches';
import type { Branch, Filters, Paginated } from '@/types';

defineProps<{
    branches: Paginated<Branch>;
    filters: Filters;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Branches', href: index() }],
    },
});

const deleteDialogOpen = ref(false);
const branchToDelete = ref<Branch | null>(null);

const confirmDelete = (branch: Branch) => {
    branchToDelete.value = branch;
    deleteDialogOpen.value = true;
};
</script>

<template>
    <Head title="Branches" />

    <div class="flex flex-col space-y-6">
        <div class="flex items-center justify-between">
            <Heading
                variant="small"
                title="Branches"
                description="Manage the branches users are assigned to"
            />

            <Button as-child>
                <Link :href="create()"><Plus /> New branch</Link>
            </Button>
        </div>

        <Card>
            <CardContent>
                <div class="mb-4">
                    <SearchInput
                        :model-value="filters.search"
                        placeholder="Search branches…"
                    />
                </div>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Name</TableHead>
                            <TableHead>Code</TableHead>
                            <TableHead>City</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead class="text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="branch in branches.data"
                            :key="branch.id"
                        >
                            <TableCell class="font-medium">{{
                                branch.name
                            }}</TableCell>
                            <TableCell>{{ branch.code }}</TableCell>
                            <TableCell>{{ branch.city ?? '—' }}</TableCell>
                            <TableCell>
                                <Badge
                                    :variant="
                                        branch.is_active
                                            ? 'default'
                                            : 'secondary'
                                    "
                                >
                                    {{
                                        branch.is_active ? 'Active' : 'Inactive'
                                    }}
                                </Badge>
                            </TableCell>
                            <TableCell class="text-right">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    as-child
                                    :aria-label="`Edit ${branch.name}`"
                                    :data-test="`edit-branch-${branch.id}`"
                                >
                                    <Link :href="edit(branch.id)">
                                        <Pencil class="h-4 w-4" />
                                    </Link>
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    :aria-label="`Delete ${branch.name}`"
                                    :data-test="`delete-branch-${branch.id}`"
                                    @click="confirmDelete(branch)"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </TableCell>
                        </TableRow>
                        <TableRow v-if="branches.data.length === 0">
                            <TableCell
                                :colspan="5"
                                class="text-center text-muted-foreground"
                            >
                                No branches yet.
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </CardContent>
        </Card>

        <TablePagination :links="branches.links" />
    </div>

    <ConfirmDeleteModal
        :open="deleteDialogOpen"
        title="Delete branch"
        :description="`This will permanently delete the branch “${branchToDelete?.name}”.`"
        :delete-url="branchToDelete ? destroy.url(branchToDelete.id) : null"
        @update:open="deleteDialogOpen = $event"
    />
</template>
