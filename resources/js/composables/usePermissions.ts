import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * Permission checks use stable keys stored in {@see Permission.name} (e.g. roles.view).
 */
export function usePermissions() {
    const page = usePage();

    const keys = computed(
        () => page.props.auth.permission_keys ?? [],
    );

    function can(permission: string): boolean {
        return keys.value.includes(permission);
    }

    return { can, keys };
}
