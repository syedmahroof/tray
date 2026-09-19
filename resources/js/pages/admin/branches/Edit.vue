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

            <div class="space-y-4 rounded-lg border p-4">
                <div>
                    <Label class="text-base">Bank details</Label>
                    <p class="text-sm text-muted-foreground">
                        Printed on this branch's quotations. Leave blank to use
                        the company's own account.
                    </p>
                </div>

                <div class="grid gap-2">
                    <Label for="bank_name">Bank name</Label>
                    <Input
                        id="bank_name"
                        name="bank_name"
                        :default-value="branch.bank_name ?? undefined"
                    />
                    <InputError :message="errors.bank_name" />
                </div>

                <div class="grid gap-2">
                    <Label for="bank_account_number">A/c no.</Label>
                    <Input
                        id="bank_account_number"
                        name="bank_account_number"
                        :default-value="branch.bank_account_number ?? undefined"
                    />
                    <InputError :message="errors.bank_account_number" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="bank_branch">Bank branch</Label>
                        <Input
                            id="bank_branch"
                            name="bank_branch"
                            :default-value="branch.bank_branch ?? undefined"
                        />
                        <InputError :message="errors.bank_branch" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="bank_ifsc">IFS code</Label>
                        <Input
                            id="bank_ifsc"
                            name="bank_ifsc"
                            :default-value="branch.bank_ifsc ?? undefined"
                        />
                        <InputError :message="errors.bank_ifsc" />
                    </div>
                </div>
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
