<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
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
import { Textarea } from '@/components/ui/textarea';
import { destroy, index, store, update } from '@/routes/suppliers';
import type { Filters, Paginated, Supplier } from '@/types';

defineProps<{
    suppliers: Paginated<Supplier>;
    filters: Filters;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Suppliers', href: index() }],
    },
});

const permissions = computed(() => usePage().props.auth.permissions);
const canCreate = computed(() =>
    permissions.value.includes('suppliers.create'),
);
const canUpdate = computed(() =>
    permissions.value.includes('suppliers.update'),
);
const canDelete = computed(() =>
    permissions.value.includes('suppliers.delete'),
);

/** The supplier open in the dialog: null while closed, `'new'` when creating. */
const supplierInDialog = ref<Supplier | 'new' | null>(null);
const supplierBeingDeleted = ref<Supplier | null>(null);
const editing = computed(() =>
    supplierInDialog.value === 'new' ? null : supplierInDialog.value,
);
const dialogAction = computed(() =>
    editing.value ? update.url(editing.value.id) : store.url(),
);
</script>

<template>
    <Head title="Suppliers" />

    <div class="flex flex-col space-y-6">
        <div class="flex items-center justify-between gap-4">
            <Heading
                variant="small"
                title="Suppliers"
                description="Companies you raise purchase orders with, shared across branches"
            />

            <Button v-if="canCreate" @click="supplierInDialog = 'new'">
                <Plus /> New supplier
            </Button>
        </div>

        <Card>
            <CardContent>
                <div class="mb-4">
                    <SearchInput
                        :model-value="filters.search"
                        placeholder="Search name, contact, phone, email or GSTIN…"
                    />
                </div>
                <div class="overflow-x-auto">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Name</TableHead>
                                <TableHead>Contact</TableHead>
                                <TableHead>GSTIN</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead class="text-right">
                                    Purchase orders
                                </TableHead>
                                <TableHead
                                    v-if="canUpdate || canDelete"
                                    class="text-right"
                                >
                                    Actions
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow
                                v-for="supplier in suppliers.data"
                                :key="supplier.id"
                            >
                                <TableCell class="font-medium">
                                    {{ supplier.name }}
                                </TableCell>
                                <TableCell class="text-sm">
                                    <div>
                                        {{ supplier.contact_person ?? '—' }}
                                    </div>
                                    <div
                                        v-if="supplier.phone || supplier.email"
                                        class="text-xs text-muted-foreground"
                                    >
                                        {{
                                            [supplier.phone, supplier.email]
                                                .filter(Boolean)
                                                .join(' · ')
                                        }}
                                    </div>
                                </TableCell>
                                <TableCell class="font-mono text-xs">
                                    {{ supplier.gst_number ?? '—' }}
                                </TableCell>
                                <TableCell>
                                    <Badge
                                        :variant="
                                            supplier.is_active
                                                ? 'default'
                                                : 'secondary'
                                        "
                                    >
                                        {{
                                            supplier.is_active
                                                ? 'Active'
                                                : 'Inactive'
                                        }}
                                    </Badge>
                                </TableCell>
                                <TableCell class="text-right tabular-nums">
                                    {{ supplier.purchase_orders_count ?? 0 }}
                                </TableCell>
                                <TableCell
                                    v-if="canUpdate || canDelete"
                                    class="space-x-1.5 text-right whitespace-nowrap"
                                >
                                    <Button
                                        v-if="canUpdate"
                                        variant="ghost"
                                        size="sm"
                                        class="bg-amber-50 text-amber-600 hover:bg-amber-100 hover:text-amber-800 dark:bg-amber-950/30 dark:text-amber-400 dark:hover:bg-amber-900/40 dark:hover:text-amber-300"
                                        :aria-label="`Edit ${supplier.name}`"
                                        @click="supplierInDialog = supplier"
                                    >
                                        <Pencil class="h-4 w-4" />
                                    </Button>
                                    <Button
                                        v-if="canDelete"
                                        variant="ghost"
                                        size="sm"
                                        class="bg-red-50 text-red-600 hover:bg-red-100 hover:text-red-800 dark:bg-red-950/30 dark:text-red-400 dark:hover:bg-red-900/40 dark:hover:text-red-300"
                                        :aria-label="`Delete ${supplier.name}`"
                                        @click="supplierBeingDeleted = supplier"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </Button>
                                </TableCell>
                            </TableRow>
                            <TableRow v-if="suppliers.data.length === 0">
                                <TableCell
                                    :colspan="6"
                                    class="py-10 text-center text-muted-foreground"
                                >
                                    No suppliers yet.
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>
            </CardContent>
        </Card>

        <TablePagination :links="suppliers.links" />
    </div>

    <Dialog
        :open="supplierInDialog !== null"
        @update:open="(open) => !open && (supplierInDialog = null)"
    >
        <DialogContent v-if="supplierInDialog !== null" class="sm:max-w-lg">
            <Form
                :key="editing?.id ?? 'new'"
                :action="dialogAction"
                :method="editing ? 'put' : 'post'"
                class="space-y-5"
                v-slot="{ errors, processing }"
                @success="supplierInDialog = null"
            >
                <DialogHeader>
                    <DialogTitle>
                        {{ editing ? `Edit ${editing.name}` : 'New supplier' }}
                    </DialogTitle>
                </DialogHeader>

                <div class="grid gap-2">
                    <Label for="supplier-name">Name *</Label>
                    <Input
                        id="supplier-name"
                        name="name"
                        :default-value="editing?.name"
                        required
                        autofocus
                    />
                    <InputError :message="errors.name" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="supplier-contact">Contact person</Label>
                        <Input
                            id="supplier-contact"
                            name="contact_person"
                            :default-value="editing?.contact_person ?? ''"
                        />
                        <InputError :message="errors.contact_person" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="supplier-phone">Phone</Label>
                        <Input
                            id="supplier-phone"
                            name="phone"
                            type="tel"
                            :default-value="editing?.phone ?? ''"
                        />
                        <InputError :message="errors.phone" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="supplier-email">Email</Label>
                        <Input
                            id="supplier-email"
                            name="email"
                            type="email"
                            :default-value="editing?.email ?? ''"
                        />
                        <InputError :message="errors.email" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="supplier-gst">GSTIN</Label>
                        <Input
                            id="supplier-gst"
                            name="gst_number"
                            placeholder="e.g. 29ABCDE1234F1Z5"
                            :default-value="editing?.gst_number ?? ''"
                        />
                        <InputError :message="errors.gst_number" />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="supplier-address">Address</Label>
                    <Textarea
                        id="supplier-address"
                        name="address"
                        rows="3"
                        :default-value="editing?.address ?? ''"
                    />
                    <InputError :message="errors.address" />
                </div>

                <Label
                    for="supplier-active"
                    class="flex items-center space-x-3"
                >
                    <Checkbox
                        id="supplier-active"
                        name="is_active"
                        :default-value="editing?.is_active ?? true"
                    />
                    <span>Active</span>
                </Label>

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button variant="secondary">Cancel</Button>
                    </DialogClose>
                    <Button type="submit" :disabled="processing">
                        {{ editing ? 'Save' : 'Create' }}
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>

    <ConfirmDeleteModal
        :open="supplierBeingDeleted !== null"
        title="Delete supplier"
        :description="`This will permanently delete “${supplierBeingDeleted?.name}”.`"
        :delete-url="
            supplierBeingDeleted ? destroy.url(supplierBeingDeleted.id) : null
        "
        @update:open="(open) => !open && (supplierBeingDeleted = null)"
    />
</template>
