<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { create, index, store } from '@/routes/branches';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Branches', href: index() },
            { title: 'New branch', href: create() },
        ],
    },
});
</script>

<template>
    <Head title="New branch" />

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            title="New branch"
            description="Create a new branch"
        />

        <Form
            v-bind="store.form()"
            class="max-w-xl space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="name">Name</Label>
                <Input id="name" name="name" required autofocus />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="code">Code</Label>
                <Input id="code" name="code" required />
                <InputError :message="errors.code" />
            </div>

            <div class="grid gap-2">
                <Label for="city">City</Label>
                <Input id="city" name="city" />
                <InputError :message="errors.city" />
            </div>

            <div class="grid gap-2">
                <Label for="address">Address</Label>
                <Input id="address" name="address" />
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
                    <Input id="bank_name" name="bank_name" />
                    <InputError :message="errors.bank_name" />
                </div>

                <div class="grid gap-2">
                    <Label for="bank_account_number">A/c no.</Label>
                    <Input
                        id="bank_account_number"
                        name="bank_account_number"
                    />
                    <InputError :message="errors.bank_account_number" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="bank_branch">Bank branch</Label>
                        <Input id="bank_branch" name="bank_branch" />
                        <InputError :message="errors.bank_branch" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="bank_ifsc">IFS code</Label>
                        <Input id="bank_ifsc" name="bank_ifsc" />
                        <InputError :message="errors.bank_ifsc" />
                    </div>
                </div>
            </div>

            <Label for="is_active" class="flex items-center space-x-3">
                <Checkbox
                    id="is_active"
                    name="is_active"
                    :default-value="true"
                />
                <span>Active</span>
            </Label>

            <Button type="submit" :disabled="processing">
                Create branch
            </Button>
        </Form>
    </div>
</template>
