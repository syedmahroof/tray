<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import { watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { quickStore } from '@/routes/customers';

export type QuickCustomer = {
    id: number;
    name: string;
    rate_tier: string | null;
    gst_number: string | null;
    branch_id: number | null;
};

const props = defineProps<{
    /** What was typed in the picker, used as the starting name. */
    initialName: string;
    /** The quotation's branch, which the new customer starts in. */
    branchId?: string;
    /** Branches the user may choose from; empty when theirs is fixed. */
    branches: { id: number; name: string }[];
}>();

const emit = defineEmits<{ created: [customer: QuickCustomer] }>();

const open = defineModel<boolean>('open', { required: true });

const form = useHttp<
    {
        name: string;
        phone: string;
        address: string;
        gst_number: string;
        branch_id: string;
    },
    QuickCustomer
>({
    name: '',
    phone: '',
    address: '',
    gst_number: '',
    branch_id: '',
});

watch(open, (isOpen) => {
    if (isOpen) {
        form.reset();
        form.clearErrors();
        form.name = props.initialName;
        form.branch_id =
            props.branchId ??
            (props.branches.length === 1 ? String(props.branches[0].id) : '');
    }
});

const submit = () => {
    form.post(quickStore.url(), {
        onSuccess: (customer) => {
            emit('created', customer);
            open.value = false;
        },
    });
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent>
            <form class="space-y-6" @submit.prevent="submit">
                <DialogHeader>
                    <DialogTitle>New customer</DialogTitle>
                    <DialogDescription>
                        For a one-time party. Only the name is needed; the
                        customer is saved and selected on this quotation.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2 sm:col-span-2">
                        <Label for="quick-customer-name">Name</Label>
                        <Input
                            id="quick-customer-name"
                            v-model="form.name"
                            required
                            autofocus
                            data-test="quick-customer-name"
                        />
                        <InputError :message="form.errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="quick-customer-phone">Phone</Label>
                        <Input id="quick-customer-phone" v-model="form.phone" />
                        <InputError :message="form.errors.phone" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="quick-customer-gst">GST Number</Label>
                        <Input
                            id="quick-customer-gst"
                            v-model="form.gst_number"
                        />
                        <InputError :message="form.errors.gst_number" />
                    </div>

                    <div
                        v-if="branches.length > 0"
                        class="grid gap-2 sm:col-span-2"
                    >
                        <Label for="quick-customer-branch">Branch</Label>
                        <Select v-model="form.branch_id">
                            <SelectTrigger
                                id="quick-customer-branch"
                                class="w-full"
                                data-test="quick-customer-branch"
                            >
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
                        <InputError :message="form.errors.branch_id" />
                    </div>

                    <div class="grid gap-2 sm:col-span-2">
                        <Label for="quick-customer-address">Address</Label>
                        <Input
                            id="quick-customer-address"
                            v-model="form.address"
                        />
                        <InputError :message="form.errors.address" />
                    </div>
                </div>

                <DialogFooter class="gap-2">
                    <Button
                        type="button"
                        variant="secondary"
                        @click="open = false"
                    >
                        Cancel
                    </Button>
                    <Button
                        type="submit"
                        data-test="create-quick-customer"
                        :disabled="form.processing"
                    >
                        Create
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
