<script setup lang="ts">
import UserController from '@/actions/App/Http/Controllers/UserController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import {
    Alert,
    AlertDescription,
    AlertTitle,
} from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { usePermissions } from '@/composables/usePermissions';
import { dashboard } from '@/routes';
import { index } from '@/routes/users';
import type { User, UserMembership } from '@/types/auth';
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { CircleAlert, Pencil, Plus, Trash2, Users } from 'lucide-vue-next';
import { computed, ref } from 'vue';

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type PaginatedUsers = {
    data: User[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
};

type SheetMode = 'create' | 'edit' | null;

type RoleOption = {
    id: number;
    name: string;
};

type DepartmentOption = {
    id: number;
    name: string;
};

type OrganizationFormOption = {
    id: number;
    name: string;
    roles: RoleOption[];
    departments: DepartmentOption[];
};

const props = defineProps<{
    users: PaginatedUsers;
    organizations: OrganizationFormOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Tableau de bord', href: dashboard.url() },
            { title: 'Utilisateurs', href: index.url() },
        ],
    },
});

const page = usePage();
const { can } = usePermissions();
const authUserId = computed(() => page.props.auth.user?.id ?? null);

const deleteTarget = ref<User | null>(null);
const deleteOrganizationId = ref<number | null>(null);

const sheetOpen = ref(false);
const sheetMode = ref<SheetMode>(null);
const editingUser = ref<User | null>(null);
const createOrganizationIds = ref<number[]>([]);
const editOrganizationIds = ref<number[]>([]);

function rolesForOrganizationIds(orgIds: number[]): RoleOption[] {
    if (orgIds.length === 0) {
        return [];
    }

    const organizations = props.organizations.filter((organization) =>
        orgIds.includes(organization.id),
    );

    if (organizations.length === 0) {
        return [];
    }

    let sharedNames = new Set(organizations[0].roles.map((role) => role.name));

    for (const organization of organizations.slice(1)) {
        const names = new Set(organization.roles.map((role) => role.name));
        sharedNames = new Set(
            [...sharedNames].filter((name) => names.has(name)),
        );
    }

    return [...sharedNames]
        .sort((a, b) => a.localeCompare(b, 'fr'))
        .map((name) => {
            const role =
                organizations[0].roles.find(
                    (candidate) => candidate.name === name,
                ) ?? organizations[0].roles[0];

            return { id: role.id, name };
        });
}

type DepartmentWithOrg = DepartmentOption & {
    organizationName: string;
    label: string;
};

function departmentsForOrganizationIds(orgIds: number[]): DepartmentWithOrg[] {
    const organizations = props.organizations.filter((organization) =>
        orgIds.includes(organization.id),
    );

    return organizations.flatMap((organization) =>
        organization.departments.map((department) => ({
            ...department,
            organizationName: organization.name,
            label:
                organizations.length > 1
                    ? `${organization.name} — ${department.name}`
                    : department.name,
        })),
    );
}

const createRoles = computed(() =>
    rolesForOrganizationIds(createOrganizationIds.value),
);
const createDepartments = computed(() =>
    departmentsForOrganizationIds(createOrganizationIds.value),
);

const editRoles = computed(() => rolesForOrganizationIds(editOrganizationIds.value));
const editDepartments = computed(() =>
    departmentsForOrganizationIds(editOrganizationIds.value),
);

function isOrganizationSelected(
    organizationId: number,
    mode: 'create' | 'edit',
): boolean {
    const selected =
        mode === 'create'
            ? createOrganizationIds.value
            : editOrganizationIds.value;

    return selected.includes(organizationId);
}

function toggleOrganization(
    organizationId: number,
    mode: 'create' | 'edit',
    checked: boolean,
): void {
    const target =
        mode === 'create' ? createOrganizationIds : editOrganizationIds;

    if (checked) {
        if (!target.value.includes(organizationId)) {
            target.value = [...target.value, organizationId].sort(
                (a, b) => a - b,
            );
        }

        return;
    }

    target.value = target.value.filter((id) => id !== organizationId);
}

