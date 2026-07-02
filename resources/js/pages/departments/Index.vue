<script setup lang="ts">
import DepartmentController from '@/actions/App/Http/Controllers/DepartmentController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import {
    Alert,
    AlertDescription,
    AlertTitle,
} from '@/components/ui/alert';
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
import { index as departmentsIndex } from '@/routes/departments';
import { Form, Head, Link } from '@inertiajs/vue3';
import { Building2, CircleAlert, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type DepartmentRow = {
    id: number;
    name: string;
    created_at: string;
    updated_at: string;
};

type PaginatedDepartments = {
    data: DepartmentRow[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
};

type SheetMode = 'create' | 'edit' | null;

const props = defineProps<{
    departments: PaginatedDepartments;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Tableau de bord', href: dashboard.url() },
            { title: 'Départements', href: departmentsIndex.url() },
        ],
    },
});

const { can } = usePermissions();

const deleteTarget = ref<DepartmentRow | null>(null);

const sheetOpen = ref(false);
const sheetMode = ref<SheetMode>(null);
const editingDepartment = ref<DepartmentRow | null>(null);

function openCreate(): void {
    sheetMode.value = 'create';
    editingDepartment.value = null;
    sheetOpen.value = true;
}

function openEdit(row: DepartmentRow): void {
    sheetMode.value = 'edit';
    editingDepartment.value = row;
    sheetOpen.value = true;
}

function onSheetOpenChange(open: boolean): void {
    sheetOpen.value = open;
    if (!open) {
        sheetMode.value = null;
        editingDepartment.value = null;
    }
}

function closeSheetOnSuccess(): void {
    sheetOpen.value = false;
    sheetMode.value = null;
    editingDepartment.value = null;
}

function requestDelete(row: DepartmentRow): void {
    deleteTarget.value = row;
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
    <Head title="Départements" />

    <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <Heading
                title="Départements"
                description="Créer, modifier ou supprimer des départements."
            />
            <Button
                v-if="can('departments.create')"
                type="button"
                @click="openCreate"
            >
                <Plus class="mr-2 size-4" />
                Nouveau département
            </Button>
        </div>

        <div
            class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card shadow-sm dark:border-sidebar-border"
        >
            <div class="overflow-x-auto">
                <table class="w-full min-w-[480px] text-left text-sm">
                    <thead
                        class="border-b border-sidebar-border/70 bg-muted/40 dark:border-sidebar-border"
                    >
                        <tr>
                            <th class="px-4 py-3 font-medium">Nom</th>
                            <th
                                class="w-[1%] whitespace-nowrap px-4 py-3 text-right font-medium"
                            >
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="props.departments.data.length === 0">
                            <td
                                colspan="2"
                                class="px-4 py-12 text-center text-muted-foreground"
                            >
                                <Building2
                                    class="mx-auto mb-3 size-10 opacity-40"
                                    aria-hidden="true"
                                />
                                Aucun département pour le moment.
                            </td>
                        </tr>
                        <tr
                            v-for="dept in props.departments.data"
                            :key="dept.id"
                            class="border-b border-sidebar-border/50 last:border-0 dark:border-sidebar-border/60"
                        >
                            <td class="px-4 py-3 font-medium">
                                {{ dept.name }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div
                                    class="flex justify-end gap-1 sm:gap-2"
                                >
                                    <Button
                                        v-if="can('departments.edit')"
                                        variant="ghost"
                                        size="icon"
                                        type="button"
                                        :aria-label="`Modifier ${dept.name}`"
                                        @click="openEdit(dept)"
                                    >
                                        <Pencil class="size-4" />
                                    </Button>
                                    <Button
                                        v-if="can('departments.delete')"
                                        variant="ghost"
                                        size="icon"
                                        class="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                        :aria-label="`Supprimer ${dept.name}`"
                                        @click="requestDelete(dept)"
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
            v-if="props.departments.last_page > 1"
            class="flex flex-wrap items-center justify-center gap-1"
            aria-label="Pagination des pages"
        >
            <template v-for="link in props.departments.links" :key="link.label">
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
                                ? 'Nouveau département'
                                : 'Modifier le département'
                        }}
                    </SheetTitle>
                    <SheetDescription>
                        {{
                            sheetMode === 'create'
                                ? 'Indiquez le nom du département.'
                                : 'Mettez à jour le nom affiché.'
                        }}
                    </SheetDescription>
                </SheetHeader>

                <div class="min-h-0 flex-1 overflow-y-auto px-6 py-4">
                    <Form
                        v-if="sheetMode === 'create'"
                        v-bind="DepartmentController.store.form()"
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
                                Vérifiez les messages sous le champ, puis
                                réessayez.
                            </AlertDescription>
                        </Alert>
                        <div class="grid gap-2">
                            <Label for="dept-create-name">Département</Label>
                            <Input
                                id="dept-create-name"
                                name="name"
                                required
                                autocomplete="organization"
                                placeholder="Ex. Logistique"
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
                        v-else-if="sheetMode === 'edit' && editingDepartment"
                        :key="editingDepartment.id"
                        v-bind="DepartmentController.update.form(editingDepartment)"
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
                                Vérifiez les messages sous le champ, puis
                                réessayez.
                            </AlertDescription>
                        </Alert>
                        <div class="grid gap-2">
                            <Label :for="`dept-edit-${editingDepartment.id}-name`"
                                >Département</Label
                            >
                            <Input
                                :id="`dept-edit-${editingDepartment.id}-name`"
                                name="name"
                                required
                                autocomplete="organization"
                                :default-value="editingDepartment.name"
                                placeholder="Ex. Logistique"
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
                    <DialogTitle>Supprimer ce département ?</DialogTitle>
                    <DialogDescription>
                        <strong>{{ deleteTarget?.name }}</strong>
                        sera retiré de la liste (suppression logique).
                    </DialogDescription>
                </DialogHeader>
                <Form
                    v-if="deleteTarget"
                    v-bind="DepartmentController.destroy.form(deleteTarget)"
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
