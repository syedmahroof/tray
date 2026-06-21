<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { edit, index, update } from '@/routes/users';
import type { Branch, UserDetail } from '@/types';

const props = defineProps<{
    user: UserDetail;
    branches: Branch[];
    roles: string[];
}>();

defineOptions({
    layout: (props: { user: UserDetail }) => ({
        breadcrumbs: [
            { title: 'Users', href: index() },
            { title: props.user.name, href: edit(props.user.id) },
        ],
    }),
});
</script>

<template>
    <Head :title="`Edit ${user.name}`" />

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            :title="`Edit ${user.name}`"
            description="Update the user's details, branch, and role"
        />

        <Form
            v-bind="update.form(props.user.id)"
            :reset-on-success="['password', 'password_confirmation']"
            class="max-w-xl space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="name">Name</Label>
                <Input
                    id="name"
                    name="name"
                    :default-value="user.name"
                    required
                    autofocus
                />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="email">Email address</Label>
                <Input
                    id="email"
                    name="email"
                    type="email"
                    :default-value="user.email"
                    required
                />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <Label for="password">New password</Label>
                <PasswordInput
                    id="password"
                    name="password"
                    placeholder="Leave blank to keep the current password"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="grid gap-2">
                <Label for="password_confirmation">
                    Confirm new password
                </Label>
                <PasswordInput
                    id="password_confirmation"
                    name="password_confirmation"
                />
                <InputError :message="errors.password_confirmation" />
            </div>

            <div class="grid gap-2">
                <Label for="branch_id">Branch</Label>
                <Select
                    name="branch_id"
                    :default-value="
                        user.branch_id ? String(user.branch_id) : undefined
                    "
                >
                    <SelectTrigger class="w-full">
                        <SelectValue placeholder="Select a branch" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="branch in branches"
                            :key="branch.id"
                            :value="String(branch.id)"
                        >
                            {{ branch.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="errors.branch_id" />
            </div>

            <div class="grid gap-2">
                <Label for="role">Role</Label>
                <Select name="role" :default-value="user.role ?? undefined">
                    <SelectTrigger class="w-full">
                        <SelectValue placeholder="Select a role" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="role in roles"
                            :key="role"
                            :value="role"
                        >
                            {{ role }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="errors.role" />
            </div>

            <Button type="submit" :disabled="processing">Save</Button>
        </Form>
    </div>
</template>
