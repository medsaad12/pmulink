import type { ComputedRef, Ref } from 'vue';
import { computed, ref } from 'vue';
import type { Appearance, ResolvedAppearance } from '@/types';

export type { Appearance, ResolvedAppearance };

const LIGHT_APPEARANCE = 'light' as const;

export type UseAppearanceReturn = {
    appearance: Ref<Appearance>;
    resolvedAppearance: ComputedRef<ResolvedAppearance>;
    updateAppearance: (value: Appearance) => void;
};

export function updateTheme(): void {
    if (typeof window === 'undefined') {
        return;
    }

    document.documentElement.classList.remove('dark');
}

const setCookie = (name: string, value: string, days = 365) => {
    if (typeof document === 'undefined') {
        return;
    }

    const maxAge = days * 24 * 60 * 60;

    document.cookie = `${name}=${value};path=/;max-age=${maxAge};SameSite=Lax`;
};

export function initializeTheme(): void {
    if (typeof window === 'undefined') {
        return;
    }

    localStorage.setItem('appearance', LIGHT_APPEARANCE);
    setCookie('appearance', LIGHT_APPEARANCE);
    updateTheme();
}

const appearance = ref<Appearance>(LIGHT_APPEARANCE);

export function useAppearance(): UseAppearanceReturn {
    const resolvedAppearance = computed<ResolvedAppearance>(() => {
        return LIGHT_APPEARANCE;
    });

    function updateAppearance(_value: Appearance) {
        appearance.value = LIGHT_APPEARANCE;

        localStorage.setItem('appearance', LIGHT_APPEARANCE);

        setCookie('appearance', LIGHT_APPEARANCE);

        updateTheme();
    }

    return {
        appearance,
        resolvedAppearance,
        updateAppearance,
    };
}
