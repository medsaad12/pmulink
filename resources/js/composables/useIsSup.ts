import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { Auth } from '@/types/auth';

export function useIsSup() {
    const page = usePage();

    return computed(() => Boolean((page.props.auth as Auth | undefined)?.is_sup));
}
