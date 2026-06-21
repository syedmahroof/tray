<script setup lang="ts">
import { Check, ChevronsUpDown, X } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
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

const props = withDefaults(
    defineProps<{
        options: Option[];
        name?: string;
        placeholder?: string;
        emptyText?: string;
        disabled?: boolean;
        modelValue?: string[];
    }>(),
    {
        modelValue: () => [],
    },
);

const emit = defineEmits(['update:modelValue']);

const localValue = ref<string[]>([]);

watch(
    () => props.modelValue,
    (newVal) => {
        localValue.value = newVal ? [...newVal] : [];
    },
    { immediate: true, deep: true },
);

const open = ref(false);

const selectedOptions = computed(() =>
    props.options.filter((option) => localValue.value.includes(option.value)),
);

const toggle = (value: string) => {
    const next = localValue.value.includes(value)
        ? localValue.value.filter((v) => v !== value)
        : [...localValue.value, value];
    localValue.value = next;
    emit('update:modelValue', next);
};

const remove = (value: string) => {
    const next = localValue.value.filter((v) => v !== value);
    localValue.value = next;
    emit('update:modelValue', next);
};
</script>

<template>
    <template v-if="name">
        <input
            v-for="value in localValue"
            :key="value"
            type="hidden"
            :name="`${name}[]`"
            :value="value"
        />
    </template>

    <div class="space-y-2">
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
                                selectedOptions.length === 0 &&
                                    'text-muted-foreground',
                            )
                        "
                    >
                        {{
                            selectedOptions.length > 0
                                ? `${selectedOptions.length} selected`
                                : (placeholder ?? 'Select…')
                        }}
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
                                @select="toggle(option.value)"
                            >
                                <Check
                                    :class="
                                        cn(
                                            'mr-2 h-4 w-4',
                                            localValue.includes(option.value)
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

        <div v-if="selectedOptions.length > 0" class="flex flex-wrap gap-1.5">
            <Badge
                v-for="option in selectedOptions"
                :key="option.value"
                variant="secondary"
                class="gap-1 pr-1"
            >
                {{ option.label }}
                <button
                    type="button"
                    class="rounded-full p-0.5 hover:bg-muted-foreground/20"
                    :aria-label="`Remove ${option.label}`"
                    @click="remove(option.value)"
                >
                    <X class="h-3 w-3" />
                </button>
            </Badge>
        </div>
    </div>
</template>
