<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Eye,
    Pencil,
    Plus,
    Trash2,
    Search,
    User,
    CalendarDays,
    X,
    Building2,
    PencilRuler,
    Loader,
    CircleCheckBig,
    BarChart3,
    Package,
} from '@lucide/vue';
import { watchDebounced } from '@vueuse/core';
import { computed, ref } from 'vue';
import ConfirmDeleteModal from '@/components/ConfirmDeleteModal.vue';
import Heading from '@/components/Heading.vue';
import StatCard from '@/components/StatCard.vue';
import TablePagination from '@/components/TablePagination.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
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
import { formatDate } from '@/lib/utils';
import {
    create,
    destroy,
    edit,
    index,
    show,
    analytics,
} from '@/routes/projects';
import type { Filters, Paginated, ProjectListItem, NamedOption } from '@/types';

const props = defineProps<{
    projects: Paginated<ProjectListItem>;
    builders: NamedOption[];
    projectCategories: NamedOption[];
    products: NamedOption[];
    statuses: string[];
    users: NamedOption[];
    stats: {
        total: number;
        planning: number;
        ongoing: number;
        completed: number;
    };
    filters: Filters & {
        builder_id?: string | number;
        project_category_id?: string | number;
        status?: string;
        product_id?: string | number;
        created_by?: string | number;
        created_from?: string;
        created_to?: string;
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
const productId = ref(
    props.filters.product_id ? String(props.filters.product_id) : 'all',
);
const createdBy = ref(
    props.filters.created_by ? String(props.filters.created_by) : 'all',
);
const createdFrom = ref(props.filters.created_from ?? '');
const createdTo = ref(props.filters.created_to ?? '');

const updateFilters = () => {
    router.get(
        window.location.pathname,
        {
            search: search.value || undefined,
            builder_id: builderId.value !== 'all' ? builderId.value : undefined,
            project_category_id:
                categoryId.value !== 'all' ? categoryId.value : undefined,
            status: status.value !== 'all' ? status.value : undefined,
            product_id: productId.value !== 'all' ? productId.value : undefined,
            created_by: createdBy.value !== 'all' ? createdBy.value : undefined,
            created_from: createdFrom.value || undefined,
            created_to: createdTo.value || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
};

const hasActiveFilters = computed(
    () =>
        search.value !== '' ||
        builderId.value !== 'all' ||
        categoryId.value !== 'all' ||
        status.value !== 'all' ||
        productId.value !== 'all' ||
        createdBy.value !== 'all' ||
        createdFrom.value !== '' ||
        createdTo.value !== '',
);

const clearFilters = () => {
    search.value = '';
    builderId.value = 'all';
    categoryId.value = 'all';
    status.value = 'all';
    productId.value = 'all';
    createdBy.value = 'all';
    createdFrom.value = '';
    createdTo.value = '';
    updateFilters();
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

const statCards = computed(() => [
    {
        key: 'total',
        label: 'Total Projects',
        value: props.stats.total,
        icon: Building2,
        color: '#4f46e5',
    },
    {
        key: 'planning',
        label: 'Planning',
        value: props.stats.planning,
        icon: PencilRuler,
        color: '#7c3aed',
    },
    {
        key: 'ongoing',
        label: 'Ongoing',
        value: props.stats.ongoing,
        icon: Loader,
        color: '#ca8a04',
    },
    {
        key: 'completed',
        label: 'Completed',
        value: props.stats.completed,
        icon: CircleCheckBig,
        color: '#16a34a',
    },
]);

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

            <div class="flex items-center gap-2">
                <Button variant="outline" as-child>
                    <Link :href="analytics()"><BarChart3 /> Analytics</Link>
                </Button>
                <Button as-child>
                    <Link :href="create()"><Plus /> New project</Link>
                </Button>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <StatCard
                v-for="card in statCards"
                :key="card.key"
                :label="card.label"
                :value="card.value"
                :icon="card.icon"
                :color="card.color"
            />
        </div>

        <Card>
            <CardHeader class="border-b">
                <div
                    class="flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-center"
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
                        <SelectTrigger class="w-full sm:w-[170px]">
                            <SelectValue placeholder="All Builders" />
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
                        <SelectTrigger class="w-full sm:w-[170px]">
                            <SelectValue placeholder="All Categories" />
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
                            <SelectValue placeholder="All Statuses" />
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

                    <!-- Product Filter -->
                    <Select
                        v-model="productId"
                        @update:model-value="updateFilters"
                    >
                        <SelectTrigger
                            class="flex w-full items-center gap-1.5 sm:w-[170px]"
                        >
                            <Package class="h-4 w-4 text-[#10b981]" />
                            <SelectValue placeholder="All Products" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Products</SelectItem>
                            <SelectItem
                                v-for="product in products"
                                :key="product.id"
                                :value="String(product.id)"
                            >
                                {{ product.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>

                    <!-- Created By Filter -->
                    <Select
                        v-model="createdBy"
                        @update:model-value="updateFilters"
                    >
                        <SelectTrigger
                            class="flex w-full items-center gap-1.5 sm:w-[170px]"
                        >
                            <User class="h-4 w-4 text-[#0ea5e9]" />
                            <SelectValue placeholder="Created by" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Creators</SelectItem>
                            <SelectItem
                                v-for="user in users"
                                :key="user.id"
                                :value="String(user.id)"
                            >
                                {{ user.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>

                    <!-- Created Date Range -->
                    <div class="flex items-center gap-1.5">
                        <CalendarDays class="h-4 w-4 text-[#16a34a]" />
                        <Input
                            v-model="createdFrom"
                            type="date"
                            class="w-[150px]"
                            aria-label="Created from"
                            @change="updateFilters"
                        />
                        <span class="text-sm text-muted-foreground">to</span>
                        <Input
                            v-model="createdTo"
                            type="date"
                            class="w-[150px]"
                            aria-label="Created to"
                            @change="updateFilters"
                        />
                    </div>

                    <!-- Clear Filters -->
                    <Button
                        v-if="hasActiveFilters"
                        variant="ghost"
                        size="sm"
                        class="text-muted-foreground"
                        data-test="clear-filters"
                        @click="clearFilters"
                    >
                        <X class="h-4 w-4" /> Clear
                    </Button>
                </div>
            </CardHeader>
            <CardContent>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead class="w-12">S.No.</TableHead>
                            <TableHead>Name</TableHead>
                            <TableHead>Builder</TableHead>
                            <TableHead>Category</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead>Created</TableHead>
                            <TableHead class="text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="(project, index) in projects.data"
                            :key="project.id"
                        >
                            <TableCell
                                class="font-medium text-muted-foreground"
                            >
                                {{ (projects.from ?? 1) + index }}
                            </TableCell>
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
                            <TableCell>
                                <div class="text-sm">
                                    {{ formatDate(project.created_at) }}
                                </div>
                                <div class="text-xs text-muted-foreground">
                                    {{ project.creator?.name ?? '—' }}
                                </div>
                            </TableCell>
                            <TableCell class="space-x-1.5 text-right">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    as-child
                                    class="bg-blue-50 text-blue-600 hover:bg-blue-100 hover:text-blue-800 dark:bg-blue-950/30 dark:text-blue-400 dark:hover:bg-blue-900/40 dark:hover:text-blue-300"
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
                                    class="bg-amber-50 text-amber-600 hover:bg-amber-100 hover:text-amber-800 dark:bg-amber-950/30 dark:text-amber-400 dark:hover:bg-amber-900/40 dark:hover:text-amber-300"
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
                                    class="bg-red-50 text-red-600 hover:bg-red-100 hover:text-red-800 dark:bg-red-950/30 dark:text-red-400 dark:hover:bg-red-900/40 dark:hover:text-red-300"
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
                                :colspan="7"
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
