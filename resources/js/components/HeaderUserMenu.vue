<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { useInitials } from '@/composables/useInitials';

const user = usePage().props.auth.user;
const { getInitials } = useInitials();
const showAvatar = computed(() => Boolean(user.avatar));
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <button
                type="button"
                class="rounded-full transition-opacity hover:opacity-80"
                aria-label="Account menu"
                data-test="header-user-menu"
            >
                <Avatar class="size-9 rounded-full">
                    <AvatarImage
                        v-if="showAvatar"
                        :src="user.avatar!"
                        :alt="user.name"
                    />
                    <AvatarFallback class="rounded-full text-xs">
                        {{ getInitials(user.name) }}
                    </AvatarFallback>
                </Avatar>
            </button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="w-56 rounded-lg">
            <UserMenuContent :user="user" />
        </DropdownMenuContent>
    </DropdownMenu>
</template>
