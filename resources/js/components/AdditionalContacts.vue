<script setup lang="ts">
import { Plus, X } from '@lucide/vue';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type AdditionalContact = {
    name: string;
    role: string | null;
    phone: string | null;
    email: string | null;
};

const props = withDefaults(
    defineProps<{
        /** The form field the contacts are sent as, e.g. `builder_contacts`. */
        name: string;
        errors: Record<string, string | undefined>;
        initial?: AdditionalContact[];
        title?: string;
    }>(),
    { initial: () => [], title: 'Additional contacts' },
);

let nextKey = 0;

const contacts = ref(
    props.initial.map((contact) => ({ key: nextKey++, ...contact })),
);

const addContact = () => {
    contacts.value.push({
        key: nextKey++,
        name: '',
        role: '',
        phone: '',
        email: '',
    });
};

const removeContact = (key: number) => {
    contacts.value = contacts.value.filter((contact) => contact.key !== key);
};

const fields = [
    { field: 'role', label: 'Role', placeholder: 'e.g. Purchase Manager' },
    { field: 'phone', label: 'Phone', placeholder: 'Phone number' },
    { field: 'email', label: 'Email', placeholder: 'Email address' },
] as const;
</script>

<template>
    <div class="space-y-3">
        <div class="flex items-center justify-between">
            <h3 class="text-sm font-medium">{{ title }}</h3>
            <Button
                type="button"
                variant="outline"
                size="sm"
                :data-test="`add-${name}`"
                @click="addContact"
            >
                <Plus class="h-4 w-4" /> Add contact
            </Button>
        </div>

        <div
            v-for="(contact, index) in contacts"
            :key="contact.key"
            class="grid grid-cols-1 items-start gap-3 rounded-lg border p-3 md:grid-cols-[repeat(4,minmax(0,1fr))_auto]"
        >
            <div class="grid gap-1.5">
                <Label :for="`${name}-name-${contact.key}`">Name *</Label>
                <Input
                    :id="`${name}-name-${contact.key}`"
                    :name="`${name}[${index}][name]`"
                    :default-value="contact.name"
                    placeholder="Contact name"
                    required
                />
                <InputError :message="errors[`${name}.${index}.name`]" />
            </div>

            <div
                v-for="input in fields"
                :key="input.field"
                class="grid gap-1.5"
            >
                <Label :for="`${name}-${input.field}-${contact.key}`">{{
                    input.label
                }}</Label>
                <Input
                    :id="`${name}-${input.field}-${contact.key}`"
                    :name="`${name}[${index}][${input.field}]`"
                    :type="input.field === 'email' ? 'email' : 'text'"
                    :default-value="contact[input.field] ?? undefined"
                    :placeholder="input.placeholder"
                />
                <InputError
                    :message="errors[`${name}.${index}.${input.field}`]"
                />
            </div>

            <Button
                type="button"
                size="icon"
                variant="ghost"
                class="md:mt-6"
                :aria-label="`Remove contact ${index + 1}`"
                @click="removeContact(contact.key)"
            >
                <X class="h-4 w-4" />
            </Button>
        </div>

        <p
            v-if="contacts.length === 0"
            class="rounded-lg border border-dashed py-3 text-center text-sm text-muted-foreground"
        >
            No additional contacts.
        </p>
    </div>
</template>
