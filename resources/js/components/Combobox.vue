<script setup lang="ts">
import { Check, ChevronsUpDown } from '@lucide/vue';
import { computed, ref } from 'vue';
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
};

const props = defineProps<{
    options: Option[];
    name?: string;
    placeholder?: string;
    emptyText?: string;
    disabled?: boolean;
}>();

const modelValue = defineModel<string | undefined>();

const open = ref(false);

const selectedLabel = computed(
    () =>
        props.options.find((option) => option.value === modelValue.value)
            ?.label,
);

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
                class="w-full justify-between font-normal"
            >
                <span
                    :class="
                        cn(
                            'truncate',
                            !selectedLabel && 'text-muted-foreground',
                        )
                    "
                >
                    {{ selectedLabel ?? placeholder ?? 'Select…' }}
                </span>
                <ChevronsUpDown class="ml-2 h-4 w-4 shrink-0 opacity-50" />
            </Button>
        </PopoverTrigger>
        <PopoverContent class="w-(--reka-popover-trigger-width) p-0">
            <Command>
                <CommandInput :placeholder="placeholder ?? 'Search…'" />
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
                            {{ option.label }}
                        </CommandItem>
                    </CommandGroup>
                </CommandList>
            </Command>
        </PopoverContent>
    </Popover>
</template>
