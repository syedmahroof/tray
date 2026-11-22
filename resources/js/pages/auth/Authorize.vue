<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { approve, deny } from '@/routes/passport/authorizations';

defineProps<{
    client: { id: string; name: string };
    email: string;
    authToken: string;
    csrfToken: string;
    state: string;
}>();

defineOptions({
    layout: {
        title: 'Connect an application',
        description:
            'Review the application requesting access to your account.',
    },
});
</script>

<template>
    <div class="space-y-6">
        <Head title="Authorize application" />
        <div class="space-y-3 text-sm">
            <p class="text-lg font-semibold">Authorize {{ client.name }}?</p>
            <p class="text-muted-foreground">Signed in as {{ email }}</p>
            <p>
                This application can view records and create quotations, sales
                orders, and purchase orders using your account permissions. Your
                branch and product access restrictions still apply.
            </p>
        </div>
        <div class="flex gap-3">
            <form :action="deny.url()" method="post" class="flex-1">
                <input type="hidden" name="_token" :value="csrfToken" />
                <input type="hidden" name="_method" value="DELETE" />
                <input type="hidden" name="client_id" :value="client.id" />
                <input type="hidden" name="auth_token" :value="authToken" />
                <input type="hidden" name="state" :value="state" />
                <Button type="submit" variant="outline" class="w-full">
                    Cancel
                </Button>
            </form>
            <form :action="approve.url()" method="post" class="flex-1">
                <input type="hidden" name="_token" :value="csrfToken" />
                <input type="hidden" name="client_id" :value="client.id" />
                <input type="hidden" name="auth_token" :value="authToken" />
                <input type="hidden" name="state" :value="state" />
                <Button type="submit" class="w-full">Authorize</Button>
            </form>
        </div>
    </div>
</template>
