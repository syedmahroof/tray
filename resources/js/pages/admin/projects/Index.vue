<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Eye, Pencil, Plus, Trash2, Search } from '@lucide/vue';
import { ref } from 'vue';
import { watchDebounced } from '@vueuse/core';
import ConfirmDeleteModal from '@/components/ConfirmDeleteModal.vue';
import Heading from '@/components/Heading.vue';
import TablePagination from '@/components/TablePagination.vue';
import { Badge } from '@/components/ui/badge';
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
import { create, destroy, edit, index, show } from '@/routes/projects';
import type { Filters, Paginated, ProjectListItem, NamedOption } from '@/types';

const props = defineProps<{
    projects: Paginated<ProjectListItem>;
    builders: NamedOption[];
    projectCategories: NamedOption[];
    statuses: string[];
    filters: Filters & {
        builder_id?: string | number;
        project_category_id?: string | number;
        status?: string;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Projects', href: index() }],
    },
});

const search = ref(props.filters.search ?? '');
const builderId = ref(
    props.filters.builder_id ? String(props.filters.builder_id) : 'all',
);
const categoryId = ref(
    props.filters.project_category_id
        ? String(props.filters.project_category_id)
        : 'all',
);
const status = ref(props.filters.status ?? 'all');

const updateFilters = () => {
    router.get(
        window.location.pathname,
        {
            search: search.value || undefined,
            builder_id: builderId.value !== 'all' ? builderId.value : undefined,
            project_category_id:
                categoryId.value !== 'all' ? categoryId.value : undefined,
            status: status.value !== 'all' ? status.value : undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
};

watchDebounced(search, () => updateFilters(), { debounce: 300 });

const statusVariant = (status: string) => {
    if (status === 'completed') {
        return 'default' as const;
    }

    if (status === 'ongoing') {
        return 'outline' as const;
    }

    return 'secondary' as const;
};

const deleteDialogOpen = ref(false);
const projectToDelete = ref<ProjectListItem | null>(null);

const confirmDelete = (project: ProjectListItem) => {
    projectToDelete.value = project;
    deleteDialogOpen.value = true;
};
</script>

<template>
    <Head title="Projects" />

    <div class="flex flex-col space-y-6">
        <div class="flex items-center justify-between">
            <Heading
                variant="small"
                title="Projects"
                description="Manage real-estate projects across builders"
            />

            <Button as-child>
                <Link :href="create()"><Plus /> New project</Link>
            </Button>
        </div>

        <Card>
            <CardContent>
                <div
                    class="mb-6 flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-center"
                >
                    <div class="relative w-full max-w-sm">
                        <Search
                            class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                        />
                        <Input
                            v-model="search"
                            type="search"
                            placeholder="Search projects…"
                            class="px-9"
                            data-test="search-input"
                        />
                    </div>

                    <!-- Builder Filter -->
                    <Select
                        v-model="builderId"
                        @update:model-value="updateFilters"
                    >
                        <SelectTrigger class="w-full sm:w-[180px]">
                            <SelectValue placeholder="Filter by Builder" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Builders</SelectItem>
                            <SelectItem
                                v-for="builder in builders"
                                :key="builder.id"
                                :value="String(builder.id)"
                            >
                                {{ builder.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>

                    <!-- Category Filter -->
                    <Select
                        v-model="categoryId"
                        @update:model-value="updateFilters"
                    >
                        <SelectTrigger class="w-full sm:w-[180px]">
                            <SelectValue placeholder="Filter by Category" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Categories</SelectItem>
                            <SelectItem
                                v-for="cat in projectCategories"
                                :key="cat.id"
                                :value="String(cat.id)"
                            >
                                {{ cat.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>

                    <!-- Status Filter -->
                    <Select
                        v-model="status"
                        @update:model-value="updateFilters"
                    >
                        <SelectTrigger class="w-full sm:w-[150px]">
                            <SelectValue placeholder="Filter by Status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Statuses</SelectItem>
                            <SelectItem
                                v-for="stat in statuses"
                                :key="stat"
                                :value="stat"
                                class="capitalize"
                            >
                                {{ stat }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Name</TableHead>
                            <TableHead>Builder</TableHead>
                            <TableHead>Category</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead class="text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="project in projects.data"
                            :key="project.id"
                        >
                            <TableCell class="font-medium">
                                <Link
                                    :href="show(project.id)"
                                    class="font-semibold text-foreground hover:underline"
                                >
                                    {{ project.name }}
                                </Link>
                            </TableCell>
                            <TableCell>{{ project.builder?.name }}</TableCell>
                            <TableCell>{{
                                project.project_category?.name
                            }}</TableCell>
                            <TableCell>
                                <Badge
                                    :variant="statusVariant(project.status)"
                                    class="capitalize"
                                >
                                    {{ project.status }}
                                </Badge>
                            </TableCell>
                            <TableCell class="text-right">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    as-child
                                    :aria-label="`View ${project.name}`"
                                    :data-test="`view-project-${project.id}`"
                                >
                                    <Link :href="show(project.id)">
                                        <Eye class="h-4 w-4" />
                                    </Link>
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    as-child
                                    :aria-label="`Edit ${project.name}`"
                                    :data-test="`edit-project-${project.id}`"
                                >
                                    <Link :href="edit(project.id)">
                                        <Pencil class="h-4 w-4" />
                                    </Link>
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    :aria-label="`Delete ${project.name}`"
                                    :data-test="`delete-project-${project.id}`"
                                    @click="confirmDelete(project)"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </TableCell>
                        </TableRow>
                        <TableRow v-if="projects.data.length === 0">
                            <TableCell
                                :colspan="5"
                                class="text-center text-muted-foreground"
                            >
                                No projects yet.
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </CardContent>
        </Card>

        <TablePagination :links="projects.links" />
    </div>

    <ConfirmDeleteModal
        :open="deleteDialogOpen"
        title="Delete project"
        :description="`This will permanently delete “${projectToDelete?.name}”.`"
        :delete-url="projectToDelete ? destroy.url(projectToDelete.id) : null"
        @update:open="deleteDialogOpen = $event"
    />
</template>
