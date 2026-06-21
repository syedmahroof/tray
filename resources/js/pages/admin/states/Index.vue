<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ListTree, Pencil, Plus, Trash2 } from '@lucide/vue';
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
import { index as countriesIndex } from '@/routes/countries';
import { create, destroy, edit, index } from '@/routes/countries/states';
import { index as districtsIndex } from '@/routes/states/districts';
import type { Country, Filters, Paginated, State } from '@/types';

const props = defineProps<{
    country: Country;
    states: Paginated<State>;
    filters: Filters;
}>();

defineOptions({
    layout: (props: { country: Country }) => ({
        breadcrumbs: [
            { title: 'Countries', href: countriesIndex() },
            { title: props.country.name, href: index(props.country.id) },
        ],
    }),
});

const deleteDialogOpen = ref(false);
const stateToDelete = ref<State | null>(null);

const confirmDelete = (state: State) => {
    stateToDelete.value = state;
    deleteDialogOpen.value = true;
};
</script>

<template>
    <Head :title="`${country.name} states`" />

    <div class="flex flex-col space-y-6">
        <div class="flex items-center justify-between">
            <Heading
                variant="small"
                :title="`${country.name} states`"
                description="Manage the states belonging to this country"
            />

            <Button as-child>
                <Link :href="create(props.country.id)"><Plus /> New state</Link>
            </Button>
        </div>

        <Card>
            <CardContent>
                <div class="mb-4">
                    <SearchInput
                        :model-value="filters.search"
                        placeholder="Search states…"
                    />
                </div>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Name</TableHead>
                            <TableHead>Code</TableHead>
                            <TableHead class="text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="state in states.data" :key="state.id">
                            <TableCell class="font-medium">{{
                                state.name
                            }}</TableCell>
                            <TableCell>{{ state.code ?? '—' }}</TableCell>
                            <TableCell class="text-right">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    as-child
                                    :aria-label="`Manage districts for ${state.name}`"
                                    :data-test="`districts-${state.id}`"
                                >
                                    <Link :href="districtsIndex(state.id)">
                                        <ListTree class="h-4 w-4" />
                                    </Link>
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    as-child
                                    :aria-label="`Edit ${state.name}`"
                                    :data-test="`edit-state-${state.id}`"
                                >
                                    <Link
                                        :href="
                                            edit([props.country.id, state.id])
                                        "
                                    >
                                        <Pencil class="h-4 w-4" />
                                    </Link>
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    :aria-label="`Delete ${state.name}`"
                                    :data-test="`delete-state-${state.id}`"
                                    @click="confirmDelete(state)"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </TableCell>
                        </TableRow>
                        <TableRow v-if="states.data.length === 0">
                            <TableCell
                                :colspan="3"
                                class="text-center text-muted-foreground"
                            >
                                No states yet.
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </CardContent>
        </Card>

        <TablePagination :links="states.links" />
    </div>

    <ConfirmDeleteModal
        :open="deleteDialogOpen"
        title="Delete state"
        :description="`This will permanently delete “${stateToDelete?.name}”.`"
        :delete-url="
            stateToDelete
                ? destroy.url([props.country.id, stateToDelete.id])
                : null
        "
        @update:open="deleteDialogOpen = $event"
    />
</template>
