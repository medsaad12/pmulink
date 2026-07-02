<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Building2, LayoutGrid, ListChecks, Users } from 'lucide-vue-next';
import { dashboard } from '@/routes';

const props = defineProps<{
    stats: {
        users: number;
        organizations: number;
        departments: number;
        faitsMarquants: number;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Tableau de bord',
                href: dashboard(),
            },
        ],
    },
});

const cards = [
    {
        key: 'users' as const,
        label: 'Utilisateurs',
        icon: Users,
    },
    {
        key: 'organizations' as const,
        label: 'Organisations',
        icon: Building2,
    },
    {
        key: 'departments' as const,
        label: 'Départements',
        icon: LayoutGrid,
    },
    {
        key: 'faitsMarquants' as const,
        label: 'Sujets',
        icon: ListChecks,
    },
];
</script>

<template>
    <Head title="Tableau de bord" />

    <div
        class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
    >
        <div class="grid auto-rows-min gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div
                v-for="card in cards"
                :key="card.key"
                class="flex items-center gap-4 rounded-xl border border-sidebar-border/70 p-5 dark:border-sidebar-border"
            >
                <div
                    class="flex size-12 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
                >
                    <component :is="card.icon" class="size-6" />
                </div>
                <div>
                    <p class="text-sm text-muted-foreground">{{ card.label }}</p>
                    <p class="text-2xl font-semibold">
                        {{ props.stats[card.key] }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
