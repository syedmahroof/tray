<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { Eye, Pencil, Plus, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import ConfirmDeleteModal from '@/components/ConfirmDeleteModal.vue';
import Heading from '@/components/Heading.vue';
import ImageInput from '@/components/ImageInput.vue';
import InputError from '@/components/InputError.vue';
import SearchInput from '@/components/SearchInput.vue';
import TablePagination from '@/components/TablePagination.vue';
import Thumbnail from '@/components/Thumbnail.vue';
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

const props = defineProps<{
    title: string;
    description: string;
    newButtonLabel: string;
    items: Paginated<CategoryItem>;
    searchValue: string;
    storeUrl: string;
    updateUrl: (id: number) => string;
    destroyUrl: (id: number) => string;
    /** The upload field each item carries a picture in, if any. */
    imageField?: 'image' | 'logo';
    imageLabel?: string;
    /** Where an item's own page lives, when it has one. */
    showUrl?: (id: number) => string;
    /** Show an extra count column (e.g. products_count). */
    countField?: string;
    countLabel?: string;
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

const pictureOf = (item: CategoryItem): string | null =>
    props.imageField === 'logo'
        ? (item.logo_url ?? null)
        : (item.image_url ?? null);
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
                            <TableHead v-if="countLabel" class="text-right">{{ countLabel }}</TableHead>
                            <TableHead class="text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="item in items.data" :key="item.id">
                            <TableCell class="font-medium">
                                <div class="flex items-center gap-3">
                                    <Thumbnail
                                        v-if="imageField"
                                        :src="pictureOf(item)"
                                        :name="item.name"
                                    />
                                    <Link
                                        v-if="showUrl"
                                        :href="showUrl(item.id)"
                                        class="hover:underline"
                                    >
                                        {{ item.name }}
                                    </Link>
                                    <span v-else>{{ item.name }}</span>
                                </div>
                            </TableCell>
                            <TableCell>
                                <Badge
                                    :variant="
                                        item.is_active ? 'default' : 'secondary'
                                    "
                                >
                                    {{ item.is_active ? 'Active' : 'Inactive' }}
                                </Badge>
                            </TableCell>
                            <TableCell v-if="countLabel" class="text-right">
                                {{ countField ? (item as Record<string, unknown>)[countField] ?? 0 : 0 }}
                            </TableCell>
                            <TableCell class="space-x-1.5 text-right">
                                <Button
                                    v-if="showUrl"
                                    variant="ghost"
                                    size="sm"
                                    class="bg-sky-50 text-sky-600 hover:bg-sky-100 hover:text-sky-800 dark:bg-sky-950/30 dark:text-sky-400 dark:hover:bg-sky-900/40 dark:hover:text-sky-300"
                                    :aria-label="`View ${item.name}`"
                                    as-child
                                >
                                    <Link :href="showUrl(item.id)">
                                        <Eye class="h-4 w-4" />
                                    </Link>
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    class="bg-amber-50 text-amber-600 hover:bg-amber-100 hover:text-amber-800 dark:bg-amber-950/30 dark:text-amber-400 dark:hover:bg-amber-900/40 dark:hover:text-amber-300"
                                    :aria-label="`Edit ${item.name}`"
                                    :data-test="`edit-${item.id}`"
                                    @click="openEdit(item)"
                                >
                                    <Pencil class="h-4 w-4" />
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    class="bg-red-50 text-red-600 hover:bg-red-100 hover:text-red-800 dark:bg-red-950/30 dark:text-red-400 dark:hover:bg-red-900/40 dark:hover:text-red-300"
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
                                :colspan="countLabel ? 4 : 3"
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

                <ImageInput
                    v-if="imageField"
                    id="create-image"
                    :name="imageField"
                    :label="imageLabel ?? 'Image'"
                    :error="errors[imageField]"
                />

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
            <!--
                Posted with a spoofed PUT: PHP only reads multipart bodies on
                POST, so a real PUT would drop an uploaded image.
            -->
            <Form
                :key="itemBeingEdited.id"
                :action="updateUrl(itemBeingEdited.id)"
                method="post"
                class="space-y-6"
                v-slot="{ errors, processing }"
                @success="editDialogOpen = false"
            >
                <input type="hidden" name="_method" value="put" />

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

                <ImageInput
                    v-if="imageField"
                    id="edit-image"
                    :name="imageField"
                    :label="imageLabel ?? 'Image'"
                    :current-url="pictureOf(itemBeingEdited)"
                    :error="errors[imageField]"
                />

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
