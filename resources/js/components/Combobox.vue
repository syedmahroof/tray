<script setup lang="ts">
import { Package, Check, ChevronsUpDown, Plus } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import Thumbnail from '@/components/Thumbnail.vue';
import { Button } from '@/components/ui/button';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { cn } from '@/lib/utils';

type Option = {
    value: string;
    label: string;
    /** A second, smaller line under the label, e.g. a product's brand and code. */
    description?: string;
    /** A thumbnail URL; options without one show their initials when `thumbnails` is on. */
    image?: string | null;
};

const props = defineProps<{
    options: Option[];
    name?: string;
    placeholder?: string;
    emptyText?: string;
    disabled?: boolean;
    /** Extra classes for the dropdown, e.g. to widen it past a narrow trigger. */
    contentClass?: string;
    /** Label for the inline create action; omit to hide it. */
    createLabel?: string;
    /** Show a picture beside each option, e.g. for products. */
    thumbnails?: boolean;
}>();

const emit = defineEmits<{
    /** The user asked to create an option, with whatever they had typed. */
    create: [term: string];
}>();

const modelValue = defineModel<string | undefined>();

const open = ref(false);
const searchTerm = ref('');

watch(open, (value) => {
    if (!value) {
        searchTerm.value = '';
    }
});

const startCreate = () => {
    open.value = false;
    emit('create', searchTerm.value.trim());
};

const selectedOption = computed(() =>
    props.options.find((option) => option.value === modelValue.value),
);

const selectedLabel = computed(() => selectedOption.value?.label);

const select = (value: string) => {
    modelValue.value = modelValue.value === value ? undefined : value;
    open.value = false;
};
</script>

<template>
    <input v-if="name" type="hidden" :name="name" :value="modelValue ?? ''" />

    <Popover v-model:open="open">
        <PopoverTrigger as-child>
            <Button
                type="button"
                variant="outline"
                role="combobox"
                :aria-expanded="open"
                :disabled="disabled"
                :title="selectedLabel"
                class="h-auto min-h-9 w-full justify-between py-1.5 text-left font-normal whitespace-normal"
            >
                <span class="flex min-w-0 items-center gap-2">
                    <Thumbnail
                        :fallback-icon="Package"
                        v-if="thumbnails && selectedOption?.image"
                        :src="selectedOption.image"
                        :name="selectedOption.label"
                        class="size-7"
                    />
                    <span class="min-w-0">
                        <span
                            :class="
                                cn(
                                    'block break-words',
                                    !selectedLabel && 'text-muted-foreground',
                                )
                            "
                        >
                            {{ selectedLabel ?? placeholder ?? 'Select…' }}
                        </span>
                        <span
                            v-if="selectedOption?.description"
                            class="block text-xs text-muted-foreground"
                        >
                            {{ selectedOption.description }}
                        </span>
                    </span>
                </span>
                <ChevronsUpDown class="ml-2 h-4 w-4 shrink-0 opacity-50" />
            </Button>
        </PopoverTrigger>
        <PopoverContent
            :class="cn('w-(--reka-popover-trigger-width) p-0', contentClass)"
        >
            <Command>
                <CommandInput
                    :placeholder="placeholder ?? 'Search…'"
                    @input="
                        searchTerm = ($event.target as HTMLInputElement).value
                    "
                />
                <CommandList>
                    <CommandEmpty>{{
                        emptyText ?? 'No results found.'
                    }}</CommandEmpty>
                    <CommandGroup>
                        <CommandItem
                            v-for="option in options"
                            :key="option.value"
                            :value="option.value"
                            @select="select(option.value)"
                        >
                            <Check
                                :class="
                                    cn(
                                        'mr-2 h-4 w-4',
                                        modelValue === option.value
                                            ? 'opacity-100'
                                            : 'opacity-0',
                                    )
                                "
                            />
                            <Thumbnail
                                :fallback-icon="Package"
                                v-if="thumbnails"
                                :src="option.image"
                                :name="option.label"
                            />
                            <span class="min-w-0 flex-1">
                                <span class="block break-words">
                                    {{ option.label }}
                                </span>
                                <span
                                    v-if="option.description"
                                    class="block text-xs text-muted-foreground"
                                >
                                    {{ option.description }}
                                </span>
                            </span>
                        </CommandItem>
                    </CommandGroup>
                </CommandList>
            </Command>
            <div v-if="createLabel" class="border-t p-1">
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    class="w-full justify-start font-normal"
                    @click="startCreate"
                >
                    <Plus class="mr-2 h-4 w-4 shrink-0" />
                    <span class="truncate">
                        {{
                            searchTerm.trim()
                                ? `Add “${searchTerm.trim()}”`
                                : createLabel
                        }}
                    </span>
                </Button>
            </div>
        </PopoverContent>
    </Popover>
</template>
