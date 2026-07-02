<script setup lang="ts">
import RoleController from '@/actions/App/Http/Controllers/RoleController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import {
    Alert,
    AlertDescription,
    AlertTitle,
} from '@/components/ui/alert';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Button } from '@/components/ui/button';
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
import { index as rolesIndex } from '@/routes/roles';
import { Form, Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    CircleAlert,
    KeyRound,
    Pencil,
    Plus,
    Shield,
    ShieldCheck,
    Trash2,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type PermissionRow = {
    id: number;
    name: string;
    description: string | null;
};

type RoleRow = {
    id: number;
    name: string;
    description: string | null;
    permissions_count: number;
    permissions: Pick<PermissionRow, 'id' | 'name' | 'description'>[];
    created_at: string;
};

type PaginatedRoles = {
    data: RoleRow[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
};

type SheetMode = 'create' | 'edit' | null;

const GROUP_LABELS: Record<string, string> = {
    users: 'Utilisateurs',
    roles: 'Rôles',
};

const props = defineProps<{
    roles: PaginatedRoles;
    permissions: PermissionRow[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Tableau de bord', href: dashboard.url() },
            { title: 'Rôles', href: rolesIndex.url() },
        ],
    },
});

const { can } = usePermissions();

const page = usePage();

const nameSheetOpen = ref(false);
const nameSheetMode = ref<SheetMode>(null);
const editingRole = ref<RoleRow | null>(null);

const assignOpen = ref(false);
const assignRole = ref<RoleRow | null>(null);
/** Checkbox v-model (permission ids). */
const selectedIds = ref<number[]>([]);
const assignSaving = ref(false);

const segments = computed(() => {
    const set = new Set(
        props.permissions.map((p) => segmentOfKey(p.name)),
    );
    return [...set].sort((a, b) => a.localeCompare(b));
});

function segmentOfKey(key: string): string {
    const i = key.indexOf('.');
    return i === -1 ? key : key.slice(0, i);
}

function groupLabel(segment: string): string {
    return (
        GROUP_LABELS[segment] ??
        segment.charAt(0).toUpperCase() + segment.slice(1)
    );
}

function permissionsInSegment(segment: string): PermissionRow[] {
    return props.permissions.filter(
        (p) => segmentOfKey(p.name) === segment,
    );
}

function segmentStats(segment: string): { selected: number; total: number } {
    const perms = permissionsInSegment(segment);
    const total = perms.length;
    const selected = perms.filter((p) =>
        selectedIds.value.includes(p.id),
    ).length;
    return { selected, total };
}

function segmentAllSelected(segment: string): boolean {
    const { selected, total } = segmentStats(segment);
    return total > 0 && selected === total;
}

function toggleSegment(segment: string, checked: boolean): void {
    const ids = permissionsInSegment(segment).map((p) => p.id);
    const set = new Set(selectedIds.value);
    for (const id of ids) {
        if (checked) {
            set.add(id);
        } else {
            set.delete(id);
        }
    }
    selectedIds.value = Array.from(set);
}

function toggleMaster(segment: string): void {
    toggleSegment(segment, !segmentAllSelected(segment));
}

const totalSelected = computed(() => selectedIds.value.length);

function openCreate(): void {
    nameSheetMode.value = 'create';
    editingRole.value = null;
    nameSheetOpen.value = true;
}

function openEdit(role: RoleRow): void {
    nameSheetMode.value = 'edit';
    editingRole.value = role;
    nameSheetOpen.value = true;
}

function onNameSheetOpenChange(open: boolean): void {
    nameSheetOpen.value = open;
    if (!open) {
        nameSheetMode.value = null;
        editingRole.value = null;
    }
}

function closeNameSheetOnSuccess(): void {
    nameSheetOpen.value = false;
    nameSheetMode.value = null;
    editingRole.value = null;
}

function openAssignPermissions(role: RoleRow): void {
    assignRole.value = role;
    selectedIds.value = role.permissions.map((p) => Number(p.id));
    assignOpen.value = true;
}

function onAssignOpenChange(open: boolean): void {
    assignOpen.value = open;
    if (!open) {
        assignRole.value = null;
        selectedIds.value = [];
        assignSaving.value = false;
    }
}

function saveAssignPermissions(): void {
    if (!assignRole.value) {
        return;
    }
    assignSaving.value = true;
    router.put(
        RoleController.syncPermissions.url(assignRole.value),
        { permission_ids: [...selectedIds.value] },
        {
            preserveScroll: true,
            onFinish: () => {
                assignSaving.value = false;
            },
            onSuccess: () => {
                onAssignOpenChange(false);
            },
        },
    );
}

