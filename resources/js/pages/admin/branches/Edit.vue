<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit, index, update } from '@/routes/branches';
import type { Branch } from '@/types';

const props = defineProps<{
    branch: Branch;
}>();

defineOptions({
    layout: (props: { branch: Branch }) => ({
        breadcrumbs: [
            { title: 'Branches', href: index() },
            { title: props.branch.name, href: edit(props.branch.id) },
        ],
    }),
});
</script>

<template>
    <Head :title="`Edit ${branch.name}`" />

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            :title="`Edit ${branch.name}`"
            description="Update branch details"
        />

        <Form
            v-bind="update.form(props.branch.id)"
            class="max-w-xl space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="name">Name</Label>
                <Input
                    id="name"
                    name="name"
                    :default-value="branch.name"
                    required
                    autofocus
                />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="code">Code</Label>
                <Input
                    id="code"
                    name="code"
                    :default-value="branch.code"
                    required
                />
                <InputError :message="errors.code" />
            </div>

            <div class="grid gap-2">
                <Label for="city">City</Label>
                <Input
                    id="city"
                    name="city"
                    :default-value="branch.city ?? undefined"
                />
                <InputError :message="errors.city" />
            </div>

            <div class="grid gap-2">
                <Label for="address">Address</Label>
                <Input
                    id="address"
                    name="address"
                    :default-value="branch.address ?? undefined"
                />
                <InputError :message="errors.address" />
            </div>

            <Label for="is_active" class="flex items-center space-x-3">
                <Checkbox
                    id="is_active"
                    name="is_active"
                    :default-value="branch.is_active"
                />
                <span>Active</span>
            </Label>

            <Button type="submit" :disabled="processing">Save</Button>
        </Form>
    </div>
</template>
