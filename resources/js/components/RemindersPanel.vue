<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import ConfirmDeleteModal from '@/components/ConfirmDeleteModal.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Reminder } from '@/types';

defineProps<{
    reminders: Reminder[];
    storeUrl: string;
    deleteUrl: (id: number) => string;
    canCreate: boolean;
    canDelete: boolean;
}>();

const deleteDialogOpen = ref(false);
const reminderToDelete = ref<Reminder | null>(null);

const confirmDelete = (reminder: Reminder) => {
    reminderToDelete.value = reminder;
    deleteDialogOpen.value = true;
};
</script>

<template>
    <div class="space-y-6">
        <Form
            v-if="canCreate"
            :action="storeUrl"
            method="post"
            reset-on-success
            class="grid gap-3 sm:grid-cols-[1fr_auto]"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="reminder-title" class="sr-only">Title</Label>
                <Input
                    id="reminder-title"
                    name="title"
                    placeholder="Reminder title"
                    required
                />
                <InputError :message="errors.title" />
            </div>

            <div class="grid gap-2">
                <Label for="reminder-remind-at" class="sr-only"
                    >Remind at</Label
                >
                <Input
                    id="reminder-remind-at"
                    name="remind_at"
                    type="datetime-local"
                    required
                />
                <InputError :message="errors.remind_at" />
            </div>

            <Button
                type="submit"
                size="sm"
                class="sm:col-span-2 sm:justify-self-start"
                :disabled="processing"
            >
                Add reminder
            </Button>
        </Form>

        <div class="space-y-3">
            <div
                v-for="reminder in reminders"
                :key="reminder.id"
                class="rounded-lg border p-4"
            >
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-sm font-medium">{{ reminder.title }}</p>
                        <p class="text-sm text-muted-foreground">
                            {{ new Date(reminder.remind_at).toLocaleString() }}
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <Badge
                            :variant="
                                reminder.status === 'done'
                                    ? 'default'
                                    : 'secondary'
                            "
                        >
                            {{
                                reminder.status === 'done' ? 'Done' : 'Pending'
                            }}
                        </Badge>
                        <Button
                            v-if="canDelete"
                            variant="ghost"
                            size="sm"
                            aria-label="Delete reminder"
                            @click="confirmDelete(reminder)"
                        >
                            <Trash2 class="h-4 w-4" />
                        </Button>
                    </div>
                </div>
                <p class="mt-2 text-xs text-muted-foreground">
                    {{ reminder.user.name }}
                </p>
            </div>
            <p
                v-if="reminders.length === 0"
                class="text-sm text-muted-foreground"
            >
                No reminders yet.
            </p>
        </div>
    </div>

    <ConfirmDeleteModal
        :open="deleteDialogOpen"
        title="Delete reminder"
        description="This will permanently delete this reminder."
        :delete-url="reminderToDelete ? deleteUrl(reminderToDelete.id) : null"
        @update:open="deleteDialogOpen = $event"
    />
</template>
