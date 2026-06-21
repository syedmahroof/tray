<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import ConfirmDeleteModal from '@/components/ConfirmDeleteModal.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import SearchInput from '@/components/SearchInput.vue';
import TablePagination from '@/components/TablePagination.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { CategoryItem, Paginated } from '@/types';

defineProps<{
    title: string;
    description: string;
    newButtonLabel: string;
    items: Paginated<CategoryItem>;
    searchValue: string;
    storeUrl: string;
    updateUrl: (id: number) => string;
    destroyUrl: (id: number) => string;
}>();

const createDialogOpen = ref(false);
const editDialogOpen = ref(false);
const deleteDialogOpen = ref(false);
const itemBeingEdited = ref<CategoryItem | null>(null);
const itemBeingDeleted = ref<CategoryItem | null>(null);

const openEdit = (item: CategoryItem) => {
    itemBeingEdited.value = item;
    editDialogOpen.value = true;
};

const openDelete = (item: CategoryItem) => {
    itemBeingDeleted.value = item;
    deleteDialogOpen.value = true;
};
</script>

<template>
    <Head :title="title" />

    <div class="flex flex-col space-y-6">
        <div class="flex items-center justify-between">
            <Heading
                variant="small"
                :title="title"
                :description="description"
            />

            <Button @click="createDialogOpen = true">
                <Plus /> {{ newButtonLabel }}
            </Button>
        </div>

        <Card>
            <CardContent>
                <div class="mb-4">
                    <SearchInput
                        :model-value="searchValue"
                        :placeholder="`Search ${title.toLowerCase()}…`"
                    />
                </div>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Name</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead class="text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="item in items.data" :key="item.id">
                            <TableCell class="font-medium">{{
                                item.name
                            }}</TableCell>
                            <TableCell>
                                <Badge
                                    :variant="
                                        item.is_active ? 'default' : 'secondary'
                                    "
                                >
                                    {{ item.is_active ? 'Active' : 'Inactive' }}
                                </Badge>
                            </TableCell>
                            <TableCell class="text-right">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    :aria-label="`Edit ${item.name}`"
                                    :data-test="`edit-${item.id}`"
                                    @click="openEdit(item)"
                                >
                                    <Pencil class="h-4 w-4" />
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    :aria-label="`Delete ${item.name}`"
                                    :data-test="`delete-${item.id}`"
                                    @click="openDelete(item)"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </TableCell>
                        </TableRow>
                        <TableRow v-if="items.data.length === 0">
                            <TableCell
                                :colspan="3"
                                class="text-center text-muted-foreground"
                            >
                                Nothing here yet.
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </CardContent>
        </Card>

        <TablePagination :links="items.links" />
    </div>

    <Dialog :open="createDialogOpen" @update:open="createDialogOpen = $event">
        <DialogContent>
            <Form
                :action="storeUrl"
                method="post"
                class="space-y-6"
                v-slot="{ errors, processing }"
                @success="createDialogOpen = false"
            >
                <DialogHeader>
                    <DialogTitle>{{ newButtonLabel }}</DialogTitle>
                </DialogHeader>

                <div class="grid gap-2">
                    <Label for="create-name">Name</Label>
                    <Input id="create-name" name="name" required autofocus />
                    <InputError :message="errors.name" />
                </div>

                <Label
                    for="create-is-active"
                    class="flex items-center space-x-3"
                >
                    <Checkbox
                        id="create-is-active"
                        name="is_active"
                        :default-value="true"
                    />
                    <span>Active</span>
                </Label>

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button variant="secondary">Cancel</Button>
                    </DialogClose>
                    <Button type="submit" :disabled="processing">Create</Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>

    <Dialog :open="editDialogOpen" @update:open="editDialogOpen = $event">
        <DialogContent v-if="itemBeingEdited">
            <Form
                :key="itemBeingEdited.id"
                :action="updateUrl(itemBeingEdited.id)"
                method="put"
                class="space-y-6"
                v-slot="{ errors, processing }"
                @success="editDialogOpen = false"
            >
                <DialogHeader>
                    <DialogTitle>Edit {{ itemBeingEdited.name }}</DialogTitle>
                </DialogHeader>

                <div class="grid gap-2">
                    <Label for="edit-name">Name</Label>
                    <Input
                        id="edit-name"
                        name="name"
                        :default-value="itemBeingEdited.name"
                        required
                        autofocus
                    />
                    <InputError :message="errors.name" />
                </div>

                <Label for="edit-is-active" class="flex items-center space-x-3">
                    <Checkbox
                        id="edit-is-active"
                        name="is_active"
                        :default-value="itemBeingEdited.is_active"
                    />
                    <span>Active</span>
                </Label>

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button variant="secondary">Cancel</Button>
                    </DialogClose>
                    <Button type="submit" :disabled="processing">Save</Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>

    <ConfirmDeleteModal
        :open="deleteDialogOpen"
        title="Delete"
        :description="`This will permanently delete “${itemBeingDeleted?.name}”.`"
        :delete-url="itemBeingDeleted ? destroyUrl(itemBeingDeleted.id) : null"
        @update:open="deleteDialogOpen = $event"
    />
</template>
