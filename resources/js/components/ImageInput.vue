<script setup lang="ts">
import { ImagePlus, X } from '@lucide/vue';
import { computed, onBeforeUnmount, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Label } from '@/components/ui/label';

/**
 * An image field for Inertia `<Form>` submissions: a preview, a native file
 * input the form picks up by name, and a way to remove the current image,
 * sent as `remove_<name>`.
 */
const props = withDefaults(
    defineProps<{
        id: string;
        name: string;
        label: string;
        currentUrl?: string | null;
        error?: string;
        /** Preview a wide image, such as a banner, uncropped. */
        wide?: boolean;
    }>(),
    { currentUrl: null, error: undefined, wide: false },
);

const input = ref<HTMLInputElement | null>(null);
const previewUrl = ref<string | null>(null);
const removed = ref(false);

const shown = computed(
    () => previewUrl.value ?? (removed.value ? null : props.currentUrl),
);

const releasePreview = () => {
    if (previewUrl.value) {
        URL.revokeObjectURL(previewUrl.value);
        previewUrl.value = null;
    }
};

const onChange = (event: Event) => {
    const file = (event.target as HTMLInputElement).files?.[0];

    releasePreview();

    if (file) {
        previewUrl.value = URL.createObjectURL(file);
        removed.value = false;
    }
};

const clear = () => {
    if (input.value) {
        input.value.value = '';
    }

    releasePreview();
    removed.value = Boolean(props.currentUrl);
};

onBeforeUnmount(releasePreview);
</script>

<template>
    <div class="grid gap-2">
        <Label :for="id">{{ label }}</Label>
        <div
            class="flex gap-4"
            :class="wide ? 'flex-col items-start' : 'items-center'"
        >
            <div
                class="flex shrink-0 items-center justify-center overflow-hidden rounded-lg border bg-muted"
                :class="wide ? 'h-24 w-full' : 'size-20'"
            >
                <img
                    v-if="shown"
                    :src="shown"
                    :alt="label"
                    class="size-full"
                    :class="wide ? 'object-contain' : 'object-cover'"
                />
                <ImagePlus v-else class="size-6 text-muted-foreground" />
            </div>
            <div class="grid min-w-0 gap-2">
                <input
                    :id="id"
                    ref="input"
                    type="file"
                    :name="name"
                    accept="image/png,image/jpeg,image/webp"
                    class="max-w-full text-sm text-muted-foreground file:mr-3 file:rounded-md file:border file:border-input file:bg-background file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-foreground hover:file:bg-muted"
                    @change="onChange"
                />
                <p class="text-xs text-muted-foreground">
                    PNG, JPG or WebP, up to 2 MB.
                </p>
                <button
                    v-if="shown"
                    type="button"
                    class="inline-flex w-fit items-center gap-1 text-xs text-destructive hover:underline"
                    @click="clear"
                >
                    <X class="size-3" /> Remove {{ label.toLowerCase() }}
                </button>
            </div>
        </div>
        <input
            v-if="removed"
            type="hidden"
            :name="`remove_${name}`"
            value="1"
        />
        <InputError :message="error" />
    </div>
</template>
