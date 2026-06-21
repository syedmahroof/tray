<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import type { PaginationLink } from '@/types';

defineProps<{
    links: PaginationLink[];
}>();
</script>

<template>
    <div v-if="links.length > 3" class="flex items-center justify-center gap-1">
        <Button
            v-for="(link, index) in links"
            :key="index"
            variant="outline"
            size="sm"
            :disabled="!link.url"
            :class="{ 'bg-muted': link.active }"
            as-child
        >
            <Link v-if="link.url" :href="link.url" preserve-scroll>
                <span v-html="link.label" />
            </Link>
            <span v-else v-html="link.label" />
        </Button>
    </div>
</template>
