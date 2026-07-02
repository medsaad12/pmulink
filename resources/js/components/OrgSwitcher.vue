<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Building2, Check, ChevronsUpDown } from 'lucide-vue-next';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
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
    <SidebarMenu v-if="current">
        <SidebarMenuItem>
            <DropdownMenu v-if="canSwitch">
                <DropdownMenuTrigger as-child>
                    <SidebarMenuButton
                        class="data-[state=open]:bg-sidebar-accent"
                    >
                        <Building2 class="size-4 shrink-0" />
                        <span class="truncate">{{ current.name }}</span>
                        <ChevronsUpDown class="ml-auto size-4" />
                    </SidebarMenuButton>
                </DropdownMenuTrigger>
                <DropdownMenuContent
                    class="w-(--reka-dropdown-menu-trigger-width) min-w-56 rounded-lg"
                    align="start"
                    :side-offset="4"
                >
                    <DropdownMenuLabel>Organisation</DropdownMenuLabel>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem
                        v-for="org in available"
                        :key="org.id"
                        class="cursor-pointer gap-2"
                        @select="switchOrganization(org.id)"
                    >
                        <Building2 class="size-4" />
                        <span class="truncate">{{ org.name }}</span>
                        <Check
                            v-if="org.id === current.id"
                            class="ml-auto size-4"
                        />
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>

            <SidebarMenuButton v-else class="pointer-events-none">
                <Building2 class="size-4 shrink-0" />
                <span class="truncate">{{ current.name }}</span>
            </SidebarMenuButton>
        </SidebarMenuItem>
    </SidebarMenu>
</template>
