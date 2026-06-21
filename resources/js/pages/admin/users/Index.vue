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
import { create, destroy, edit, index } from '@/routes/users';
import type { Filters, Paginated, UserListItem } from '@/types';

defineProps<{
    users: Paginated<UserListItem>;
    filters: Filters;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Users', href: index() }],
    },
});

const deleteDialogOpen = ref(false);
const userToDelete = ref<UserListItem | null>(null);

const confirmDelete = (user: UserListItem) => {
    userToDelete.value = user;
    deleteDialogOpen.value = true;
};
</script>

<template>
    <Head title="Users" />

    <div class="flex flex-col space-y-6">
        <div class="flex items-center justify-between">
            <Heading
                variant="small"
                title="Users"
                description="Manage user accounts, roles, and branch assignments"
            />

            <Button as-child>
                <Link :href="create()"><Plus /> New user</Link>
            </Button>
        </div>

        <Card>
            <CardContent>
                <div class="mb-4">
                    <SearchInput
                        :model-value="filters.search"
                        placeholder="Search users…"
                    />
                </div>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Name</TableHead>
                            <TableHead>Email</TableHead>
                            <TableHead>Branch</TableHead>
                            <TableHead>Role</TableHead>
                            <TableHead class="text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="user in users.data" :key="user.id">
                            <TableCell class="font-medium">{{
                                user.name
                            }}</TableCell>
                            <TableCell>{{ user.email }}</TableCell>
                            <TableCell>{{
                                user.branch?.name ?? '—'
                            }}</TableCell>
                            <TableCell>
                                <Badge
                                    v-for="role in user.roles"
                                    :key="role.id"
                                    variant="secondary"
                                >
                                    {{ role.name }}
                                </Badge>
                            </TableCell>
                            <TableCell class="text-right space-x-1.5">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    as-child
                                    class="bg-amber-50 text-amber-600 hover:text-amber-800 hover:bg-amber-100 dark:bg-amber-950/30 dark:text-amber-400 dark:hover:text-amber-300 dark:hover:bg-amber-900/40"
                                    :aria-label="`Edit ${user.name}`"
                                    :data-test="`edit-user-${user.id}`"
                                >
                                    <Link :href="edit(user.id)">
                                        <Pencil class="h-4 w-4" />
                                    </Link>
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    class="bg-red-50 text-red-600 hover:text-red-800 hover:bg-red-100 dark:bg-red-950/30 dark:text-red-400 dark:hover:text-red-300 dark:hover:bg-red-900/40"
                                    :aria-label="`Delete ${user.name}`"
                                    :data-test="`delete-user-${user.id}`"
                                    @click="confirmDelete(user)"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </TableCell>
                        </TableRow>
                        <TableRow v-if="users.data.length === 0">
                            <TableCell
                                :colspan="5"
                                class="text-center text-muted-foreground"
                            >
                                No users yet.
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </CardContent>
        </Card>

        <TablePagination :links="users.links" />
    </div>

    <ConfirmDeleteModal
        :open="deleteDialogOpen"
        title="Delete user"
        :description="`This will permanently delete “${userToDelete?.name}”.`"
        :delete-url="userToDelete ? destroy.url(userToDelete.id) : null"
        @update:open="deleteDialogOpen = $event"
    />
</template>
