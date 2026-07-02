import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { Tenant } from '@/types/auth';

export function useTenant() {
    const page = usePage();

    const tenant = computed<Tenant>(
        () =>
            (page.props.tenant as Tenant | undefined) ?? {
                current_id: null,
                current: null,
                available: [],
            },
    );

    const current = computed(() => tenant.value.current);
    const available = computed(() => tenant.value.available);
    const canSwitch = computed(() => tenant.value.available.length > 1);

    return { tenant, current, available, canSwitch };
}
