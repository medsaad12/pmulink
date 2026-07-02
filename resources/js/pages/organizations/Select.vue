<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Building2, ChevronRight } from 'lucide-vue-next';
import AppLogo from '@/components/AppLogo.vue';
import type { OrganizationSummary } from '@/types/auth';

const props = defineProps<{
    organizations: OrganizationSummary[];
}>();

function choose(organizationId: number): void {
    router.post('/organizations/switch', { organization_id: organizationId });
}
</script>

<template>
    <Head title="Choisir une organisation" />

    <div
        class="flex min-h-svh flex-col items-center justify-center gap-8 bg-background p-6"
    >
        <div class="flex flex-col items-center gap-2">
            <AppLogo />
            <h1 class="mt-4 text-xl font-semibold">Choisir une organisation</h1>
            <p class="text-sm text-muted-foreground">
                Vous appartenez à plusieurs organisations. Sélectionnez celle
                dans laquelle vous souhaitez travailler.
            </p>
        </div>

        <div class="flex w-full max-w-md flex-col gap-3">
            <button
                v-for="org in props.organizations"
                :key="org.id"
                type="button"
                class="group flex items-center gap-3 rounded-xl border border-sidebar-border/70 bg-card px-5 py-4 text-left shadow-sm transition-colors hover:border-primary hover:bg-muted/50 dark:border-sidebar-border"
                @click="choose(org.id)"
            >
                <span
                    class="flex size-10 items-center justify-center rounded-lg bg-primary/10 text-primary"
                >
                    <Building2 class="size-5" />
                </span>
                <span class="flex-1 font-medium">{{ org.name }}</span>
                <ChevronRight
                    class="size-5 text-muted-foreground transition-transform group-hover:translate-x-0.5"
                />
            </button>
        </div>
    </div>
</template>
