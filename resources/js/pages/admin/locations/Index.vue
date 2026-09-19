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
import { index as countriesIndex } from '@/routes/countries';
import { index as statesIndex } from '@/routes/countries/states';
import { create, destroy, edit, index } from '@/routes/districts/locations';
import { index as districtsIndex } from '@/routes/states/districts';
import type { DistrictWithState, Filters, Location, Paginated } from '@/types';

const props = defineProps<{
    district: DistrictWithState;
    locations: Paginated<Location>;
    filters: Filters;
}>();

defineOptions({
    layout: (props: { district: DistrictWithState }) => ({
        breadcrumbs: [
            { title: 'Countries', href: countriesIndex() },
            {
                title: props.district.state.country.name,
                href: statesIndex(props.district.state.country.id),
            },
            {
                title: props.district.state.name,
                href: districtsIndex(props.district.state.id),
            },
            { title: props.district.name, href: index(props.district.id) },
        ],
    }),
});

const deleteDialogOpen = ref(false);
const locationToDelete = ref<Location | null>(null);

const confirmDelete = (location: Location) => {
    locationToDelete.value = location;
    deleteDialogOpen.value = true;
};
</script>

<template>
    <Head :title="`${district.name} locations`" />

    <div class="flex flex-col space-y-6">
        <div class="flex items-center justify-between">
            <Heading
                variant="small"
                :title="`${district.name} locations`"
                description="Manage the locations belonging to this district"
            />

            <Button as-child>
                <Link :href="create(props.district.id)">
                    <Plus /> New location
                </Link>
            </Button>
        </div>

        <Card>
            <CardContent>
                <div class="mb-4">
                    <SearchInput
                        :model-value="filters.search"
                        placeholder="Search locations…"
                    />
                </div>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Name</TableHead>
                            <TableHead>Pincode</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead class="text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="location in locations.data"
                            :key="location.id"
                        >
                            <TableCell class="font-medium">{{
                                location.name
                            }}</TableCell>
                            <TableCell class="text-muted-foreground">{{
                                location.pincode ?? '—'
                            }}</TableCell>
                            <TableCell>
                                <Badge
                                    :variant="
                                        location.is_active
                                            ? 'default'
                                            : 'secondary'
                                    "
                                >
                                    {{
                                        location.is_active
                                            ? 'Active'
                                            : 'Inactive'
                                    }}
                                </Badge>
                            </TableCell>
                            <TableCell class="space-x-1.5 text-right">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    as-child
                                    class="bg-amber-50 text-amber-600 hover:bg-amber-100 hover:text-amber-800 dark:bg-amber-950/30 dark:text-amber-400 dark:hover:bg-amber-900/40 dark:hover:text-amber-300"
                                    :aria-label="`Edit ${location.name}`"
                                    :data-test="`edit-location-${location.id}`"
                                >
                                    <Link
                                        :href="
                                            edit([
                                                props.district.id,
                                                location.id,
                                            ])
                                        "
                                    >
                                        <Pencil class="h-4 w-4" />
                                    </Link>
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    class="bg-red-50 text-red-600 hover:bg-red-100 hover:text-red-800 dark:bg-red-950/30 dark:text-red-400 dark:hover:bg-red-900/40 dark:hover:text-red-300"
                                    :aria-label="`Delete ${location.name}`"
                                    :data-test="`delete-location-${location.id}`"
                                    @click="confirmDelete(location)"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </TableCell>
                        </TableRow>
                        <TableRow v-if="locations.data.length === 0">
                            <TableCell
                                :colspan="4"
                                class="text-center text-muted-foreground"
                            >
                                No locations yet.
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </CardContent>
        </Card>

        <TablePagination :links="locations.links" />
    </div>

    <ConfirmDeleteModal
        :open="deleteDialogOpen"
        title="Delete location"
        :description="`This will permanently delete “${locationToDelete?.name}”.`"
        :delete-url="
            locationToDelete
                ? destroy.url([props.district.id, locationToDelete.id])
                : null
        "
        @update:open="deleteDialogOpen = $event"
    />
</template>
