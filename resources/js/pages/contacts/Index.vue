<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Eye, Pencil, Plus, Trash2, Search } from '@lucide/vue';
import { ref } from 'vue';
import { watchDebounced } from '@vueuse/core';
import ConfirmDeleteModal from '@/components/ConfirmDeleteModal.vue';
import Heading from '@/components/Heading.vue';
import TablePagination from '@/components/TablePagination.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
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
import { create, destroy, edit, index, show } from '@/routes/contacts';
import type { ContactListItem, Filters, Paginated, NamedOption } from '@/types';

const props = defineProps<{
    contacts: Paginated<ContactListItem>;
    contactTypes: NamedOption[];
    users: NamedOption[];
    filters: Filters & {
        contact_type_id?: string | number;
        assigned_to?: string | number;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Contacts', href: index() }],
    },
});

const search = ref(props.filters.search ?? '');
const contactTypeId = ref(props.filters.contact_type_id ? String(props.filters.contact_type_id) : 'all');
const assignedTo = ref(props.filters.assigned_to ? String(props.filters.assigned_to) : 'all');

const updateFilters = () => {
    router.get(
        window.location.pathname,
        {
            search: search.value || undefined,
            contact_type_id: contactTypeId.value !== 'all' ? contactTypeId.value : undefined,
            assigned_to: assignedTo.value !== 'all' ? assignedTo.value : undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        }
    );
};

watchDebounced(search, () => updateFilters(), { debounce: 300 });

const deleteDialogOpen = ref(false);
const contactToDelete = ref<ContactListItem | null>(null);

const confirmDelete = (contact: ContactListItem) => {
    contactToDelete.value = contact;
    deleteDialogOpen.value = true;
};
</script>

<template>
    <Head title="Contacts" />

    <div class="flex flex-col space-y-6">
        <div class="flex items-center justify-between">
            <Heading
                variant="small"
                title="Contacts"
                description="Manage leads and customers"
            />

            <Button as-child>
                <Link :href="create()"><Plus /> New contact</Link>
            </Button>
        </div>

        <Card>
            <CardContent>
                <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:flex-wrap">
                    <div class="relative w-full max-w-sm">
                        <Search
                            class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                        />
                        <Input
                            v-model="search"
                            type="search"
                            placeholder="Search contacts…"
                            class="px-9"
                            data-test="search-input"
                        />
                    </div>

                    <!-- Contact Type Filter -->
                    <Select v-model="contactTypeId" @update:model-value="updateFilters">
                        <SelectTrigger class="w-full sm:w-[180px]">
                            <SelectValue placeholder="Filter by Type" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Types</SelectItem>
                            <SelectItem
                                v-for="typeItem in contactTypes"
                                :key="typeItem.id"
                                :value="String(typeItem.id)"
                            >
                                {{ typeItem.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>

                    <!-- Assigned To Filter -->
                    <Select v-model="assignedTo" @update:model-value="updateFilters">
                        <SelectTrigger class="w-full sm:w-[180px]">
                            <SelectValue placeholder="Filter by Assignee" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Assignees</SelectItem>
                            <SelectItem
                                v-for="user in users"
                                :key="user.id"
                                :value="String(user.id)"
                            >
                                {{ user.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Name</TableHead>
                            <TableHead>Type</TableHead>
                            <TableHead>Contact</TableHead>
                            <TableHead>Assigned to</TableHead>
                            <TableHead class="text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="contact in contacts.data"
                            :key="contact.id"
                        >
                            <TableCell class="font-medium">
                                <Link
                                    :href="show(contact.id)"
                                    class="hover:underline"
                                >
                                    {{ contact.name }}
                                </Link>
                            </TableCell>
                            <TableCell>{{
                                contact.contact_type.name
                            }}</TableCell>
                            <TableCell>
                                <div>{{ contact.phone ?? '—' }}</div>
                                <div class="text-sm text-muted-foreground">
                                    {{ contact.email ?? '' }}
                                </div>
                            </TableCell>
                            <TableCell>{{
                                contact.assignee?.name ?? '—'
                            }}</TableCell>
                            <TableCell class="text-right">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    as-child
                                    :aria-label="`View ${contact.name}`"
                                    :data-test="`view-contact-${contact.id}`"
                                >
                                    <Link :href="show(contact.id)">
                                        <Eye class="h-4 w-4" />
                                    </Link>
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    as-child
                                    :aria-label="`Edit ${contact.name}`"
                                    :data-test="`edit-contact-${contact.id}`"
                                >
                                    <Link :href="edit(contact.id)">
                                        <Pencil class="h-4 w-4" />
                                    </Link>
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    :aria-label="`Delete ${contact.name}`"
                                    :data-test="`delete-contact-${contact.id}`"
                                    @click="confirmDelete(contact)"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </TableCell>
                        </TableRow>
                        <TableRow v-if="contacts.data.length === 0">
                            <TableCell
                                :colspan="5"
                                class="text-center text-muted-foreground"
                            >
                                No contacts yet.
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </CardContent>
        </Card>

        <TablePagination :links="contacts.links" />
    </div>

    <ConfirmDeleteModal
        :open="deleteDialogOpen"
        title="Delete contact"
        :description="`This will permanently delete “${contactToDelete?.name}”.`"
        :delete-url="contactToDelete ? destroy.url(contactToDelete.id) : null"
        @update:open="deleteDialogOpen = $event"
    />
</template>