function membershipForOrganization(
    user: User,
    organizationId: number | null,
): UserMembership | null {
    if (organizationId === null) {
        return null;
    }

    return (
        user.memberships?.find((m) => m.organization_id === organizationId) ??
        null
    );
}

function editRoleIdForSelectedOrgs(): number | null {
    if (!editingUser.value || editOrganizationIds.value.length === 0) {
        return null;
    }

    const membershipRoleIds = editOrganizationIds.value
        .map(
            (organizationId) =>
                membershipForOrganization(editingUser.value!, organizationId)
                    ?.role_id ?? null,
        )
        .filter((roleId): roleId is number => roleId !== null);

    if (membershipRoleIds.length === 0) {
        return editRoles.value[0]?.id ?? null;
    }

    const roleNames = new Set(
        membershipRoleIds
            .map((roleId) => {
                for (const organization of props.organizations) {
                    const role = organization.roles.find(
                        (candidate) => candidate.id === roleId,
                    );
                    if (role) {
                        return role.name;
                    }
                }

                return null;
            })
            .filter((name): name is string => name !== null),
    );

    if (roleNames.size === 1) {
        const roleName = [...roleNames][0];
        return (
            editRoles.value.find((role) => role.name === roleName)?.id ?? null
        );
    }

    return editRoles.value[0]?.id ?? membershipRoleIds[0] ?? null;
}

function userHasDepartmentInOrg(user: User, departmentId: number): boolean {
    return user.departments?.some((d) => d.id === departmentId) ?? false;
}

function openCreate(): void {
    sheetMode.value = 'create';
    editingUser.value = null;
    createOrganizationIds.value =
        props.organizations.length > 0 ? [props.organizations[0].id] : [];
    editOrganizationIds.value = [];
    sheetOpen.value = true;
}

function openEdit(user: User): void {
    sheetMode.value = 'edit';
    editingUser.value = user;
    editOrganizationIds.value =
        user.organizations?.map((organization) => organization.id) ??
        user.memberships?.map((membership) => membership.organization_id) ??
        [];
    createOrganizationIds.value = [];
    sheetOpen.value = true;
}

function onSheetOpenChange(open: boolean): void {
    sheetOpen.value = open;
    if (!open) {
        sheetMode.value = null;
        editingUser.value = null;
        createOrganizationIds.value = [];
        editOrganizationIds.value = [];
    }
}

function closeSheetOnSuccess(): void {
    sheetOpen.value = false;
    sheetMode.value = null;
    editingUser.value = null;
    createOrganizationIds.value = [];
    editOrganizationIds.value = [];
}

function requestDelete(user: User): void {
    deleteTarget.value = user;
    deleteOrganizationId.value =
        user.memberships?.[0]?.organization_id ??
        user.organizations?.[0]?.id ??
        props.organizations[0]?.id ??
        null;
}

function closeDelete(open: boolean): void {
    if (!open) {
        deleteTarget.value = null;
        deleteOrganizationId.value = null;
    }
}

function hasFormErrors(errors: Record<string, unknown>): boolean {
    return Object.values(errors).some((v) => {
        if (v == null || v === '') {
            return false;
        }
        if (Array.isArray(v)) {
            return v.some((x) => x != null && x !== '');
        }
        return true;
    });
}
</script>

