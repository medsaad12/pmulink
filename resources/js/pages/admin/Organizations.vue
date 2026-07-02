<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    Building2,
    Check,
    Network,
    Pencil,
    Plus,
    Search,
    Trash2,
    UserPlus,
    Users,
    X,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
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
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { dashboard } from '@/routes';

type RoleOption = { id: number; name: string };

type Department = { id: number; name: string };

type Member = {
    id: number;
    name: string;
    email: string;
    role_id: number | null;
};

type UserOption = {
    id: number;
    name: string;
    email: string;
};

type OrganizationRow = {
    id: number;
    name: string;
    slug: string;
    is_active: boolean;
    members_count: number;
    roles: RoleOption[];
    members: Member[];
    departments: Department[];
};

const props = defineProps<{
    organizations: OrganizationRow[];
    users: UserOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Tableau de bord', href: dashboard.url() },
            { title: 'Organisations', href: '/admin/organizations' },
        ],
    },
});

const baseUrl = '/admin/organizations';

/* ---------------------------------------------------------------- Create */

const createOpen = ref(false);
const createForm = useForm({ name: '' });

function openCreate(): void {
    createForm.reset();
    createForm.clearErrors();
    createOpen.value = true;
}

function submitCreate(): void {
    createForm.post(baseUrl, {
        preserveScroll: true,
        onSuccess: () => {
            createForm.reset('name');
            createOpen.value = false;
        },
    });
}

/* ------------------------------------------------------------------ Edit */

const editTarget = ref<OrganizationRow | null>(null);
const editForm = useForm({ name: '', is_active: true });

function openEdit(org: OrganizationRow): void {
    editTarget.value = org;
    editForm.clearErrors();
    editForm.name = org.name;
    editForm.is_active = org.is_active;
}

function closeEdit(open: boolean): void {
    if (!open) {
        editTarget.value = null;
    }
}

function submitEdit(): void {
    if (!editTarget.value) {
        return;
    }
    editForm.put(`${baseUrl}/${editTarget.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            editTarget.value = null;
        },
    });
}

/* ---------------------------------------------------------------- Delete */

const deleteTarget = ref<OrganizationRow | null>(null);

function closeDelete(open: boolean): void {
    if (!open) {
        deleteTarget.value = null;
    }
}

function confirmDelete(): void {
    if (!deleteTarget.value) {
        return;
    }
    router.delete(`${baseUrl}/${deleteTarget.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            deleteTarget.value = null;
        },
    });
}

/* --------------------------------------------------------------- Details */

const detailsOrgId = ref<number | null>(null);

const detailsOrg = computed<OrganizationRow | null>(() =>
    detailsOrgId.value === null
        ? null
        : (props.organizations.find((o) => o.id === detailsOrgId.value) ?? null),
);

const memberSearch = ref('');
const selectedUserIds = ref<number[]>([]);
const addRoleId = ref<number | null>(null);
const addProcessing = ref(false);

function openDetails(org: OrganizationRow): void {
    detailsOrgId.value = org.id;
    memberSearch.value = '';
    selectedUserIds.value = [];
    addRoleId.value = org.roles[0]?.id ?? null;
}

function onDetailsOpenChange(open: boolean): void {
    if (!open) {
        detailsOrgId.value = null;
        selectedUserIds.value = [];
        memberSearch.value = '';
    }
}

const memberIds = computed<Set<number>>(
    () => new Set((detailsOrg.value?.members ?? []).map((m) => m.id)),
);

const availableUsers = computed<UserOption[]>(() => {
    const q = memberSearch.value.trim().toLowerCase();
    return props.users
        .filter((u) => !memberIds.value.has(u.id))
        .filter(
            (u) =>
                q === '' ||
                u.name.toLowerCase().includes(q) ||
                u.email.toLowerCase().includes(q),
        );
});

function toggleUser(userId: number): void {
    const i = selectedUserIds.value.indexOf(userId);
    if (i === -1) {
        selectedUserIds.value.push(userId);
    } else {
        selectedUserIds.value.splice(i, 1);
    }
}

function addMembers(): void {
    const org = detailsOrg.value;
    if (!org || addRoleId.value === null || selectedUserIds.value.length === 0) {
        return;
    }

    addProcessing.value = true;
    router.post(
        `${baseUrl}/${org.id}/members`,
        { user_ids: selectedUserIds.value, role_id: addRoleId.value },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                selectedUserIds.value = [];
                memberSearch.value = '';
            },
            onFinish: () => {
                addProcessing.value = false;
            },
        },
    );
}

