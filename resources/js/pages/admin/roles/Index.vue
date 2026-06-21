<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import ConfirmDeleteModal from '@/components/ConfirmDeleteModal.vue';
import Heading from '@/components/Heading.vue';
import SearchInput from '@/components/SearchInput.vue';
import TablePagination from '@/components/TablePagination.vue';
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
import { create, destroy, edit, index } from '@/routes/roles';
import type { Filters, Paginated, RoleSummary } from '@/types';

defineProps<{
    roles: Paginated<RoleSummary>;
    filters: Filters;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Roles', href: index() }],
    },
});

const deleteDialogOpen = ref(false);
const roleToDelete = ref<RoleSummary | null>(null);

const confirmDelete = (role: RoleSummary) => {
    roleToDelete.value = role;
    deleteDialogOpen.value = true;
};
</script>

<template>
    <Head title="Roles" />

    <div class="flex flex-col space-y-6">
        <div class="flex items-center justify-between">
            <Heading
                variant="small"
                title="Roles"
                description="Manage roles and their permissions"
            />

            <Button as-child>
                <Link :href="create()"><Plus /> New role</Link>
            </Button>
        </div>

        <Card>
            <CardContent>
                <div class="mb-4">
                    <SearchInput
                        :model-value="filters.search"
                        placeholder="Search roles…"
                    />
                </div>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Name</TableHead>
                            <TableHead>Permissions</TableHead>
                            <TableHead>Users</TableHead>
                            <TableHead class="text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="role in roles.data" :key="role.id">
                            <TableCell class="font-medium">{{
                                role.name
                            }}</TableCell>
                            <TableCell>{{ role.permissions_count }}</TableCell>
                            <TableCell>{{ role.users_count }}</TableCell>
                            <TableCell class="text-right space-x-1.5">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    as-child
                                    class="bg-amber-50 text-amber-600 hover:text-amber-800 hover:bg-amber-100 dark:bg-amber-950/30 dark:text-amber-400 dark:hover:text-amber-300 dark:hover:bg-amber-900/40"
                                    :aria-label="`Edit ${role.name}`"
                                    :data-test="`edit-role-${role.id}`"
                                >
                                    <Link :href="edit(role.id)">
                                        <Pencil class="h-4 w-4" />
                                    </Link>
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    class="bg-red-50 text-red-600 hover:text-red-800 hover:bg-red-100 dark:bg-red-950/30 dark:text-red-400 dark:hover:text-red-300 dark:hover:bg-red-900/40"
                                    :aria-label="`Delete ${role.name}`"
                                    :data-test="`delete-role-${role.id}`"
                                    @click="confirmDelete(role)"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </TableCell>
                        </TableRow>
                        <TableRow v-if="roles.data.length === 0">
                            <TableCell
                                :colspan="4"
                                class="text-center text-muted-foreground"
                            >
                                No roles yet.
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </CardContent>
        </Card>

        <TablePagination :links="roles.links" />
    </div>

    <ConfirmDeleteModal
        :open="deleteDialogOpen"
        title="Delete role"
        :description="`This will permanently delete the role “${roleToDelete?.name}”.`"
        :delete-url="roleToDelete ? destroy.url(roleToDelete.id) : null"
        @update:open="deleteDialogOpen = $event"
    />
</template>
