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
import { create, destroy, edit, index } from '@/routes/countries';
import { index as statesIndex } from '@/routes/countries/states';
import type { Country, Filters, Paginated } from '@/types';

defineProps<{
    countries: Paginated<Country>;
    filters: Filters;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Countries', href: index() }],
    },
});

const deleteDialogOpen = ref(false);
const countryToDelete = ref<Country | null>(null);

const confirmDelete = (country: Country) => {
    countryToDelete.value = country;
    deleteDialogOpen.value = true;
};
</script>

<template>
    <Head title="Countries" />

    <div class="flex flex-col space-y-6">
        <div class="flex items-center justify-between">
            <Heading
                variant="small"
                title="Countries"
                description="Manage the countries available for addresses"
            />

            <Button as-child>
                <Link :href="create()"><Plus /> New country</Link>
            </Button>
        </div>

        <Card>
            <CardContent>
                <div class="mb-4">
                    <SearchInput
                        :model-value="filters.search"
                        placeholder="Search countries…"
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
                        <TableRow
                            v-for="country in countries.data"
                            :key="country.id"
                        >
                            <TableCell class="font-medium">{{
                                country.name
                            }}</TableCell>
                            <TableCell>{{ country.code ?? '—' }}</TableCell>
                            <TableCell class="text-right">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    as-child
                                    :aria-label="`Manage states for ${country.name}`"
                                    :data-test="`states-${country.id}`"
                                >
                                    <Link :href="statesIndex(country.id)">
                                        <ListTree class="h-4 w-4" />
                                    </Link>
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    as-child
                                    :aria-label="`Edit ${country.name}`"
                                    :data-test="`edit-country-${country.id}`"
                                >
                                    <Link :href="edit(country.id)">
                                        <Pencil class="h-4 w-4" />
                                    </Link>
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    :aria-label="`Delete ${country.name}`"
                                    :data-test="`delete-country-${country.id}`"
                                    @click="confirmDelete(country)"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </TableCell>
                        </TableRow>
                        <TableRow v-if="countries.data.length === 0">
                            <TableCell
                                :colspan="3"
                                class="text-center text-muted-foreground"
                            >
                                No countries yet.
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </CardContent>
        </Card>

        <TablePagination :links="countries.links" />
    </div>

    <ConfirmDeleteModal
        :open="deleteDialogOpen"
        title="Delete country"
        :description="`This will permanently delete “${countryToDelete?.name}”.`"
        :delete-url="countryToDelete ? destroy.url(countryToDelete.id) : null"
        @update:open="deleteDialogOpen = $event"
    />
</template>