function changeMemberRole(
    org: OrganizationRow,
    member: Member,
    roleId: number,
): void {
    if (roleId === member.role_id) {
        return;
    }
    router.put(
        `${baseUrl}/${org.id}/members/${member.id}`,
        { role_id: roleId },
        { preserveScroll: true, preserveState: true },
    );
}

function detachMember(org: OrganizationRow, member: Member): void {
    router.delete(`${baseUrl}/${org.id}/members/${member.id}`, {
        preserveScroll: true,
        preserveState: true,
    });
}

/* ---------------------------------------------------------- Departments */

const departmentsOrgId = ref<number | null>(null);

const departmentsOrg = computed<OrganizationRow | null>(() =>
    departmentsOrgId.value === null
        ? null
        : (props.organizations.find((o) => o.id === departmentsOrgId.value) ??
          null),
);

const newDepartmentName = ref('');
const departmentProcessing = ref(false);
const editingDepartmentId = ref<number | null>(null);
const editingDepartmentName = ref('');

function resetDepartmentState(): void {
    newDepartmentName.value = '';
    editingDepartmentId.value = null;
    editingDepartmentName.value = '';
    departmentProcessing.value = false;
}

function openDepartments(org: OrganizationRow): void {
    departmentsOrgId.value = org.id;
    resetDepartmentState();
}

function onDepartmentsOpenChange(open: boolean): void {
    if (!open) {
        departmentsOrgId.value = null;
        resetDepartmentState();
    }
}

function addDepartment(org: OrganizationRow): void {
    const name = newDepartmentName.value.trim();
    if (name === '') {
        return;
    }

    departmentProcessing.value = true;
    router.post(
        `${baseUrl}/${org.id}/departments`,
        { name },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                newDepartmentName.value = '';
            },
            onFinish: () => {
                departmentProcessing.value = false;
            },
        },
    );
}

function startEditDepartment(department: Department): void {
    editingDepartmentId.value = department.id;
    editingDepartmentName.value = department.name;
}

function cancelEditDepartment(): void {
    editingDepartmentId.value = null;
    editingDepartmentName.value = '';
}

function saveDepartment(org: OrganizationRow): void {
    const id = editingDepartmentId.value;
    const name = editingDepartmentName.value.trim();
    if (id === null || name === '') {
        return;
    }

    departmentProcessing.value = true;
    router.put(
        `${baseUrl}/${org.id}/departments/${id}`,
        { name },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                cancelEditDepartment();
            },
            onFinish: () => {
                departmentProcessing.value = false;
            },
        },
    );
}

function deleteDepartment(org: OrganizationRow, department: Department): void {
    router.delete(`${baseUrl}/${org.id}/departments/${department.id}`, {
        preserveScroll: true,
        preserveState: true,
    });
}
</script>

