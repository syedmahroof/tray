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
import { create, index, store } from '@/routes/users';
import type { Branch } from '@/types';

defineProps<{
    branches: Branch[];
    roles: string[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Users', href: index() },
            { title: 'New user', href: create() },
        ],
    },
});
</script>

<template>
    <Head title="New user" />

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            title="New user"
            description="Create a user account"
        />

        <Form
            v-bind="store.form()"
            :reset-on-success="['password', 'password_confirmation']"
            class="max-w-xl space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="name">Name</Label>
                <Input id="name" name="name" required autofocus />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="email">Email address</Label>
                <Input id="email" name="email" type="email" required />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <Label for="password">Password</Label>
                <PasswordInput id="password" name="password" required />
                <InputError :message="errors.password" />
            </div>

            <div class="grid gap-2">
                <Label for="password_confirmation">Confirm password</Label>
                <PasswordInput
                    id="password_confirmation"
                    name="password_confirmation"
                    required
                />
                <InputError :message="errors.password_confirmation" />
            </div>

            <div class="grid gap-2">
                <Label for="branch_id">Branch</Label>
                <Select name="branch_id">
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
                <Select name="role">
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

            <Button type="submit" :disabled="processing">Create user</Button>
        </Form>
    </div>
</template>
