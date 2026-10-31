<script setup lang="ts">
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Branch } from '@/types';

/**
 * The series a branch numbers its quotations in, shared by the create and
 * edit forms. Leaving the prefix blank keeps the company-wide numbering.
 */
const props = defineProps<{
    branch?: Branch;
    errors: Record<string, string | undefined>;
}>();

const prefix = ref(props.branch?.quotation_prefix ?? '');
const nextNumber = ref<string | number>(
    props.branch?.quotation_next_number ?? 1,
);

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
            <Label class="text-base">Quotation series</Label>
            <p class="text-sm text-muted-foreground">
                Numbers this branch's new quotations. Leave the prefix blank to
                use the company-wide QT-YYYY-NNNNN numbering.
            </p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="quotation_prefix">Prefix</Label>
                <Input
                    id="quotation_prefix"
                    v-model="prefix"
                    name="quotation_prefix"
                    placeholder="e.g. BTCLT/QT/26-27/"
                />
                <InputError :message="errors.quotation_prefix" />
            </div>

            <div class="grid gap-2">
                <Label for="quotation_next_number">Next number</Label>
                <Input
                    id="quotation_next_number"
                    v-model="nextNumber"
                    name="quotation_next_number"
                    type="number"
                    min="1"
                    step="1"
                />
                <InputError :message="errors.quotation_next_number" />
            </div>
        </div>

        <p v-if="preview" class="text-sm text-muted-foreground">
            Next quotation will be
            <span class="font-mono font-medium text-foreground">{{
                preview
            }}</span>
        </p>
    </div>
</template>
