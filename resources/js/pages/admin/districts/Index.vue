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
import { index as countriesIndex } from '@/routes/countries';
import { index as statesIndex } from '@/routes/countries/states';
import { create, destroy, edit, index } from '@/routes/states/districts';
import type { District, Filters, Paginated, StateWithCountry } from '@/types';

const props = defineProps<{
    state: StateWithCountry;
    districts: Paginated<District>;
    filters: Filters;
}>();

defineOptions({
    layout: (props: { state: StateWithCountry }) => ({
        breadcrumbs: [
            { title: 'Countries', href: countriesIndex() },
            {
                title: props.state.country.name,
                href: statesIndex(props.state.country.id),
            },
            { title: props.state.name, href: index(props.state.id) },
        ],
    }),
});

const deleteDialogOpen = ref(false);
const districtToDelete = ref<District | null>(null);

const confirmDelete = (district: District) => {
    districtToDelete.value = district;
    deleteDialogOpen.value = true;
};
</script>

<template>
    <Head :title="`${state.name} districts`" />

    <div class="flex flex-col space-y-6">
        <div class="flex items-center justify-between">
            <Heading
                variant="small"
                :title="`${state.name} districts`"
                description="Manage the districts belonging to this state"
            />

            <Button as-child>
                <Link :href="create(props.state.id)">
                    <Plus /> New district
                </Link>
            </Button>
        </div>

        <Card>
            <CardContent>
                <div class="mb-4">
                    <SearchInput
                        :model-value="filters.search"
                        placeholder="Search districts…"
                    />
                </div>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Name</TableHead>
                            <TableHead class="text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="district in districts.data"
                            :key="district.id"
                        >
                            <TableCell class="font-medium">{{
                                district.name
                            }}</TableCell>
                            <TableCell class="text-right space-x-1.5">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    as-child
                                    class="bg-amber-50 text-amber-600 hover:text-amber-800 hover:bg-amber-100 dark:bg-amber-950/30 dark:text-amber-400 dark:hover:text-amber-300 dark:hover:bg-amber-900/40"
                                    :aria-label="`Edit ${district.name}`"
                                    :data-test="`edit-district-${district.id}`"
                                >
                                    <Link
                                        :href="
                                            edit([props.state.id, district.id])
                                        "
                                    >
                                        <Pencil class="h-4 w-4" />
                                    </Link>
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    class="bg-red-50 text-red-600 hover:text-red-800 hover:bg-red-100 dark:bg-red-950/30 dark:text-red-400 dark:hover:text-red-300 dark:hover:bg-red-900/40"
                                    :aria-label="`Delete ${district.name}`"
                                    :data-test="`delete-district-${district.id}`"
                                    @click="confirmDelete(district)"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </TableCell>
                        </TableRow>
                        <TableRow v-if="districts.data.length === 0">
                            <TableCell
                                :colspan="2"
                                class="text-center text-muted-foreground"
                            >
                                No districts yet.
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </CardContent>
        </Card>

        <TablePagination :links="districts.links" />
    </div>

    <ConfirmDeleteModal
        :open="deleteDialogOpen"
        title="Delete district"
        :description="`This will permanently delete “${districtToDelete?.name}”.`"
        :delete-url="
            districtToDelete
                ? destroy.url([props.state.id, districtToDelete.id])
                : null
        "
        @update:open="deleteDialogOpen = $event"
    />
</template>
