<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Building2, LayoutGrid, Network, /* Shield, */ Users } from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import OrgSwitcher from '@/components/OrgSwitcher.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useIsSup } from '@/composables/useIsSup';
import { usePermissions } from '@/composables/usePermissions';
import { useWeekRange } from '@/composables/useWeekRange';
import { dashboard, whiteboard } from '@/routes';
import { index as departmentsIndex } from '@/routes/departments';
// import { index as rolesIndex } from '@/routes/roles';
import { index as usersIndex } from '@/routes/users';
import type { NavItem } from '@/types';

const { can } = usePermissions();
const { weekRangeLabel } = useWeekRange();
const isSup = useIsSup();

const mainNavItems = computed<NavItem[]>(() => {
    const items: NavItem[] = [];

    if (isSup.value) {
        items.push({
            title: 'Tableau de bord',
            href: dashboard(),
            icon: LayoutGrid,
        });
        items.push({
            title: 'Organisations',
            href: '/admin/organizations',
            icon: Network,
        });
    }

    if (can('users.view')) {
        items.push({
            title: 'Utilisateurs',
            href: usersIndex(),
            icon: Users,
        });
    }

    // if (can('roles.view')) {
    //     items.push({
    //         title: 'Rôles',
    //         href: rolesIndex(),
    //         icon: Shield,
    //     });
    // }

    if (can('departments.view')) {
        items.push({
            title: 'Départements',
            href: departmentsIndex(),
            icon: Building2,
        });
    }

    return items;
});
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton
                        size="lg"
                        as-child
                        class="bg-transparent hover:bg-transparent active:bg-transparent data-[active=true]:bg-transparent data-[state=open]:hover:bg-transparent"
                    >
                        <Link
                            :href="whiteboard()"
                            class="flex min-w-0 flex-col items-center gap-1 group-data-[collapsible=icon]:gap-0"
                        >
                            <AppLogo />
                             
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <div class="px-2 group-data-[collapsible=icon]:hidden">
                <OrgSwitcher />
            </div>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
