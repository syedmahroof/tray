<script setup lang="ts">
import type { Component, HTMLAttributes } from 'vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { getInitials } from '@/composables/useInitials';
import { cn } from '@/lib/utils';

/**
 * A small square picture for a record, falling back to an icon when one is
 * given, else to its initials.
 */
const props = withDefaults(
    defineProps<{
        src?: string | null;
        name: string;
        class?: HTMLAttributes['class'];
        /** Shown instead of initials when there is no picture, e.g. for products whose names start with numbers. */
        fallbackIcon?: Component;
    }>(),
    { src: null, fallbackIcon: undefined },
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
            <component
                :is="props.fallbackIcon"
                v-if="props.fallbackIcon"
                class="size-1/2 opacity-60"
            />
            <template v-else>{{ getInitials(name) }}</template>
        </AvatarFallback>
    </Avatar>
</template>