<template>
    <Head title="Utilisateurs" />

    <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <Heading
                title="Utilisateurs"
                description="Créer, modifier ou supprimer des comptes utilisateur."
            />
            <Button
                v-if="can('users.create')"
                type="button"
                @click="openCreate"
            >
                <Plus class="mr-2 size-4" />
                Nouvel utilisateur
            </Button>
        </div>

        <div
            class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card shadow-sm dark:border-sidebar-border"
        >
            <div class="overflow-x-auto">
                <table class="w-full min-w-[640px] text-left text-sm">
                    <thead
                        class="border-b border-sidebar-border/70 bg-muted/40 dark:border-sidebar-border"
                    >
                        <tr>
                            <th class="px-4 py-3 font-medium">Nom</th>
                            <th class="px-4 py-3 font-medium">E-mail</th>
                            <th class="px-4 py-3 font-medium">Organisation</th>
                            <th class="px-4 py-3 font-medium">Rôle</th>
                            <th class="px-4 py-3 font-medium">Département</th>
                            <th
                                class="w-[1%] whitespace-nowrap px-4 py-3 text-right font-medium"
                            >
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="props.users.data.length === 0">
                            <td
                                colspan="6"
                                class="px-4 py-12 text-center text-muted-foreground"
                            >
                                <Users
                                    class="mx-auto mb-3 size-10 opacity-40"
                                    aria-hidden="true"
                                />
                                Aucun utilisateur pour le moment.
                            </td>
                        </tr>
                        <tr
                            v-for="user in props.users.data"
                            :key="user.id"
                            class="border-b border-sidebar-border/50 last:border-0 dark:border-sidebar-border/60"
                        >
                            <td class="px-4 py-3 font-medium">
                                {{ user.name }}
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ user.email }}
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                <div
                                    v-if="user.organizations?.length"
                                    class="flex flex-wrap gap-1"
                                >
                                    <Badge
                                        v-for="o in user.organizations"
                                        :key="o.id"
                                        variant="secondary"
                                    >
                                        {{ o.name }}
                                    </Badge>
                                </div>
                                <span v-else class="italic">—</span>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                <span
                                    v-if="user.memberships?.length"
                                    class="flex flex-wrap gap-1"
                                >
                                    <Badge
                                        v-for="membership in user.memberships"
                                        :key="`${user.id}-${membership.organization_id}`"
                                        variant="outline"
                                    >
                                        {{ membership.role_name ?? '—' }}
                                    </Badge>
                                </span>
                                <span v-else class="italic">—</span>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                <span v-if="user.departments?.length">{{
                                    user.departments.map((d) => d.name).join(', ')
                                }}</span>
                                <span v-else class="italic">Global</span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div
                                    class="flex justify-end gap-1 sm:gap-2"
                                >
                                    <Button
                                        v-if="can('users.edit')"
                                        variant="ghost"
                                        size="icon"
                                        type="button"
                                        :aria-label="`Modifier ${user.name}`"
                                        @click="openEdit(user)"
                                    >
                                        <Pencil class="size-4" />
                                    </Button>
                                    <Button
                                        v-if="
                                            can('users.delete') &&
                                            user.id !== authUserId
                                        "
                                        variant="ghost"
                                        size="icon"
                                        class="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                        :aria-label="`Supprimer ${user.name}`"
                                        @click="requestDelete(user)"
                                    >
                                        <Trash2 class="size-4" />
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <nav
            v-if="props.users.last_page > 1"
            class="flex flex-wrap items-center justify-center gap-1"
            aria-label="Pagination des pages"
        >
            <template v-for="link in props.users.links" :key="link.label">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    preserve-scroll
                    class="inline-flex min-w-9 items-center justify-center rounded-md px-3 py-1.5 text-sm transition-colors"
                    :class="
                        link.active
                            ? 'bg-primary text-primary-foreground'
                            : 'text-foreground hover:bg-muted'
                    "
                    v-html="link.label"
                />
                <span
                    v-else
                    class="inline-flex min-w-9 items-center justify-center rounded-md px-3 py-1.5 text-sm text-muted-foreground opacity-50"
                    v-html="link.label"
                />
            </template>
        </nav>

        <Sheet :open="sheetOpen" @update:open="onSheetOpenChange">
            <SheetContent
                side="right"
                class="flex h-full w-[40vw] min-w-[280px] max-w-[40vw] flex-col gap-0 border-l p-0 sm:max-w-[40vw]"
            >
                <SheetHeader
                    class="shrink-0 space-y-1 border-b border-sidebar-border/70 px-6 py-4 text-left"
                >
                    <SheetTitle>
                        {{
                            sheetMode === 'create'
                                ? 'Nouvel utilisateur'
                                : 'Modifier l’utilisateur'
                        }}
                    </SheetTitle>
                    <SheetDescription>
                        {{
                            sheetMode === 'create'
                                ? 'Renseignez l’organisation, le nom, l’e-mail, le rôle et le mot de passe. Le département est facultatif (utilisateur général).'
                                : 'Mettez à jour l’organisation, les informations et le rôle. Le département est facultatif (utilisateur général). Laissez le mot de passe vide pour ne pas le modifier.'
                        }}
                    </SheetDescription>
                </SheetHeader>

                <div class="min-h-0 flex-1 overflow-y-auto px-6 py-4">
                    <Form
                        v-if="sheetMode === 'create'"
                        v-bind="UserController.store.form()"
                        class="flex flex-col gap-4"
                        reset-on-success
                        @success="closeSheetOnSuccess"
                        v-slot="{ errors, processing }"
                    >
                        <Alert
                            v-if="hasFormErrors(errors)"
                            variant="destructive"
                            class="border-destructive/40"
                        >
                            <CircleAlert class="size-4" />
                            <AlertTitle>Formulaire incomplet ou invalide</AlertTitle>
                            <AlertDescription>
                                Vérifiez les messages sous chaque champ, puis
                                réessayez.
                            </AlertDescription>
                        </Alert>
                        <div class="grid gap-2">
                            <Label>Organisations</Label>
                            <p class="text-xs text-muted-foreground">
                                Sélectionnez une ou plusieurs organisations.
                            </p>
                            <div
                                v-if="props.organizations.length === 0"
                                class="rounded-md border border-dashed border-input p-3 text-sm text-muted-foreground"
                            >
                                Aucune organisation disponible.
                            </div>
                            <div
                                v-else
                                class="max-h-40 space-y-2 overflow-y-auto rounded-md border border-input p-3"
                                :class="
                                    errors.organization_ids
                                        ? 'border-destructive'
                                        : ''
                                "
                            >
                                <label
                                    v-for="org in props.organizations"
                                    :key="`create-org-${org.id}`"
                                    class="flex cursor-pointer items-center gap-2 text-sm"
                                >
                                    <input
                                        type="checkbox"
                                        name="organization_ids[]"
                                        :value="org.id"
                                        class="size-4 rounded border-input"
                                        :checked="
                                            isOrganizationSelected(
                                                org.id,
                                                'create',
                                            )
                                        "
                                        @change="
                                            toggleOrganization(
                                                org.id,
                                                'create',
                                                (
                                                    $event.target as HTMLInputElement
                                                ).checked,
                                            )
                                        "
                                    />
                                    {{ org.name }}
                                </label>
                            </div>
                            <InputError :message="errors.organization_ids" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="user-create-name">Nom</Label>
                            <Input
                                id="user-create-name"
                                name="name"
                                required
                                autocomplete="name"
                                placeholder="Nom complet"
                                :class="
                                    errors.name
                                        ? 'border-destructive focus-visible:ring-destructive'
                                        : ''
                                "
                                :aria-invalid="errors.name ? 'true' : undefined"
                            />
                            <InputError :message="errors.name" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="user-create-email">E-mail</Label>
                            <Input
                                id="user-create-email"
                                type="email"
                                name="email"
                                required
                                autocomplete="username"
                                placeholder="adresse@exemple.com"
                                :class="
                                    errors.email
                                        ? 'border-destructive focus-visible:ring-destructive'
                                        : ''
                                "
                                :aria-invalid="errors.email ? 'true' : undefined"
                            />
                            <InputError :message="errors.email" />
                        </div>
                        <div class="grid gap-2">
                            <Label>Départements</Label>
                            <p class="text-xs text-muted-foreground">
                                Aucune case cochée = utilisateur global (propriétaire).
                            </p>
                            <div
                                v-if="createDepartments.length === 0"
                                class="rounded-md border border-dashed border-input p-3 text-sm text-muted-foreground"
                            >
                                Aucun département pour les organisations sélectionnées.
                            </div>
                            <div
                                v-else
                                class="max-h-40 space-y-2 overflow-y-auto rounded-md border border-input p-3"
                                :class="
                                    errors.department_ids
                                        ? 'border-destructive'
                                        : ''
                                "
                            >
                                <label
                                    v-for="d in createDepartments"
                                    :key="`create-dept-${d.id}`"
                                    class="flex cursor-pointer items-center gap-2 text-sm"
                                >
                                    <input
                                        type="checkbox"
                                        name="department_ids[]"
                                        :value="d.id"
                                        class="size-4 rounded border-input"
                                    />
                                    {{ d.label }}
                                </label>
                            </div>
                            <InputError :message="errors.department_ids" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="user-create-role_id">Rôle</Label>
                            <select
                                id="user-create-role_id"
                                :key="`create-role-${createOrganizationIds.join('-')}`"
                                name="role_id"
                                required
                                class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none aria-invalid:border-destructive"
                                :class="
                                    errors.role_id
                                        ? 'border-destructive focus-visible:ring-destructive'
                                        : ''
                                "
                                :aria-invalid="errors.role_id ? 'true' : undefined"
                                :disabled="createRoles.length === 0"
                            >
                                <option value="" disabled selected hidden>
                                    Choisir un rôle…
                                </option>
                                <option
                                    v-for="r in createRoles"
                                    :key="r.id"
                                    :value="r.id"
                                >
                                    {{ r.name }}
                                </option>
                            </select>
                            <InputError :message="errors.role_id" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="user-create-password">Mot de passe</Label>
                            <Input
                                id="user-create-password"
                                type="password"
                                name="password"
                                required
                                autocomplete="new-password"
                                :class="
                                    errors.password
                                        ? 'border-destructive focus-visible:ring-destructive'
                                        : ''
                                "
                                :aria-invalid="
                                    errors.password ? 'true' : undefined
                                "
                            />
                            <InputError :message="errors.password" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="user-create-password-confirmation"
                                >Confirmer le mot de passe</Label
                            >
                            <Input
                                id="user-create-password-confirmation"
                                type="password"
                                name="password_confirmation"
                                required
                                autocomplete="new-password"
                                :class="
                                    errors.password_confirmation
                                        ? 'border-destructive focus-visible:ring-destructive'
                                        : ''
                                "
                                :aria-invalid="
                                    errors.password_confirmation
                                        ? 'true'
                                        : undefined
                                "
                            />
                            <InputError
                                :message="errors.password_confirmation"
                            />
                        </div>
                        <SheetFooter
                            class="mt-2 flex-row justify-end gap-2 border-0 p-0 sm:justify-end"
                        >
                            <Button
                                type="button"
                                variant="secondary"
                                @click="onSheetOpenChange(false)"
                            >
                                Annuler
                            </Button>
                            <Button type="submit" :disabled="processing">
                                Créer
                            </Button>
                        </SheetFooter>
                    </Form>

                    <Form
                        v-else-if="sheetMode === 'edit' && editingUser"
                        :key="editingUser.id"
                        v-bind="UserController.update.form(editingUser)"
                        class="flex flex-col gap-4"
                        @success="closeSheetOnSuccess"
                        v-slot="{ errors, processing }"
                    >
                        <Alert
                            v-if="hasFormErrors(errors)"
                            variant="destructive"
                            class="border-destructive/40"
                        >
                            <CircleAlert class="size-4" />
                            <AlertTitle>Formulaire incomplet ou invalide</AlertTitle>
                            <AlertDescription>
                                Vérifiez les messages sous chaque champ, puis
                                réessayez.
                            </AlertDescription>
                        </Alert>
                        <div class="grid gap-2">
                            <Label>Organisations</Label>
                            <p class="text-xs text-muted-foreground">
                                Sélectionnez une ou plusieurs organisations.
                            </p>
                            <div
                                class="max-h-40 space-y-2 overflow-y-auto rounded-md border border-input p-3"
                                :class="
                                    errors.organization_ids
                                        ? 'border-destructive'
                                        : ''
                                "
                            >
                                <label
                                    v-for="org in props.organizations"
                                    :key="`edit-org-${editingUser.id}-${org.id}`"
                                    class="flex cursor-pointer items-center gap-2 text-sm"
                                >
                                    <input
                                        type="checkbox"
                                        name="organization_ids[]"
                                        :value="org.id"
                                        class="size-4 rounded border-input"
                                        :checked="
                                            isOrganizationSelected(
                                                org.id,
                                                'edit',
                                            )
                                        "
                                        @change="
                                            toggleOrganization(
                                                org.id,
                                                'edit',
                                                (
                                                    $event.target as HTMLInputElement
                                                ).checked,
                                            )
                                        "
                                    />
                                    {{ org.name }}
                                </label>
                            </div>
                            <InputError :message="errors.organization_ids" />
                        </div>
                        <div class="grid gap-2">
                            <Label :for="`user-edit-${editingUser.id}-name`"
                                >Nom</Label
                            >
                            <Input
                                :id="`user-edit-${editingUser.id}-name`"
                                name="name"
                                required
                                autocomplete="name"
                                :default-value="editingUser.name"
                                placeholder="Nom complet"
                                :class="
                                    errors.name
                                        ? 'border-destructive focus-visible:ring-destructive'
                                        : ''
                                "
                                :aria-invalid="errors.name ? 'true' : undefined"
                            />
                            <InputError :message="errors.name" />
                        </div>
                        <div class="grid gap-2">
                            <Label :for="`user-edit-${editingUser.id}-email`"
                                >E-mail</Label
                            >
                            <Input
                                :id="`user-edit-${editingUser.id}-email`"
                                type="email"
                                name="email"
                                required
                                autocomplete="username"
                                :default-value="editingUser.email"
                                placeholder="adresse@exemple.com"
                                :class="
                                    errors.email
                                        ? 'border-destructive focus-visible:ring-destructive'
                                        : ''
                                "
                                :aria-invalid="errors.email ? 'true' : undefined"
                            />
                            <InputError :message="errors.email" />
                        </div>
                        <div class="grid gap-2">
                            <Label>Départements</Label>
                            <p class="text-xs text-muted-foreground">
                                Aucune case cochée = utilisateur global (propriétaire).
                            </p>
                            <div
                                v-if="editDepartments.length === 0"
                                class="rounded-md border border-dashed border-input p-3 text-sm text-muted-foreground"
                            >
                                Aucun département pour les organisations sélectionnées.
                            </div>
                            <div
                                v-else
                                class="max-h-40 space-y-2 overflow-y-auto rounded-md border border-input p-3"
                                :class="
                                    errors.department_ids
                                        ? 'border-destructive'
                                        : ''
                                "
                            >
                                <label
                                    v-for="d in editDepartments"
                                    :key="`edit-dept-${editingUser.id}-${d.id}`"
                                    class="flex cursor-pointer items-center gap-2 text-sm"
                                >
                                    <input
                                        type="checkbox"
                                        name="department_ids[]"
                                        :value="d.id"
                                        class="size-4 rounded border-input"
                                        :checked="
                                            userHasDepartmentInOrg(
                                                editingUser,
                                                d.id,
                                            )
                                        "
                                    />
                                    {{ d.label }}
                                </label>
                            </div>
                            <InputError :message="errors.department_ids" />
                        </div>
                        <div class="grid gap-2">
                            <Label :for="`user-edit-${editingUser.id}-role_id`"
                                >Rôle</Label
                            >
                            <select
                                :id="`user-edit-${editingUser.id}-role_id`"
                                :key="`user-role-${editingUser.id}-${editOrganizationIds.join('-')}`"
                                name="role_id"
                                required
                                class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                                :class="
                                    errors.role_id
                                        ? 'border-destructive focus-visible:ring-destructive'
                                        : ''
                                "
                                :aria-invalid="errors.role_id ? 'true' : undefined"
                                :disabled="editRoles.length === 0"
                            >
                                <option
                                    v-for="r in editRoles"
                                    :key="r.id"
                                    :value="r.id"
                                    :selected="
                                        editRoleIdForSelectedOrgs() === r.id
                                    "
                                >
                                    {{ r.name }}
                                </option>
                            </select>
                            <InputError :message="errors.role_id" />
                        </div>
                        <div class="grid gap-2">
                            <Label :for="`user-edit-${editingUser.id}-password`"
                                >Nouveau mot de passe (optionnel)</Label
                            >
                            <Input
                                :id="`user-edit-${editingUser.id}-password`"
                                type="password"
                                name="password"
                                autocomplete="new-password"
                                :class="
                                    errors.password
                                        ? 'border-destructive focus-visible:ring-destructive'
                                        : ''
                                "
                                :aria-invalid="
                                    errors.password ? 'true' : undefined
                                "
                            />
                            <InputError :message="errors.password" />
                        </div>
                        <div class="grid gap-2">
                            <Label
                                :for="`user-edit-${editingUser.id}-password-confirmation`"
                                >Confirmer le mot de passe</Label
                            >
                            <Input
                                :id="`user-edit-${editingUser.id}-password-confirmation`"
                                type="password"
                                name="password_confirmation"
                                autocomplete="new-password"
                                :class="
                                    errors.password_confirmation
                                        ? 'border-destructive focus-visible:ring-destructive'
                                        : ''
                                "
                                :aria-invalid="
                                    errors.password_confirmation
                                        ? 'true'
                                        : undefined
                                "
                            />
                            <InputError
                                :message="errors.password_confirmation"
                            />
                        </div>
                        <SheetFooter
                            class="mt-2 flex-row justify-end gap-2 border-0 p-0 sm:justify-end"
                        >
                            <Button
                                type="button"
                                variant="secondary"
                                @click="onSheetOpenChange(false)"
                            >
                                Annuler
                            </Button>
                            <Button type="submit" :disabled="processing">
                                Enregistrer
                            </Button>
                        </SheetFooter>
                    </Form>
                </div>
            </SheetContent>
        </Sheet>

        <Dialog :open="deleteTarget !== null" @update:open="closeDelete">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Retirer cet utilisateur ?</DialogTitle>
                    <DialogDescription>
                        <template
                            v-if="
                                deleteTarget?.organizations &&
                                deleteTarget.organizations.length > 1
                            "
                        >
                            Choisissez l’organisation dont
                            <strong>{{ deleteTarget?.name }}</strong>
                            doit être retiré. S’il n’appartient plus à aucune
                            organisation, son compte sera supprimé.
                        </template>
                        <template v-else>
                            Cette action retirera
                            <strong>{{ deleteTarget?.name }}</strong>
                            de l’organisation. S’il n’appartient plus à aucune
                            organisation, son compte sera supprimé.
                        </template>
                    </DialogDescription>
                </DialogHeader>
                <Form
                    v-if="deleteTarget"
                    v-bind="UserController.destroy.form(deleteTarget)"
                    class="contents"
                    @success="deleteTarget = null"
                    v-slot="{ processing }"
                >
                    <div
                        v-if="
                            deleteTarget.organizations &&
                            deleteTarget.organizations.length > 1
                        "
                        class="grid gap-2 py-2"
                    >
                        <Label for="delete-organization_id">Organisation</Label>
                        <select
                            id="delete-organization_id"
                            name="organization_id"
                            required
                            v-model.number="deleteOrganizationId"
                            class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                        >
                            <option
                                v-for="org in deleteTarget.organizations"
                                :key="org.id"
                                :value="org.id"
                            >
                                {{ org.name }}
                            </option>
                        </select>
                    </div>
                    <input
                        v-else
                        type="hidden"
                        name="organization_id"
                        :value="deleteOrganizationId ?? ''"
                    />
                    <DialogFooter class="gap-2">
                        <DialogClose as-child>
                            <Button type="button" variant="secondary">
                                Annuler
                            </Button>
                        </DialogClose>
                        <Button
                            type="submit"
                            variant="destructive"
                            :disabled="processing"
                        >
                            Supprimer
                        </Button>
                    </DialogFooter>
                </Form>
            </DialogContent>
        </Dialog>
    </div>
</template>
