<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowLeft, Building2, MapPin, Pencil, Phone, Mail, User } from '@lucide/vue';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
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
import { edit, index, show } from '@/routes/builders';
import { show as showProject } from '@/routes/projects';
import type { Builder, Country, State, District, Project } from '@/types';

type BuilderDetail = Builder & {
    country: Country | null;
    state: State | null;
    district: District | null;
    projects: Project[];
};

const props = defineProps<{
    builder: BuilderDetail;
}>();

defineOptions({
    layout: (props: { builder: BuilderDetail }) => ({
        breadcrumbs: [
            { title: 'Builders', href: index() },
            { title: props.builder.name, href: show(props.builder.id) },
        ],
    }),
});

const statusVariant = (status: string) => {
    if (status === 'completed') {
        return 'default' as const;
    }

    if (status === 'ongoing') {
        return 'outline' as const;
    }

    return 'secondary' as const;
};

const permissions = computed(() => usePage().props.auth.permissions);
</script>

<template>
    <Head :title="builder.name" />

    <div class="flex flex-col space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <Link
                    :href="index()"
                    class="mb-2 inline-flex items-center gap-1 text-sm text-muted-foreground hover:underline"
                >
                    <ArrowLeft class="h-4 w-4" /> Back to List
                </Link>
                <Heading
                    variant="small"
                    :title="builder.name"
                    description="Real-estate Builder Details"
                />
            </div>

            <Button v-if="permissions.includes('builders.update')" as-child>
                <Link :href="edit(builder.id)"><Pencil /> Edit</Link>
            </Button>
        </div>

        <div class="grid gap-6 md:grid-cols-2">
            <!-- Profile Info -->
            <div class="rounded-lg border p-4 space-y-4">
                <div class="flex items-center gap-2 border-b pb-2">
                    <User class="h-5 w-5 text-muted-foreground" />
                    <h3 class="font-semibold text-base">Contact Information</h3>
                </div>
                <div class="space-y-3">
                    <div>
                        <p class="text-sm text-muted-foreground">Contact Person</p>
                        <p class="text-sm font-medium">{{ builder.contact_person ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-muted-foreground">Phone</p>
                        <p class="text-sm font-medium flex items-center gap-1.5">
                            <Phone v-if="builder.phone" class="h-3.5 w-3.5 text-muted-foreground" />
                            {{ builder.phone ?? '—' }}
                        </p>
                    </div>
                    <div>
                        <p class="text-sm text-muted-foreground">Email</p>
                        <p class="text-sm font-medium flex items-center gap-1.5">
                            <Mail v-if="builder.email" class="h-3.5 w-3.5 text-muted-foreground" />
                            {{ builder.email ?? '—' }}
                        </p>
                    </div>
                    <div>
                        <p class="text-sm text-muted-foreground">Status</p>
                        <Badge :variant="builder.is_active ? 'default' : 'secondary'" class="mt-0.5">
                            {{ builder.is_active ? 'Active' : 'Inactive' }}
                        </Badge>
                    </div>
                </div>
            </div>

            <!-- Location Info -->
            <div class="rounded-lg border p-4 space-y-4">
                <div class="flex items-center gap-2 border-b pb-2">
                    <MapPin class="h-5 w-5 text-muted-foreground" />
                    <h3 class="font-semibold text-base">Location</h3>
                </div>
                <div class="space-y-3">
                    <div>
                        <p class="text-sm text-muted-foreground">Address</p>
                        <p class="text-sm font-medium whitespace-pre-wrap">{{ builder.address ?? '—' }}</p>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm text-muted-foreground">District</p>
                            <p class="text-sm font-medium">{{ builder.district?.name ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-muted-foreground">State</p>
                            <p class="text-sm font-medium">{{ builder.state?.name ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-muted-foreground">Country</p>
                            <p class="text-sm font-medium">{{ builder.country?.name ?? '—' }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Projects owned by builder -->
            <div class="rounded-lg border p-4 space-y-4 md:col-span-2">
                <div class="flex items-center gap-2 border-b pb-2">
                    <Building2 class="h-5 w-5 text-muted-foreground" />
                    <h3 class="font-semibold text-base">Projects ({{ builder.projects.length }})</h3>
                </div>

                <div class="overflow-x-auto">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Name</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Start Date</TableHead>
                                <TableHead>End Date</TableHead>
                                <TableHead class="text-right">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="project in builder.projects" :key="project.id">
                                <TableCell class="font-medium">
                                    <Link :href="showProject(project.id)" class="hover:underline text-sm font-semibold">
                                        {{ project.name }}
                                    </Link>
                                </TableCell>
                                <TableCell>
                                    <Badge :variant="statusVariant(project.status)" class="capitalize">
                                        {{ project.status }}
                                    </Badge>
                                </TableCell>
                                <TableCell>{{ project.start_date ?? '—' }}</TableCell>
                                <TableCell>{{ project.end_date ?? '—' }}</TableCell>
                                <TableCell class="text-right">
                                    <Button variant="ghost" size="sm" as-child>
                                        <Link :href="showProject(project.id)">View Details</Link>
                                    </Button>
                                </TableCell>
                            </TableRow>
                            <TableRow v-if="builder.projects.length === 0">
                                <TableCell colspan="5" class="text-center text-muted-foreground py-8">
                                    No projects yet for this builder.
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>
            </div>
        </div>
    </div>
</template>
