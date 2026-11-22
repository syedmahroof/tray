<script setup lang="ts">
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';

/** Long text cut to a couple of lines in a table, shown in full on hover. */
withDefaults(
    defineProps<{
        text?: string | null;
        /** The width the text is clipped at. */
        widthClass?: string;
    }>(),
    { text: null, widthClass: 'max-w-56' },
);
</script>

<template>
    <TooltipProvider v-if="text" :delay-duration="200">
        <Tooltip>
            <TooltipTrigger as-child>
                <span
                    tabindex="0"
                    class="line-clamp-2 cursor-default text-sm break-words whitespace-normal"
                    :class="widthClass"
                    data-test="clipped-text"
                    >{{ text }}</span
                >
            </TooltipTrigger>
            <TooltipContent
                class="max-w-sm text-left text-pretty whitespace-pre-line"
            >
                {{ text }}
            </TooltipContent>
        </Tooltip>
    </TooltipProvider>
    <span v-else class="text-muted-foreground">—</span>
</template>
