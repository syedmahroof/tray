<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { actionLabel, resourceLabel } from '@/lib/permissions';
import { edit, index, update } from '@/routes/roles';
import type { PermissionGroups, RoleDetail } from '@/types';

const props = defineProps<{
    role: RoleDetail;
    permissionGroups: PermissionGroups;
}>();

defineOptions({
    layout: (props: { role: RoleDetail }) => ({
        breadcrumbs: [
            { title: 'Roles', href: index() },
            { title: props.role.name, href: edit(props.role.id) },
        ],
    }),
});
</script>

<template>
    <Head :title="`Edit ${role.name}`" />

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            :title="`Edit ${role.name}`"
            description="Update the role name and its permissions"
        />

        <Form
            v-bind="update.form(props.role.id)"
            class="max-w-3xl space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="name">Name</Label>
                <Input
                    id="name"
                    name="name"
                    :default-value="role.name"
                    required
                    autofocus
                />
                <InputError :message="errors.name" />
            </div>

            <div class="space-y-4">
                <Label>Permissions</Label>

                <div
                    v-for="(permissions, resource) in permissionGroups"
                    :key="resource"
                    class="rounded-lg border p-4"
                >
                    <p class="mb-3 font-medium">
                        {{ resourceLabel(resource) }}
                    </p>
                    <div class="flex flex-wrap gap-4">
                        <Label
                            v-for="permission in permissions"
                            :key="permission"
                            class="flex items-center space-x-2 font-normal"
                        >
                            <Checkbox
                                name="permissions[]"
                                :value="permission"
                                :data-test="`permission-${permission.replace('.', '-')}`"
                                :default-value="
                                    role.permissions.includes(permission)
                                "
                            />
                            <span>{{ actionLabel(permission) }}</span>
                        </Label>
                    </div>
                </div>

                <InputError :message="errors.permissions" />
            </div>

            <Button type="submit" :disabled="processing">Save</Button>
        </Form>
    </div>
</template>
