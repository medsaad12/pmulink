<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Building2, Check, ChevronDown } from 'lucide-vue-next';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useTenant } from '@/composables/useTenant';

const { current, available, canSwitch } = useTenant();

function switchOrganization(organizationId: number): void {
    if (organizationId === current.value?.id) {
        return;
    }

    router.post(
        '/organizations/switch',
        { organization_id: organizationId },
        { preserveScroll: true },
    );
}
</script>

<template>
    <DropdownMenu v-if="canSwitch && current">
        <DropdownMenuTrigger as-child>
            <button
                type="button"
                :aria-label="`Organisation active : ${current.name}. Changer d'organisation`"
                class="inline-flex h-7 max-w-[10rem] shrink-0 items-center justify-center gap-1.5 rounded-md border border-border bg-background/90 px-2 text-xs font-semibold text-foreground shadow-sm backdrop-blur-sm transition hover:bg-muted/60 focus-visible:ring-2 focus-visible:ring-primary/40 focus-visible:ring-offset-2 focus-visible:outline-none"
            >
                <Building2 class="size-3.5 shrink-0" :stroke-width="2.25" aria-hidden="true" />
                <span class="truncate">{{ current.name }}</span>
                <ChevronDown class="size-3.5 shrink-0 opacity-70" aria-hidden="true" />
            </button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" :side-offset="6" class="min-w-40 p-1">
            <DropdownMenuItem
                v-for="org in available"
                :key="org.id"
                class="cursor-pointer gap-1.5 px-2 py-1 text-xs"
                @select="switchOrganization(org.id)"
            >
                <Building2 class="size-3.5" aria-hidden="true" />
                <span class="truncate">{{ org.name }}</span>
                <Check
                    v-if="org.id === current.id"
                    class="ml-auto size-3.5"
                    aria-hidden="true"
                />
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
