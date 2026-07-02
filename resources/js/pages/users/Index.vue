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
import type { User } from '@/types/auth';
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

const props = defineProps<{
    users: PaginatedUsers;
    roles: RoleOption[];
    departments: DepartmentOption[];
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

const sheetOpen = ref(false);
const sheetMode = ref<SheetMode>(null);
const editingUser = ref<User | null>(null);

function openCreate(): void {
    sheetMode.value = 'create';
    editingUser.value = null;
    sheetOpen.value = true;
}

function openEdit(user: User): void {
    sheetMode.value = 'edit';
    editingUser.value = user;
    sheetOpen.value = true;
}

function onSheetOpenChange(open: boolean): void {
    sheetOpen.value = open;
    if (!open) {
        sheetMode.value = null;
        editingUser.value = null;
    }
}

function closeSheetOnSuccess(): void {
    sheetOpen.value = false;
    sheetMode.value = null;
    editingUser.value = null;
}

function requestDelete(user: User): void {
    deleteTarget.value = user;
}

function closeDelete(open: boolean): void {
    if (!open) {
        deleteTarget.value = null;
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
                                <span v-if="user.role?.name">{{
                                    user.role.name
                                }}</span>
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
                                ? 'Renseignez le nom, l’e-mail, le rôle et le mot de passe. Le département est facultatif (utilisateur général).'
                                : 'Mettez à jour les informations et le rôle. Le département est facultatif (utilisateur général). Laissez le mot de passe vide pour ne pas le modifier.'
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
                                class="max-h-40 space-y-2 overflow-y-auto rounded-md border border-input p-3"
                                :class="
                                    errors.department_ids
                                        ? 'border-destructive'
                                        : ''
                                "
                            >
                                <label
                                    v-for="d in props.departments"
                                    :key="`create-dept-${d.id}`"
                                    class="flex cursor-pointer items-center gap-2 text-sm"
                                >
                                    <input
                                        type="checkbox"
                                        name="department_ids[]"
                                        :value="d.id"
                                        class="size-4 rounded border-input"
                                    />
                                    {{ d.name }}
                                </label>
                            </div>
                            <InputError :message="errors.department_ids" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="user-create-role_id">Rôle</Label>
                            <select
                                id="user-create-role_id"
                                name="role_id"
                                required
                                class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none aria-invalid:border-destructive"
                                :class="
                                    errors.role_id
                                        ? 'border-destructive focus-visible:ring-destructive'
                                        : ''
                                "
                                :aria-invalid="errors.role_id ? 'true' : undefined"
                            >
                                <option value="" disabled selected hidden>
                                    Choisir un rôle…
                                </option>
                                <option
                                    v-for="r in props.roles"
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
                                class="max-h-40 space-y-2 overflow-y-auto rounded-md border border-input p-3"
                                :class="
                                    errors.department_ids
                                        ? 'border-destructive'
                                        : ''
                                "
                            >
                                <label
                                    v-for="d in props.departments"
                                    :key="`edit-dept-${editingUser.id}-${d.id}`"
                                    class="flex cursor-pointer items-center gap-2 text-sm"
                                >
                                    <input
                                        type="checkbox"
                                        name="department_ids[]"
                                        :value="d.id"
                                        class="size-4 rounded border-input"
                                        :checked="
                                            editingUser.departments?.some(
                                                (ud) => ud.id === d.id,
                                            ) ?? false
                                        "
                                    />
                                    {{ d.name }}
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
                                :key="`user-role-${editingUser.id}`"
                                name="role_id"
                                required
                                class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                                :class="
                                    errors.role_id
                                        ? 'border-destructive focus-visible:ring-destructive'
                                        : ''
                                "
                                :aria-invalid="errors.role_id ? 'true' : undefined"
                            >
                                <option
                                    v-for="r in props.roles"
                                    :key="r.id"
                                    :value="r.id"
                                    :selected="editingUser.role_id === r.id"
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
                    <DialogTitle>Supprimer cet utilisateur ?</DialogTitle>
                    <DialogDescription>
                        Cette action supprimera définitivement
                        <strong>{{ deleteTarget?.name }}</strong>
                        et ne pourra pas être annulée.
                    </DialogDescription>
                </DialogHeader>
                <Form
                    v-if="deleteTarget"
                    v-bind="UserController.destroy.form(deleteTarget)"
                    class="contents"
                    @success="deleteTarget = null"
                    v-slot="{ processing }"
                >
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
