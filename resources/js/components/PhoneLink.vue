<script setup lang="ts">
import { Phone } from '@lucide/vue';
import { computed } from 'vue';

/**
 * A phone number that dials on tap, so a number anywhere in the app (and the
 * installed PWA) is one click from a call. Shows the fallback when there is
 * no number to call.
 */
const props = withDefaults(
    defineProps<{
        phone?: string | null;
        icon?: boolean;
        fallback?: string;
    }>(),
    { phone: null, icon: true, fallback: '—' },
);

/** Dialers want the digits and a leading plus, nothing else. */
const href = computed(() => {
    const dialable = (props.phone ?? '').replace(/[^\d+]/g, '');

    return dialable === '' ? null : `tel:${dialable}`;
});
</script>

<template>
    <a
        v-if="href"
        :href="href"
        class="inline-flex items-center gap-1.5 text-inherit hover:text-green-700 hover:underline dark:hover:text-green-400"
        :title="`Call ${phone}`"
        @click.stop
    >
        <Phone v-if="icon" class="h-3.5 w-3.5 shrink-0 text-green-600" />
        <span class="tabular-nums">{{ phone }}</span>
    </a>
    <span v-else>{{ fallback }}</span>
</template>
