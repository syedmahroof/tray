<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

type Props = {
    open: boolean;
    title: string;
    description: string;
    deleteUrl: string | null;
};

const props = defineProps<Props>();
const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const processing = ref(false);

const confirm = () => {
    if (!props.deleteUrl) {
        return;
    }

    router.delete(props.deleteUrl, {
        onStart: () => (processing.value = true),
        onFinish: () => (processing.value = false),
        onSuccess: () => emit('update:open', false),
    });
};
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ title }}</DialogTitle>
                <DialogDescription>{{ description }}</DialogDescription>
            </DialogHeader>

            <DialogFooter class="gap-2">
                <DialogClose as-child>
                    <Button variant="secondary">Cancel</Button>
                </DialogClose>

                <Button
                    variant="destructive"
                    data-test="confirm-delete-button"
                    :disabled="processing"
                    @click="confirm"
                >
                    Delete
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
