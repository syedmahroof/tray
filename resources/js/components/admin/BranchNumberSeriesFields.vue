<script setup lang="ts">
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Branch } from '@/types';

/**
 * A series a branch numbers its documents in, shared by the create and edit
 * forms. Leaving the prefix blank keeps the company-wide numbering.
 */
const props = withDefaults(
    defineProps<{
        branch?: Branch;
        errors: Record<string, string | undefined>;
        document?: 'quotation' | 'purchase_order';
    }>(),
    { document: 'quotation' },
);

const settings = {
    quotation: {
        title: 'Quotation series',
        noun: 'quotation',
        fallback: 'QT-YYYY-NNNNN',
        example: 'BTCLT/QT/26-27/',
    },
    purchase_order: {
        title: 'Purchase order series',
        noun: 'purchase order',
        fallback: 'PO-YYYY-NNNNN',
        example: 'BTCLT/PO/26-27/',
    },
}[props.document];

const prefixField = `${props.document}_prefix` as const;
const nextNumberField = `${props.document}_next_number` as const;

const prefix = ref(props.branch?.[prefixField] ?? '');
const nextNumber = ref<string | number>(props.branch?.[nextNumberField] ?? 1);

const preview = computed(() => {
    if (prefix.value.trim() === '') {
        return null;
    }

    const number = Math.max(1, Number(nextNumber.value) || 1);

    return prefix.value + String(number).padStart(3, '0');
});
</script>

<template>
    <div class="space-y-4 rounded-lg border p-4">
        <div>
            <Label class="text-base">{{ settings.title }}</Label>
            <p class="text-sm text-muted-foreground">
                Numbers this branch's new {{ settings.noun }}s. Leave the prefix
                blank to use the company-wide {{ settings.fallback }} numbering.
            </p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label :for="prefixField">Prefix</Label>
                <Input
                    :id="prefixField"
                    v-model="prefix"
                    :name="prefixField"
                    :placeholder="`e.g. ${settings.example}`"
                />
                <InputError :message="errors[prefixField]" />
            </div>

            <div class="grid gap-2">
                <Label :for="nextNumberField">Next number</Label>
                <Input
                    :id="nextNumberField"
                    v-model="nextNumber"
                    :name="nextNumberField"
                    type="number"
                    min="1"
                    step="1"
                />
                <InputError :message="errors[nextNumberField]" />
            </div>
        </div>

        <p v-if="preview" class="text-sm text-muted-foreground">
            Next {{ settings.noun }} will be
            <span class="font-mono font-medium text-foreground">{{
                preview
            }}</span>
        </p>
    </div>
</template>
