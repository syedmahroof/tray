<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import ConfirmDeleteModal from '@/components/ConfirmDeleteModal.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import type { Note } from '@/types';

defineProps<{
    notes: Note[];
    storeUrl: string;
    deleteUrl: (id: number) => string;
    canCreate: boolean;
    canDelete: boolean;
}>();

const deleteDialogOpen = ref(false);
const noteToDelete = ref<Note | null>(null);

const confirmDelete = (note: Note) => {
    noteToDelete.value = note;
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
            class="space-y-3"
            v-slot="{ errors, processing }"
        >
            <Textarea name="body" placeholder="Add a note…" rows="3" required />
            <InputError :message="errors.body" />
            <Button type="submit" size="sm" :disabled="processing">
                Add note
            </Button>
        </Form>

        <div class="space-y-3">
            <div
                v-for="note in notes"
                :key="note.id"
                class="rounded-lg border p-4"
            >
                <div class="flex items-start justify-between gap-4">
                    <p class="text-sm whitespace-pre-wrap">{{ note.body }}</p>
                    <Button
                        v-if="canDelete"
                        variant="ghost"
                        size="sm"
                        aria-label="Delete note"
                        @click="confirmDelete(note)"
                    >
                        <Trash2 class="h-4 w-4" />
                    </Button>
                </div>
                <p class="mt-2 text-xs text-muted-foreground">
                    {{ note.user.name }} ·
                    {{ new Date(note.created_at).toLocaleString() }}
                </p>
            </div>
            <p v-if="notes.length === 0" class="text-sm text-muted-foreground">
                No notes yet.
            </p>
        </div>
    </div>

    <ConfirmDeleteModal
        :open="deleteDialogOpen"
        title="Delete note"
        description="This will permanently delete this note."
        :delete-url="noteToDelete ? deleteUrl(noteToDelete.id) : null"
        @update:open="deleteDialogOpen = $event"
    />
</template>
