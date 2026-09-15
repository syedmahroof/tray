<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { getInitials } from '@/composables/useInitials';
import { cn } from '@/lib/utils';

/** A small square picture for a record, falling back to its initials. */
const props = withDefaults(
    defineProps<{
        src?: string | null;
        name: string;
        class?: HTMLAttributes['class'];
    }>(),
    { src: null },
);
</script>

<template>
    <Avatar
        :class="cn('size-9 shrink-0 rounded-md border bg-muted', props.class)"
    >
        <AvatarImage v-if="src" :src="src" :alt="name" class="object-cover" />
        <AvatarFallback
            class="rounded-md text-xs font-medium text-muted-foreground"
        >
            {{ getInitials(name) }}
        </AvatarFallback>
    </Avatar>
</template>
