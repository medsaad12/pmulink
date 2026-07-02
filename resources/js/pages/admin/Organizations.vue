<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Building2, Network, Plus, Trash2, UserPlus } from 'lucide-vue-next';
import { reactive, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';

type RoleOption = { id: number; name: string };

type Member = {
    id: number;
    name: string;
    email: string;
    role_id: number | null;
};

type OrganizationRow = {
    id: number;
    name: string;
    slug: string;
    is_active: boolean;
    members_count: number;
    roles: RoleOption[];
    members: Member[];
};

const props = defineProps<{
    organizations: OrganizationRow[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Tableau de bord', href: dashboard.url() },
            { title: 'Organisations', href: '/admin/organizations' },
        ],
    },
});

const createForm = useForm({ name: '' });

function submitCreate(): void {
    createForm.post('/admin/organizations', {
        preserveScroll: true,
        onSuccess: () => createForm.reset('name'),
    });
}

type AttachState = { email: string; role_id: number | null; processing: boolean };

const attachByOrg = reactive<Record<number, AttachState>>({});

watch(
    () => props.organizations,
    (orgs) => {
        for (const org of orgs) {
            if (!attachByOrg[org.id]) {
                attachByOrg[org.id] = {
                    email: '',
                    role_id: org.roles[0]?.id ?? null,
                    processing: false,
                };
            }
        }
    },
    { immediate: true, deep: true },
);

function attachMember(org: OrganizationRow): void {
    const state = attachByOrg[org.id];
    if (!state || state.role_id === null) {
        return;
    }

    state.processing = true;
    router.post(
        `/admin/organizations/${org.id}/members`,
        { email: state.email, role_id: state.role_id },
        {
            preserveScroll: true,
            onSuccess: () => {
                state.email = '';
            },
            onFinish: () => {
                state.processing = false;
            },
        },
    );
}

function detachMember(org: OrganizationRow, member: Member): void {
    router.delete(`/admin/organizations/${org.id}/members/${member.id}`, {
        preserveScroll: true,
    });
}

function roleName(org: OrganizationRow, roleId: number | null): string {
    if (roleId === null) {
        return '—';
    }
    return org.roles.find((r) => r.id === roleId)?.name ?? '—';
}
</script>

<template>
    <Head title="Organisations" />

    <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
        <Heading
            title="Organisations"
            description="Créer des organisations et gérer leurs membres (réservé au support)."
        />

        <form
            class="flex flex-col gap-3 rounded-xl border border-sidebar-border/70 bg-card p-4 shadow-sm sm:flex-row sm:items-end dark:border-sidebar-border"
            @submit.prevent="submitCreate"
        >
            <div class="grid flex-1 gap-2">
                <Label for="org-name">Nouvelle organisation</Label>
                <Input
                    id="org-name"
                    v-model="createForm.name"
                    placeholder="Ex. Filiale Pharma"
                    required
                />
                <InputError :message="createForm.errors.name" />
            </div>
            <Button type="submit" :disabled="createForm.processing">
                <Plus class="mr-2 size-4" />
                Créer
            </Button>
        </form>

        <div class="flex flex-col gap-4">
            <article
                v-for="org in props.organizations"
                :key="org.id"
                class="rounded-xl border border-sidebar-border/70 bg-card p-4 shadow-sm dark:border-sidebar-border"
            >
                <header class="mb-4 flex items-center gap-3">
                    <span
                        class="flex size-10 items-center justify-center rounded-lg bg-primary/10 text-primary"
                    >
                        <Building2 class="size-5" />
                    </span>
                    <div class="flex-1">
                        <h2 class="font-semibold">{{ org.name }}</h2>
                        <p class="text-xs text-muted-foreground">
                            {{ org.members_count }} membre(s) · {{ org.slug }}
                        </p>
                    </div>
                </header>

                <div class="mb-4 overflow-hidden rounded-lg border border-sidebar-border/50">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-muted/40">
                            <tr>
                                <th class="px-3 py-2 font-medium">Membre</th>
                                <th class="px-3 py-2 font-medium">E-mail</th>
                                <th class="px-3 py-2 font-medium">Rôle</th>
                                <th class="w-[1%] px-3 py-2"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-if="org.members.length === 0"
                                class="border-t border-sidebar-border/40"
                            >
                                <td
                                    colspan="4"
                                    class="px-3 py-6 text-center text-muted-foreground"
                                >
                                    Aucun membre.
                                </td>
                            </tr>
                            <tr
                                v-for="member in org.members"
                                :key="member.id"
                                class="border-t border-sidebar-border/40"
                            >
                                <td class="px-3 py-2 font-medium">{{ member.name }}</td>
                                <td class="px-3 py-2 text-muted-foreground">
                                    {{ member.email }}
                                </td>
                                <td class="px-3 py-2">
                                    {{ roleName(org, member.role_id) }}
                                </td>
                                <td class="px-3 py-2 text-right">
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        class="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                        :aria-label="`Retirer ${member.name}`"
                                        @click="detachMember(org, member)"
                                    >
                                        <Trash2 class="size-4" />
                                    </Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <form
                    class="flex flex-col gap-3 sm:flex-row sm:items-end"
                    @submit.prevent="attachMember(org)"
                >
                    <div class="grid flex-1 gap-2">
                        <Label :for="`attach-email-${org.id}`">
                            Ajouter un membre (par e-mail)
                        </Label>
                        <Input
                            :id="`attach-email-${org.id}`"
                            v-model="attachByOrg[org.id].email"
                            type="email"
                            placeholder="utilisateur@exemple.com"
                            required
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label :for="`attach-role-${org.id}`">Rôle</Label>
                        <select
                            :id="`attach-role-${org.id}`"
                            v-model.number="attachByOrg[org.id].role_id"
                            class="h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                        >
                            <option
                                v-for="role in org.roles"
                                :key="role.id"
                                :value="role.id"
                            >
                                {{ role.name }}
                            </option>
                        </select>
                    </div>
                    <Button
                        type="submit"
                        :disabled="attachByOrg[org.id].processing"
                    >
                        <UserPlus class="mr-2 size-4" />
                        Ajouter
                    </Button>
                </form>
            </article>
        </div>

        <p
            v-if="props.organizations.length === 0"
            class="flex flex-col items-center gap-3 py-12 text-center text-muted-foreground"
        >
            <Network class="size-10 opacity-40" />
            Aucune organisation.
        </p>
    </div>
</template>