<template>
    <Head title="Organisations" />

    <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <Heading
                title="Organisations"
                description="Créer, modifier ou supprimer des organisations et gérer leurs membres (réservé au support)."
            />
            <Button type="button" @click="openCreate">
                <Plus class="mr-2 size-4" />
                Nouvelle organisation
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
                            <th class="px-4 py-3 font-medium">Organisation</th>
                            <th class="px-4 py-3 font-medium">Identifiant</th>
                            <th class="px-4 py-3 font-medium">Membres</th>
                            <th
                                class="w-[1%] whitespace-nowrap px-4 py-3 text-right font-medium"
                            >
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="props.organizations.length === 0">
                            <td
                                colspan="4"
                                class="px-4 py-12 text-center text-muted-foreground"
                            >
                                <Network
                                    class="mx-auto mb-3 size-10 opacity-40"
                                    aria-hidden="true"
                                />
                                Aucune organisation pour le moment.
                            </td>
                        </tr>
                        <tr
                            v-for="org in props.organizations"
                            :key="org.id"
                            class="border-b border-sidebar-border/50 last:border-0 dark:border-sidebar-border/60"
                        >
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <span
                                        class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
                                    >
                                        <Building2 class="size-4.5" />
                                    </span>
                                    <span class="font-medium">{{ org.name }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ org.slug }}
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ org.members_count }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex justify-end gap-1 sm:gap-2">
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        type="button"
                                        :aria-label="`Membres de ${org.name}`"
                                        @click="openDetails(org)"
                                    >
                                        <Users class="size-4" />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        type="button"
                                        :aria-label="`Départements de ${org.name}`"
                                        @click="openDepartments(org)"
                                    >
                                        <Building2 class="size-4" />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        type="button"
                                        :aria-label="`Modifier ${org.name}`"
                                        @click="openEdit(org)"
                                    >
                                        <Pencil class="size-4" />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        type="button"
                                        class="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                        :aria-label="`Supprimer ${org.name}`"
                                        @click="deleteTarget = org"
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

        <!-- Create dialog -->
        <Dialog :open="createOpen" @update:open="createOpen = $event">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Nouvelle organisation</DialogTitle>
                    <DialogDescription>
                        Une organisation est créée avec ses rôles par défaut
                        (Administrateur et Utilisateur).
                    </DialogDescription>
                </DialogHeader>
                <form class="flex flex-col gap-4" @submit.prevent="submitCreate">
                    <div class="grid gap-2">
                        <Label for="org-create-name">Nom</Label>
                        <Input
                            id="org-create-name"
                            v-model="createForm.name"
                            placeholder="Ex. Filiale Pharma"
                            required
                        />
                        <InputError :message="createForm.errors.name" />
                    </div>
                    <DialogFooter class="gap-2">
                        <DialogClose as-child>
                            <Button type="button" variant="secondary">
                                Annuler
                            </Button>
                        </DialogClose>
                        <Button type="submit" :disabled="createForm.processing">
                            Créer
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Edit dialog -->
        <Dialog :open="editTarget !== null" @update:open="closeEdit">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Modifier l’organisation</DialogTitle>
                    <DialogDescription>
                        Mettez à jour le nom et le statut de l’organisation.
                    </DialogDescription>
                </DialogHeader>
                <form class="flex flex-col gap-4" @submit.prevent="submitEdit">
                    <div class="grid gap-2">
                        <Label for="org-edit-name">Nom</Label>
                        <Input
                            id="org-edit-name"
                            v-model="editForm.name"
                            required
                        />
                        <InputError :message="editForm.errors.name" />
                    </div>
                    <DialogFooter class="gap-2">
                        <DialogClose as-child>
                            <Button type="button" variant="secondary">
                                Annuler
                            </Button>
                        </DialogClose>
                        <Button type="submit" :disabled="editForm.processing">
                            Enregistrer
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Delete dialog -->
        <Dialog :open="deleteTarget !== null" @update:open="closeDelete">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Supprimer cette organisation ?</DialogTitle>
                    <DialogDescription>
                        Cette action supprimera
                        <strong>{{ deleteTarget?.name }}</strong>
                        ainsi que l’accès de ses membres. Vous pouvez la
                        restaurer ultérieurement si nécessaire.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button type="button" variant="secondary">
                            Annuler
                        </Button>
                    </DialogClose>
                    <Button
                        type="button"
                        variant="destructive"
                        @click="confirmDelete"
                    >
                        Supprimer
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Details / member management sheet -->
        <Sheet
            :open="detailsOrg !== null"
            @update:open="onDetailsOpenChange"
        >
            <SheetContent
                side="right"
                class="flex h-full w-[42vw] min-w-[320px] max-w-[42vw] flex-col gap-0 border-l p-0 sm:max-w-[42vw]"
            >
                <template v-if="detailsOrg">
                    <SheetHeader
                        class="shrink-0 space-y-1 border-b border-sidebar-border/70 px-6 py-4 text-left"
                    >
                        <SheetTitle class="flex items-center gap-2">
                            <Building2 class="size-5 text-primary" />
                            {{ detailsOrg.name }}
                        </SheetTitle>
                        <SheetDescription>
                            {{ detailsOrg.slug }} ·
                            {{ detailsOrg.members_count }} membre(s) ·
                            {{ detailsOrg.is_active ? 'Active' : 'Inactive' }}
                        </SheetDescription>
                    </SheetHeader>

                    <div class="min-h-0 flex-1 space-y-6 overflow-y-auto px-6 py-4">
                        <!-- Current members -->
                        <section class="space-y-3">
                            <h3 class="text-sm font-semibold">
                                Membres ({{ detailsOrg.members.length }})
                            </h3>
                            <div
                                class="overflow-hidden rounded-lg border border-sidebar-border/60"
                            >
                                <table class="w-full text-left text-sm">
                                    <thead class="bg-muted/40">
                                        <tr>
                                            <th class="px-3 py-2 font-medium">
                                                Membre
                                            </th>
                                            <th class="px-3 py-2 font-medium">
                                                Rôle
                                            </th>
                                            <th class="w-[1%] px-3 py-2"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-if="detailsOrg.members.length === 0"
                                        >
                                            <td
                                                colspan="3"
                                                class="px-3 py-6 text-center text-muted-foreground"
                                            >
                                                Aucun membre.
                                            </td>
                                        </tr>
                                        <tr
                                            v-for="member in detailsOrg.members"
                                            :key="member.id"
                                            class="border-t border-sidebar-border/40"
                                        >
                                            <td class="px-3 py-2">
                                                <div class="font-medium">
                                                    {{ member.name }}
                                                </div>
                                                <div
                                                    class="text-xs text-muted-foreground"
                                                >
                                                    {{ member.email }}
                                                </div>
                                            </td>
                                            <td class="px-3 py-2">
                                                <select
                                                    :value="member.role_id ?? ''"
                                                    class="h-8 rounded-md border border-input bg-transparent px-2 text-sm focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                                                    @change="
                                                        changeMemberRole(
                                                            detailsOrg,
                                                            member,
                                                            Number(
                                                                (
                                                                    $event.target as HTMLSelectElement
                                                                ).value,
                                                            ),
                                                        )
                                                    "
                                                >
                                                    <option
                                                        v-if="member.role_id === null"
                                                        value=""
                                                        disabled
                                                    >
                                                        —
                                                    </option>
                                                    <option
                                                        v-for="role in detailsOrg.roles"
                                                        :key="role.id"
                                                        :value="role.id"
                                                    >
                                                        {{ role.name }}
                                                    </option>
                                                </select>
                                            </td>
                                            <td class="px-3 py-2 text-right">
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    type="button"
                                                    class="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                                    :aria-label="`Retirer ${member.name}`"
                                                    @click="
                                                        detachMember(
                                                            detailsOrg,
                                                            member,
                                                        )
                                                    "
                                                >
                                                    <X class="size-4" />
                                                </Button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </section>

                        <!-- Add members from list -->
                        <section class="space-y-3">
                            <h3 class="flex items-center gap-2 text-sm font-semibold">
                                <UserPlus class="size-4" />
                                Ajouter des membres
                            </h3>

                            <div class="grid gap-2">
                                <Label :for="`add-role-${detailsOrg.id}`">
                                    Rôle attribué
                                </Label>
                                <select
                                    :id="`add-role-${detailsOrg.id}`"
                                    v-model.number="addRoleId"
                                    class="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                                >
                                    <option
                                        v-for="role in detailsOrg.roles"
                                        :key="role.id"
                                        :value="role.id"
                                    >
                                        {{ role.name }}
                                    </option>
                                </select>
                            </div>

                            <div class="relative">
                                <Search
                                    class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground"
                                />
                                <Input
                                    v-model="memberSearch"
                                    class="pl-9"
                                    placeholder="Rechercher un utilisateur…"
                                />
                            </div>

                            <div
                                class="max-h-64 space-y-1 overflow-y-auto rounded-lg border border-input p-1"
                            >
                                <p
                                    v-if="availableUsers.length === 0"
                                    class="flex flex-col items-center gap-2 px-3 py-8 text-center text-sm text-muted-foreground"
                                >
                                    <Users class="size-8 opacity-40" />
                                    Aucun utilisateur disponible.
                                </p>
                                <button
                                    v-for="user in availableUsers"
                                    :key="user.id"
                                    type="button"
                                    class="flex w-full items-center gap-3 rounded-md px-3 py-2 text-left text-sm transition-colors hover:bg-muted/60"
                                    :class="
                                        selectedUserIds.includes(user.id)
                                            ? 'bg-primary/10'
                                            : ''
                                    "
                                    @click="toggleUser(user.id)"
                                >
                                    <span
                                        class="flex size-5 shrink-0 items-center justify-center rounded border"
                                        :class="
                                            selectedUserIds.includes(user.id)
                                                ? 'border-primary bg-primary text-primary-foreground'
                                                : 'border-input'
                                        "
                                    >
                                        <Check
                                            v-if="selectedUserIds.includes(user.id)"
                                            class="size-3.5"
                                        />
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate font-medium">
                                            {{ user.name }}
                                        </span>
                                        <span
                                            class="block truncate text-xs text-muted-foreground"
                                        >
                                            {{ user.email }}
                                        </span>
                                    </span>
                                </button>
                            </div>

                            <div class="flex items-center justify-between gap-3">
                                <span class="text-xs text-muted-foreground">
                                    {{ selectedUserIds.length }} sélectionné(s)
                                </span>
                                <Button
                                    type="button"
                                    :disabled="
                                        addProcessing ||
                                        selectedUserIds.length === 0 ||
                                        addRoleId === null
                                    "
                                    @click="addMembers"
                                >
                                    <UserPlus class="mr-2 size-4" />
                                    Ajouter la sélection
                                </Button>
                            </div>
                        </section>
                    </div>
                </template>
            </SheetContent>
        </Sheet>

        <!-- Departments management sheet -->
        <Sheet
            :open="departmentsOrg !== null"
            @update:open="onDepartmentsOpenChange"
        >
            <SheetContent
                side="right"
                class="flex h-full w-[42vw] min-w-[320px] max-w-[42vw] flex-col gap-0 border-l p-0 sm:max-w-[42vw]"
            >
                <template v-if="departmentsOrg">
                    <SheetHeader
                        class="shrink-0 space-y-1 border-b border-sidebar-border/70 px-6 py-4 text-left"
                    >
                        <SheetTitle class="flex items-center gap-2">
                            <Building2 class="size-5 text-primary" />
                            Départements — {{ departmentsOrg.name }}
                        </SheetTitle>
                        <SheetDescription>
                            Créer, renommer ou supprimer les départements de cette
                            organisation.
                        </SheetDescription>
                    </SheetHeader>

                    <div class="min-h-0 flex-1 space-y-4 overflow-y-auto px-6 py-4">
                        <div
                            class="overflow-hidden rounded-lg border border-sidebar-border/60"
                        >
                            <ul>
                                <li
                                    v-if="departmentsOrg.departments.length === 0"
                                    class="px-3 py-6 text-center text-sm text-muted-foreground"
                                >
                                    Aucun département.
                                </li>
                                <li
                                    v-for="department in departmentsOrg.departments"
                                    :key="department.id"
                                    class="flex items-center gap-2 border-t border-sidebar-border/40 px-3 py-2 first:border-t-0"
                                >
                                    <template
                                        v-if="editingDepartmentId === department.id"
                                    >
                                        <Input
                                            v-model="editingDepartmentName"
                                            class="h-8 flex-1"
                                            placeholder="Nom du département"
                                            @keyup.enter="
                                                saveDepartment(departmentsOrg)
                                            "
                                        />
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            type="button"
                                            :disabled="
                                                departmentProcessing ||
                                                editingDepartmentName.trim() === ''
                                            "
                                            aria-label="Enregistrer"
                                            @click="saveDepartment(departmentsOrg)"
                                        >
                                            <Check class="size-4" />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            type="button"
                                            aria-label="Annuler"
                                            @click="cancelEditDepartment"
                                        >
                                            <X class="size-4" />
                                        </Button>
                                    </template>
                                    <template v-else>
                                        <span class="flex-1 font-medium">
                                            {{ department.name }}
                                        </span>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            type="button"
                                            :aria-label="`Modifier ${department.name}`"
                                            @click="startEditDepartment(department)"
                                        >
                                            <Pencil class="size-4" />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            type="button"
                                            class="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                            :aria-label="`Supprimer ${department.name}`"
                                            @click="
                                                deleteDepartment(
                                                    departmentsOrg,
                                                    department,
                                                )
                                            "
                                        >
                                            <Trash2 class="size-4" />
                                        </Button>
                                    </template>
                                </li>
                            </ul>
                        </div>

                        <div class="flex items-center gap-2">
                            <Input
                                v-model="newDepartmentName"
                                placeholder="Nouveau département…"
                                @keyup.enter="addDepartment(departmentsOrg)"
                            />
                            <Button
                                type="button"
                                :disabled="
                                    departmentProcessing ||
                                    newDepartmentName.trim() === ''
                                "
                                @click="addDepartment(departmentsOrg)"
                            >
                                <Plus class="mr-2 size-4" />
                                Ajouter
                            </Button>
                        </div>
                    </div>
                </template>
            </SheetContent>
        </Sheet>
    </div>
</template>