function formatDate(iso: string): string {
    try {
        return new Date(iso).toLocaleDateString('fr-FR');
    } catch {
        return '—';
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

const assignPermissionErrorList = computed(() => {
    const raw = page.props.errors as
        | Record<string, string | string[] | undefined>
        | undefined;
    if (!raw) {
        return [];
    }
    const out: string[] = [];
    for (const [key, val] of Object.entries(raw)) {
        if (!key.startsWith('permission_ids')) {
            continue;
        }
        if (Array.isArray(val)) {
            out.push(...val.filter(Boolean));
        } else if (val) {
            out.push(val);
        }
    }
    return [...new Set(out)];
});

function confirmDelete(role: RoleRow): void {
    if (
        !confirm(
            `Supprimer définitivement le rôle « ${role.name} » ? Cette action est irréversible.`,
        )
    ) {
        return;
    }
    router.delete(RoleController.destroy.url(role), { preserveScroll: true });
}
</script>

<template>
    <Head title="Rôles" />

    <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <Heading
                    title="Rôles"
                    description="Créez des rôles, renommez-les et définissez les permissions associées."
                />
            </div>
            <Button
                v-if="can('roles.create')"
                type="button"
                @click="openCreate"
            >
                <Plus class="mr-2 size-4" />
                Créer un rôle
            </Button>
        </div>

        <div
            class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card shadow-sm dark:border-sidebar-border"
        >
            <div class="overflow-x-auto">
                <table class="w-full min-w-[720px] text-left text-sm">
                    <thead
                        class="border-b border-sidebar-border/70 bg-muted/40 dark:border-sidebar-border"
                    >
                        <tr>
                            <th class="px-4 py-3 font-medium">Nom</th>
                            <th class="px-4 py-3 font-medium">Permissions</th>
                            <th class="px-4 py-3 font-medium">Créé le</th>
                            <th
                                class="w-[1%] whitespace-nowrap px-4 py-3 text-right font-medium"
                            >
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="props.roles.data.length === 0">
                            <td
                                colspan="4"
                                class="px-4 py-14 text-center text-muted-foreground"
                            >
                                <Shield
                                    class="mx-auto mb-3 size-10 opacity-40"
                                    aria-hidden="true"
                                />
                                <p class="mb-4">Aucun rôle pour le moment.</p>
                                <Button
                                    v-if="can('roles.create')"
                                    type="button"
                                    @click="openCreate"
                                >
                                    <Plus class="mr-2 size-4" />
                                    Créer un rôle
                                </Button>
                            </td>
                        </tr>
                        <tr
                            v-for="role in props.roles.data"
                            :key="role.id"
                            class="border-b border-sidebar-border/50 last:border-0 dark:border-sidebar-border/60"
                        >
                            <td class="px-4 py-3 font-medium">
                                {{ role.name }}
                            </td>
                            <td class="px-4 py-3">
                                <div
                                    class="flex flex-wrap items-center gap-2"
                                >
                                    <span class="text-muted-foreground">{{
                                        role.permissions_count
                                    }}</span>
                                    <Button
                                        v-if="
                                            can('roles.assign-permissions')
                                        "
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        class="h-8"
                                        @click="openAssignPermissions(role)"
                                    >
                                        <KeyRound class="mr-1.5 size-3.5" />
                                        Gérer les permissions ({{
                                            role.permissions_count
                                        }})
                                    </Button>
                                    <span
                                        v-else
                                        class="text-xs text-muted-foreground"
                                    >
                                        permission(s)
                                    </span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ formatDate(role.created_at) }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex justify-end gap-1 sm:gap-2">
                                    <Button
                                        v-if="can('roles.edit')"
                                        variant="ghost"
                                        size="icon"
                                        type="button"
                                        :aria-label="`Renommer ${role.name}`"
                                        @click="openEdit(role)"
                                    >
                                        <Pencil class="size-4" />
                                    </Button>
                                    <Button
                                        v-if="can('roles.delete')"
                                        variant="ghost"
                                        size="icon"
                                        class="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                        :aria-label="`Supprimer ${role.name}`"
                                        @click="confirmDelete(role)"
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
            v-if="props.roles.last_page > 1"
            class="flex flex-wrap items-center justify-center gap-1"
            aria-label="Pagination des pages"
        >
            <template v-for="link in props.roles.links" :key="link.label">
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

        <!-- Create / rename role -->
        <Sheet :open="nameSheetOpen" @update:open="onNameSheetOpenChange">
            <SheetContent
                side="right"
                class="flex h-full w-[40vw] min-w-[280px] max-w-[40vw] flex-col gap-0 border-l p-0 sm:max-w-[40vw]"
            >
                <SheetHeader
                    class="shrink-0 space-y-2 border-b border-sidebar-border/70 px-6 py-4 text-left"
                >
                    <div class="flex items-start gap-3">
                        <div
                            class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
                        >
                            <Shield
                                v-if="nameSheetMode === 'create'"
                                class="size-5"
                            />
                            <Pencil v-else class="size-5" />
                        </div>
                        <div class="min-w-0 space-y-1">
                            <SheetTitle>
                                {{
                                    nameSheetMode === 'create'
                                        ? 'Nouveau rôle'
                                        : 'Renommer le rôle'
                                }}
                            </SheetTitle>
                            <SheetDescription>
                                {{
                                    nameSheetMode === 'create'
                                        ? 'Donnez un nom au rôle. Vous pourrez ensuite lui assigner des permissions.'
                                        : 'Modifiez le nom affiché du rôle.'
                                }}
                            </SheetDescription>
                        </div>
                    </div>
                </SheetHeader>

                <div class="min-h-0 flex-1 overflow-y-auto px-6 py-4">
                    <Form
                        v-if="nameSheetMode === 'create'"
                        v-bind="RoleController.store.form()"
                        class="flex flex-col gap-4"
                        preserve-scroll
                        reset-on-success
                        @success="closeNameSheetOnSuccess"
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
                                Vérifiez les messages sous le champ, puis
                                réessayez.
                            </AlertDescription>
                        </Alert>
                        <div class="grid gap-2">
                            <Label for="role-create-name">Nom du rôle</Label>
                            <Input
                                id="role-create-name"
                                name="name"
                                required
                                placeholder="Ex. Éditeur contenu"
                                :class="
                                    errors.name
                                        ? 'border-destructive focus-visible:ring-destructive'
                                        : ''
                                "
                                :aria-invalid="errors.name ? 'true' : undefined"
                            />
                            <InputError :message="errors.name" />
                        </div>
                        <SheetFooter
                            class="mt-2 flex-row justify-end gap-2 border-0 p-0 sm:justify-end"
                        >
                            <Button
                                type="button"
                                variant="secondary"
                                @click="onNameSheetOpenChange(false)"
                            >
                                Annuler
                            </Button>
                            <Button type="submit" :disabled="processing">
                                Créer
                            </Button>
                        </SheetFooter>
                    </Form>

                    <Form
                        v-else-if="nameSheetMode === 'edit' && editingRole"
                        :key="editingRole.id"
                        v-bind="RoleController.update.form(editingRole)"
                        class="flex flex-col gap-4"
                        preserve-scroll
                        @success="closeNameSheetOnSuccess"
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
                                Vérifiez les messages sous le champ, puis
                                réessayez.
                            </AlertDescription>
                        </Alert>
                        <div class="grid gap-2">
                            <Label :for="`role-edit-${editingRole.id}-name`"
                                >Nom du rôle</Label
                            >
                            <Input
                                :id="`role-edit-${editingRole.id}-name`"
                                name="name"
                                required
                                :default-value="editingRole.name"
                                placeholder="Ex. Éditeur contenu"
                                :class="
                                    errors.name
                                        ? 'border-destructive focus-visible:ring-destructive'
                                        : ''
                                "
                                :aria-invalid="errors.name ? 'true' : undefined"
                            />
                            <InputError :message="errors.name" />
                        </div>
                        <SheetFooter
                            class="mt-2 flex-row justify-end gap-2 border-0 p-0 sm:justify-end"
                        >
                            <Button
                                type="button"
                                variant="secondary"
                                @click="onNameSheetOpenChange(false)"
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

        <!-- Assign permissions: plain HTML panel (no Sheet) -->
        <Teleport to="body">
            <div
                v-show="assignOpen"
                class="fixed inset-0 z-50 flex justify-end bg-black/50"
                role="presentation"
                @click.self="onAssignOpenChange(false)"
            >
                <aside
                    class="flex h-full w-[min(44rem,94vw)] min-w-[320px] flex-col border-l border-border bg-background shadow-xl"
                    @click.stop
                >
                    <header
                        class="flex shrink-0 items-start gap-3 border-b border-sidebar-border/70 px-6 py-4"
                    >
                        <div
                            class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
                        >
                            <KeyRound class="size-5" />
                        </div>
                        <div class="min-w-0">
                            <h2 class="text-lg font-semibold leading-tight">
                                Permissions du rôle
                            </h2>
                            <p
                                v-if="assignRole"
                                class="mt-1 text-sm text-muted-foreground"
                            >
                                Rôle :
                                <strong class="text-foreground">{{
                                    assignRole.name
                                }}</strong>
                            </p>
                        </div>
                        <button
                            type="button"
                            class="ml-auto rounded-md p-2 text-muted-foreground hover:bg-muted hover:text-foreground"
                            aria-label="Fermer"
                            @click="onAssignOpenChange(false)"
                        >
                            ×
                        </button>
                    </header>

                    <div
                        class="flex min-h-0 flex-1 flex-col overflow-hidden px-6 py-4"
                    >
                        <Alert
                            v-if="assignPermissionErrorList.length > 0"
                            variant="destructive"
                            class="mb-3 shrink-0 border-destructive/40"
                        >
                            <CircleAlert class="size-4" />
                            <AlertTitle>Enregistrement impossible</AlertTitle>
                            <AlertDescription>
                                <ul class="mt-1 list-inside list-disc space-y-0.5">
                                    <li
                                        v-for="(msg, i) in assignPermissionErrorList"
                                        :key="i"
                                    >
                                        {{ msg }}
                                    </li>
                                </ul>
                            </AlertDescription>
                        </Alert>
                        <div
                            class="sticky top-0 z-10 -mx-2 mb-3 flex items-center justify-between gap-3 rounded-md border border-border bg-card px-3 py-2 shadow-sm"
                        >
                            <span class="text-sm font-medium">Sélection</span>
                            <span
                                class="rounded-full bg-primary/15 px-2.5 py-0.5 text-xs font-medium text-primary"
                            >
                                {{ totalSelected }} /
                                {{ props.permissions.length }}
                            </span>
                        </div>

                        <div class="min-h-0 flex-1 space-y-4 overflow-y-auto pr-1">
                            <div
                                v-for="segment in segments"
                                :key="segment"
                                class="rounded-lg border border-border bg-card/50 p-4"
                            >
                                <div
                                    class="mb-3 flex flex-wrap items-center justify-between gap-2 border-b border-border/80 pb-3"
                                >
                                    <label
                                        class="flex cursor-pointer items-center gap-3 text-sm font-semibold"
                                    >
                                        <input
                                            type="checkbox"
                                            class="size-4 accent-primary"
                                            :checked="segmentAllSelected(segment)"
                                            @click.prevent="toggleMaster(segment)"
                                        />
                                        <span>{{ groupLabel(segment) }}</span>
                                    </label>
                                    <span
                                        class="rounded-md bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground"
                                    >
                                        {{ segmentStats(segment).selected }} /
                                        {{ segmentStats(segment).total }}
                                    </span>
                                </div>
                                <div class="divide-y divide-border/60">
                                    <label
                                        v-for="p in permissionsInSegment(segment)"
                                        :key="p.id"
                                        class="flex cursor-pointer items-start gap-3 py-3 hover:bg-muted/40"
                                    >
                                        <input
                                            v-model="selectedIds"
                                            type="checkbox"
                                            class="mt-0.5 size-4 accent-primary"
                                            :value="p.id"
                                        />
                                        <span class="min-w-0 flex-1">
                                            <span
                                                class="block text-sm font-medium"
                                                >{{
                                                    p.description || p.name
                                                }}</span
                                            >
                                            <span
                                                class="mt-0.5 block font-mono text-xs text-muted-foreground"
                                                >{{ p.name }}</span
                                            >
                                        </span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <footer
                            class="mt-4 flex shrink-0 flex-col gap-3 border-t border-border pt-4 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <p class="text-sm text-muted-foreground">
                                <span class="font-medium text-foreground">{{
                                    totalSelected
                                }}</span>
                                permission(s) sélectionnée(s)
                            </p>
                            <div class="flex w-full justify-end gap-2 sm:w-auto">
                                <button
                                    type="button"
                                    class="rounded-md border border-input bg-background px-4 py-2 text-sm font-medium shadow-xs hover:bg-muted"
                                    @click="onAssignOpenChange(false)"
                                >
                                    Annuler
                                </button>
                                <button
                                    type="button"
                                    class="inline-flex items-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground shadow-xs hover:bg-primary/90 disabled:cursor-not-allowed disabled:opacity-50"
                                    :disabled="assignSaving"
                                    @click="saveAssignPermissions"
                                >
                                    <ShieldCheck class="mr-2 size-4" />
                                    Enregistrer
                                </button>
                            </div>
                        </footer>
                    </div>
                </aside>
            </div>
        </Teleport>
    </div>
</template>
