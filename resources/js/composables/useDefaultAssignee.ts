import { usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import type { Ref } from 'vue';

/** Roles that assign records to others, so their forms start unassigned. */
const ADMIN_ROLES = ['Super Admin', 'Admin'];

/**
 * The "Assigned to" value a create form starts with: the signed-in user,
 * unless they are an admin. It can still be changed on the form.
 */
export function useDefaultAssignee(): Ref<string | undefined> {
    const { auth } = usePage().props;
    const isAdmin = auth.roles.some((role) => ADMIN_ROLES.includes(role));

    return ref(isAdmin || !auth.user ? undefined : String(auth.user.id));
}
