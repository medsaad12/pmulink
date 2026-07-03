<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    Archive,
    Bookmark,
    Check,
    ChevronDown,
    CircleCheck,
    Folder,
    History,
    LayoutGrid,
    ListTodo,
    LogOut,
    MessageSquare,
    Pencil,
    FileText,
    Plus,
    SlidersHorizontal,
    Table2,
    X,
} from 'lucide-vue-next';
import { computed, nextTick, onMounted, onUnmounted, ref, watch, type CSSProperties } from 'vue';
import { toast } from 'vue-sonner';
import DepartmentFilterStrip from '@/components/whiteboard/DepartmentFilterStrip.vue';
import StickyBoard from '@/components/whiteboard/StickyBoard.vue';
import { type WhiteboardKpiItem } from '@/components/whiteboard/OpenFaitsKpiStrip.vue';
import WeeklyMeteoAllDepartmentsDonut from '@/components/whiteboard/WeeklyMeteoAllDepartmentsDonut.vue';
import WhiteboardMeteoKpiRow from '@/components/whiteboard/WhiteboardMeteoKpiRow.vue';
import WhiteboardOrgSwitcher from '@/components/whiteboard/WhiteboardOrgSwitcher.vue';
import { useWeekRange } from '@/composables/useWeekRange';
import { badgePillFromStatus, isHexColor } from '@/lib/faitStatusStyle';
import { downloadWhiteboardTablePdf } from '@/lib/whiteboardTablePdfExport';
import { logout } from '@/routes';
import type { User } from '@/types/auth';
import {
    defaultProchaineEtapeInput,
    prochaineEtapeDisplaySummary,
    prochaineEtapeFromPivotRow,
    prochaineEtapesForSubmit,
    prochaineEtapesFromSaved,
} from '@/lib/prochaineEtapeDraft';
import type {
    ActionResponsibleOption,
    DepartmentOption,
    EtapeStatusOption,
    FaitMarquantPivotRow,
    FaitMarquantView,
    FaitMarquantWeeklyTimelineWeek,
    FaitStatusOption,
    ProchaineEtapeInput,
    WorkflowStatusOption,
} from '@/types/faitMarquant';
import {
    DRAG_MIME,
    stickyNoteFaceGradientFromStatus,
    stickyNotePaperColorFromStatus,
} from '@/lib/whiteboardProjects.js';

const boardProjectIdsOnBoard = ref<Set<number>>(new Set());

/** Teleports must not run during SSR/hydration (Inertia's body script breaks hydrateTeleport). */
const teleportReady = ref(false);

onMounted(() => {
    teleportReady.value = true;
});

/** Apparence du bouton tiroir (position + transitions : voir `.faits-sheet-fab` en bas de fichier). */
const faitsSheetFabBaseClass =
    'faits-sheet-fab flex size-14 items-center justify-center rounded-full bg-violet-600 text-white shadow-lg ring-violet-400/50 hover:bg-violet-700 hover:shadow-xl focus-visible:ring-4 focus-visible:outline-none dark:bg-violet-600 dark:hover:bg-violet-500';

function onBoardProjectIds(ids: number[]): void {
    boardProjectIdsOnBoard.value = new Set(ids);
}

function statusForDragPayload(rawName: string | undefined): string {
    const n = (rawName ?? '').trim().toLowerCase();

    if (n.includes('archive')) {
return 'archivee';
}

    if (n.includes('clotur') || n.includes('clôtur') || n.includes('termin')) {
return 'cloture';
}

    if (n.includes('critique') || n.includes('risque')) {
return 'critique';
}

    if (n.includes('bon') || n.includes('bonne voie')) {
return 'bon';
}

    if (n.includes('vigilance')) {
return 'vigilance';
}

    return 'vigilance';
}

function rowsForDragPayload(rows: unknown): string[] {
    if (!Array.isArray(rows)) {
return [];
}

    return rows
        .map((row) => {
            if (typeof row === 'string') {
return row.trim();
}

            if (!row || typeof row !== 'object') {
return '';
}

            const r = row as Record<string, unknown>;
            const body =
                r.body ?? r.commentaire ?? r.prochaine_etape ?? r.texte ?? r.content;

            return typeof body === 'string' ? body.trim() : '';
        })
        .filter((s) => s.length > 0);
}

function sidebarDragPayload(fait: FaitMarquantView): Record<string, unknown> {
    const rawEtapes =
        fait.prochaines_etapes ??
        (fait as unknown as Record<string, unknown>).fait_marquant_prochaine_etapes ??
        (fait as unknown as Record<string, unknown>).fait_marquant_prochaine_etape ??
        [];
    const rawCommentaires =
        fait.commentaires ??
        (fait as unknown as Record<string, unknown>).fait_marquant_commentaires ??
        (fait as unknown as Record<string, unknown>).fait_marquant_commentaire ??
        [];

    return {
        id: fait.id,
        name: fait.title,
        faitsMarquants: rowsForDragPayload(rawEtapes),
        commentaires: rowsForDragPayload(rawCommentaires),
        projectStatus: statusForDragPayload(fait.fait_status?.name),
        fait_status_id: fait.fait_status_id,
        deadline: fait.deadline,
    };
}

function onSidebarFaitDragStart(e: DragEvent, fait: FaitMarquantView): void {
    if (boardProjectIdsOnBoard.value.has(fait.id)) {
        e.preventDefault();

        return;
    }

    const payload = sidebarDragPayload(fait);
    const json = JSON.stringify(payload);
    e.dataTransfer?.setData(DRAG_MIME, json);
    e.dataTransfer?.setData('application/json', json);

    if (e.dataTransfer) {
e.dataTransfer.effectAllowed = 'copy';
}
}

const props = defineProps<{
    faitsMarquants: FaitMarquantView[];
    faitStatuses: FaitStatusOption[];
    etapeStatuses: EtapeStatusOption[];
    workflowStatuses: WorkflowStatusOption[];
    /** Tous les utilisateurs éligibles comme responsable action / prochaine étape. */
    actionResponsibles: ActionResponsibleOption[];
    /** Vrai pour un utilisateur global (aucun département assigné). */
    showDepartmentFilter: boolean;
    /** Départements assignés à l'utilisateur courant (vide si global). */
    userDepartmentIds: number[];
    departments: DepartmentOption[];
}>();
const isGlobalUser = computed(() => props.showDepartmentFilter);
const isDepartmentUser = computed(() => !isGlobalUser.value);
const page = usePage<{ auth: { user: User | null } }>();
const userDepartments = computed((): DepartmentOption[] => page.props.auth.user?.departments ?? []);
const currentUserDepartmentIds = computed((): number[] => {
    if (props.userDepartmentIds.length > 0) {
        return props.userDepartmentIds;
    }

    return userDepartments.value.map((d) => d.id);
});

/**
 * Deadline générale : masquée dès qu'au moins une étape existe (l'échéance est alors portée par les étapes).
 * Sinon : obligatoire à la création ; à l'édition, seuls les utilisateurs globaux peuvent la modifier.
 */
const showFaitFormDeadlineField = computed(
    () => (editingId.value === null || isGlobalUser.value) && draftEtapes.value.length === 0,
);

/** `null` = filtre « Tous » (tous les départements visibles). */
const departmentFilterId = ref<number | null>(null);
const showDepartmentFilterPills = computed(() => props.departments.length > 0);

const faitFormDepartments = computed((): DepartmentOption[] =>
    isGlobalUser.value ? props.departments : userDepartments.value,
);

const showFaitFormDepartmentField = computed(() => faitFormDepartments.value.length > 0);

/** Création uniquement lorsque le filtre pointe vers un département assigné à l'utilisateur. */
const showCreateFaitMarquant = computed(() => {
    if (isGlobalUser.value) {
        return true;
    }

    const filterId = departmentFilterId.value;

    if (filterId === null) {
        return false;
    }

    return currentUserDepartmentIds.value.includes(filterId);
});

const { weekRangeLabel, formatDateRangeLabel } = useWeekRange();

/** Filtres vue tableau réservés à la vue propriétaire (`showDepartmentFilter`). Valeurs `null` = toutes. */
const ownerTableFilterWorkflowId = ref<number | null>(null);
const ownerTableFilterFaitStatusId = ref<number | null>(null);
const ownerTableFilterDateStart = ref('');
const ownerTableFilterDateEnd = ref('');
const ownerTableFiltersActive = computed(
    () =>
        ownerTableFilterWorkflowId.value !== null ||
        ownerTableFilterFaitStatusId.value !== null ||
        ownerTableFilterDateStart.value !== '' ||
        ownerTableFilterDateEnd.value !== '',
);

/** Utilisateur global, responsable d'action (du fait ou d'une étape), ou membre du département — aligné sur `allowsCollaborationFrom` côté API. */
function userCanEditFaitMarquant(fait: FaitMarquantView): boolean {
    if (isGlobalUser.value) {
        return true;
    }

    if (userIsResponsableForFait(fait)) {
        return true;
    }

    return currentUserDepartmentIds.value.includes(Number(fait.department_id));
}

/** L'utilisateur courant est-il responsable d'action du fait lui-même ou d'une de ses prochaines étapes ? */
function userIsResponsableForFait(fait: FaitMarquantView): boolean {
    const authUserId = page.props.auth.user?.id;
    if (authUserId === undefined || authUserId === null) {
        return false;
    }

    if (Number(fait.responsable_action_id) === Number(authUserId)) {
        return true;
    }

    return (fait.prochaines_etapes ?? []).some(
        (etape) => Number(etape.responsable_action_id) === Number(authUserId),
    );
}

function isFaitEditableForStickyBoard(faitId: number): boolean {
    const fait = props.faitsMarquants.find((x) => x.id === faitId);

    return fait !== undefined && userCanEditFaitMarquant(fait);
}

function tableRowEditorDisabled(fait: FaitMarquantView): boolean {
    return tableInlineSaving.value[fait.id] === true || !userCanEditFaitMarquant(fait);
}

const WORKFLOW_STATUS_OUVERT = 1;

const workflowStatusClotureId = computed((): number | null => {
    const match = props.workflowStatuses.find((w) => workflowStatusSlug(w.name) === 'cloture');

    return match?.id ?? null;
});

const workflowStatusArchiveId = computed((): number | null => {
    const match = props.workflowStatuses.find((w) => workflowStatusSlug(w.name) === 'archivee');

    return match?.id ?? null;
});

const KPI_FAIT_STATUS_IDS = [1, 2, 3] as const;

const KPI_FAIT_STATUS_METEO_ICONS: Record<(typeof KPI_FAIT_STATUS_IDS)[number], string> = {
    1: '/meteo/1.png',
    2: '/meteo/2.png',
    3: '/meteo/3.png',
};

function countOpenFaitsByStatus(items: FaitMarquantView[]): { meteo1: number; meteo2: number; meteo3: number; total: number } {
    const counts = new Map<number, number>(
        KPI_FAIT_STATUS_IDS.map((id) => [id, 0]),
    );

    for (const f of items) {
        if (f.status_id !== WORKFLOW_STATUS_OUVERT) {
            continue;
        }

        const faitStatusId = f.fait_status_id;

        if (counts.has(faitStatusId)) {
            counts.set(faitStatusId, (counts.get(faitStatusId) ?? 0) + 1);
        }
    }

    const meteo1 = counts.get(1) ?? 0;
    const meteo2 = counts.get(2) ?? 0;
    const meteo3 = counts.get(3) ?? 0;

    return {
        meteo1,
        meteo2,
        meteo3,
        total: meteo1 + meteo2 + meteo3,
    };
}

/** Hauteur bandeau tableau : couvre le bloc gauche absolu (logo + période + météo / donut). */
const wbTableChromeMinHeightClass = computed(() => 'min-h-[7.25rem] sm:min-h-[7.75rem] md:min-h-[8rem]');

/** Moins d’espace au-dessus de la rangée météo + KPI en vue tableau. */
const wbTableInnerTopClass = computed(() => 'pt-1 sm:pt-1.5');

type WbMainView = 'board' | 'table';
const wbMainView = ref<WbMainView>('table');

function faitCreatedAtIso(f: FaitMarquantView): string | undefined {
    const v = f.created_at;

    return typeof v === 'string' ? v : undefined;
}

function isoCalendarDateFromCreatedAt(iso: string | undefined): string | null {
    if (!iso) {
        return null;
    }

    const ymd = iso.slice(0, 10);

    if (!/^\d{4}-\d{2}-\d{2}$/.test(ymd)) {
        return null;
    }

    return ymd;
}

function faitMatchesOwnerDateRange(fait: FaitMarquantView, startIso: string, endIso: string): boolean {
    const created = isoCalendarDateFromCreatedAt(faitCreatedAtIso(fait));

    if (!created) {
        return false;
    }

    const from = startIso && endIso && startIso > endIso ? endIso : startIso;
    const to = startIso && endIso && startIso > endIso ? startIso : endIso;

    if (from && created < from) {
        return false;
    }

    if (to && created > to) {
        return false;
    }

    return true;
}

const tableDateRangeLabel = computed(() => {
    const start = ownerTableFilterDateStart.value;
    const end = ownerTableFilterDateEnd.value;

    if (!start && !end) {
        return 'Toutes les périodes';
    }

    if (start && end) {
        return formatDateRangeLabel(start, end);
    }

    if (start) {
        return `À partir du ${formatDateFr(start)}`;
    }

    return `Jusqu'au ${formatDateFr(end)}`;
});

function formatDateFr(iso: string | null | undefined): string {
    if (!iso) {
        return '—';
    }

    const d = new Date(iso.slice(0, 10));

    if (!Number.isFinite(d.getTime())) {
        return '—';
    }

    return d.toLocaleDateString('fr-FR');
}

function formatDateTimeFr(iso: string | null | undefined): string {
    if (!iso) {
        return '—';
    }

    const d = new Date(iso);

    if (!Number.isFinite(d.getTime())) {
        return '—';
    }

    return d.toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' });
}

function parseIsoDateLocal(iso: string): Date | null {
    const ymd = iso.slice(0, 10);
    const parts = ymd.split('-');

    if (parts.length !== 3) {
        return null;
    }

    const y = Number.parseInt(parts[0]!, 10);
    const m = Number.parseInt(parts[1]!, 10);
    const day = Number.parseInt(parts[2]!, 10);

    if (!Number.isFinite(y) || !Number.isFinite(m) || !Number.isFinite(day)) {
        return null;
    }

    const dt = new Date(y, m - 1, day);

    return Number.isFinite(dt.getTime()) ? dt : null;
}

/** Jours calendaires : date d'échéance − aujourd'hui (minuit local). Négatif = retard. */
function calendarDaysUntilDeadline(iso: string | null | undefined): number | null {
    if (!iso || String(iso).trim() === '') {
        return null;
    }

    const target = parseIsoDateLocal(String(iso));

    if (!target) {
        return null;
    }

    const today = new Date();
    today.setHours(0, 0, 0, 0);
    target.setHours(0, 0, 0, 0);

    return Math.round((target.getTime() - today.getTime()) / 86400000);
}

/** Libellé français pour la colonne durée (deadline − aujourd'hui). */
function deadlineDaysJoursLabel(n: number): string {
    if (n < 0) {
        const a = Math.abs(n);

        return a === 1 ? '1 jour de retard' : `${a} jours de retard`;
    }

    if (n === 1) {
        return '1 jour';
    }

    if (n === 0) {
        return '0 jour';
    }

    return `${n} jours`;
}

/** Une seule évaluation par cellule (utilisé avec `v-for` sur un singleton). */
function deadlineDaysTableCell(
    iso: string | null | undefined,
): { text: string; tone: 'empty' | 'calm' | 'urgent' } {
    const n = calendarDaysUntilDeadline(iso);

    if (n === null) {
        return { text: '—', tone: 'empty' };
    }

    return {
        text: deadlineDaysJoursLabel(n),
        tone: n > 7 ? 'calm' : 'urgent',
    };
}

/** Clôturé / archivé : le décompte sous la deadline n'est plus pertinent. */
function tableWorkflowShowsDeadlineDayCount(workflowStatusId: number): boolean {
    return workflowStatusId !== 2 && workflowStatusId !== 3;
}

function tableWorkflowStatusShowsUnderFaitStatus(workflowStatusId: number): boolean {
    return workflowStatusId === 2 || workflowStatusId === 3;
}

function tableWorkflowStatusLabel(id: number): string {
    return props.workflowStatuses.find((w) => w.id === id)?.name ?? '—';
}

function tableWorkflowStatusMiniBadge(id: number): { className: string; style?: Record<string, string> } {
    const status = props.workflowStatuses.find((w) => w.id === id);

    if (!status) {
        return { className: 'wb-fait-form-status-trigger--archivee' };
    }

    const badge = workflowStatusBadgeForOption(status);

    return { className: badge.triggerClass, style: badge.style };
}

const filteredFaitsMarquants = computed(() => {
    if (departmentFilterId.value === null) {
        return props.faitsMarquants;
    }

    return props.faitsMarquants.filter((f) => f.department_id === departmentFilterId.value);
});

/** Liste filtrée pour le tableau et les KPI (département + période + état + statut). */
const faitsForTableGrouping = computed((): FaitMarquantView[] => {
    let base = filteredFaitsMarquants.value;

    if (!props.showDepartmentFilter) {
        return base;
    }

    const dateStart = ownerTableFilterDateStart.value;
    const dateEnd = ownerTableFilterDateEnd.value;

    if (dateStart || dateEnd) {
        base = base.filter((fait) => faitMatchesOwnerDateRange(fait, dateStart, dateEnd));
    }

    const wf = ownerTableFilterWorkflowId.value;
    const fs = ownerTableFilterFaitStatusId.value;

    if (wf === null && fs === null) {
        return base;
    }

    return base.filter((fait) => {
        if (wf !== null && fait.status_id !== wf) {
            return false;
        }

        if (fs !== null && fait.fait_status_id !== fs) {
            return false;
        }

        return true;
    });
});

/** Répartition météo des faits ouverts (mêmes filtres que le tableau). */
const faitStatusMeteoSummary = computed(() => countOpenFaitsByStatus(faitsForTableGrouping.value));

const faitStatusKpiItems = computed((): WhiteboardKpiItem[] => {
    const faitCounts = new Map<number, number>(
        KPI_FAIT_STATUS_IDS.map((id) => [id, 0]),
    );

    for (const f of faitsForTableGrouping.value) {
        if (f.status_id !== WORKFLOW_STATUS_OUVERT) {
            continue;
        }

        const faitStatusId = f.fait_status_id;

        if (faitCounts.has(faitStatusId)) {
            faitCounts.set(faitStatusId, (faitCounts.get(faitStatusId) ?? 0) + 1);
        }
    }

    const faitFallbackLabels: Record<number, string> = {
        1: 'En bonne voie',
        2: 'Sous vigilance',
        3: 'Critique / À risque',
    };

    return KPI_FAIT_STATUS_IDS.map((id) => {
        const opt = props.faitStatuses.find((s) => s.id === id);
        const label = opt?.name ?? faitFallbackLabels[id] ?? `Statut ${id}`;
        const badge = opt
            ? faitStatusBadgeForOption(opt)
            : faitStatusBadgeForOption({ id, name: label });

        return {
            key: `fait-${id}`,
            label,
            count: faitCounts.get(id) ?? 0,
            slug: statusForDragPayload(label),
            iconSrc: KPI_FAIT_STATUS_METEO_ICONS[id],
            style: badge.style,
            hexBadge: badge.triggerClass.includes('--hex'),
        };
    });
});

/** Filtre « Tous » : regrouper la liste latérale par département. */
const sidebarUsesDepartmentGroups = computed(() => departmentFilterId.value === null);

type DepartmentFaitGroup = { departmentId: number; departmentName: string; faits: FaitMarquantView[] };

function buildDepartmentFaitGroups(items: FaitMarquantView[]): DepartmentFaitGroup[] {
    if (!sidebarUsesDepartmentGroups.value) {
        return [{ departmentId: -1, departmentName: '', faits: items }];
    }

    const groupsOrdered: DepartmentFaitGroup[] = [];
    const indexByDept = new Map<number, number>();

    for (const f of items) {
        const deptId = f.department_id;
        let idx = indexByDept.get(deptId);

        if (idx === undefined) {
            idx = groupsOrdered.length;
            indexByDept.set(deptId, idx);
            const name =
                f.department?.name ??
                props.departments.find((d) => d.id === deptId)?.name ??
                `Département ${deptId}`;
            groupsOrdered.push({ departmentId: deptId, departmentName: name, faits: [] });
        }

        groupsOrdered[idx].faits.push(f);
    }

    groupsOrdered.sort((a, b) => a.departmentName.localeCompare(b.departmentName, 'fr'));

    return groupsOrdered;
}

const sidebarFaitGroups = computed((): DepartmentFaitGroup[] =>
    buildDepartmentFaitGroups(filteredFaitsMarquants.value),
);

const tableViewFaitGroups = computed((): DepartmentFaitGroup[] =>
    buildDepartmentFaitGroups(faitsForTableGrouping.value),
);

/** Vue tableau : ouvert + critique en tête ; clôturé / archivé en bas ; le reste entre les deux (date puis titre). */
function tableViewSortTier(fait: FaitMarquantView): number {
    const workflowId = fait.status_id;
    if (workflowId === 2 || workflowId === 3) {
        return 2;
    }
    if (workflowId === 1 && fait.fait_status_id === 3) {
        return 0;
    }

    return 1;
}

const tableViewGroups = computed(() => {
    return tableViewFaitGroups.value.map((g) => {
        const title =
            g.departmentName.trim() !== ''
                ? g.departmentName
                : (g.faits[0]?.department?.name ??
                  props.departments[0]?.name ??
                  'Sujets');
        const faits = [...g.faits].sort((a, b) => {
            const tierA = tableViewSortTier(a);
            const tierB = tableViewSortTier(b);

            if (tierA !== tierB) {
                return tierA - tierB;
            }

            const ta = Date.parse(faitCreatedAtIso(a) ?? '');
            const tb = Date.parse(faitCreatedAtIso(b) ?? '');

            if (Number.isFinite(tb) && Number.isFinite(ta) && tb !== ta) {
                return tb - ta;
            }

            return a.title.localeCompare(b.title, 'fr', { sensitivity: 'base' });
        });

        return { title, faits };
    });
});

/** Lignes éditables tableau : une ligne vide en fin pour saisir sans bouton « Ajouter ». */
function draftLinesFromSavedBodies(bodies: string[]): string[] {
    const list = [...bodies];

    if (list.length === 0) {
        return [''];
    }

    const last = list[list.length - 1];

    if (last !== undefined && last.trim() !== '') {
        list.push('');
    }

    return list;
}

type TableInlineDraft = {
    title: string;
    fait_status_id: number;
    status_id: number;
    deadline: string | null;
    responsable_action_id: number;
    prochaines_etapes: ProchaineEtapeInput[];
    commentaires: string[];
};

const defaultEtapeStatusId = computed(() => props.etapeStatuses[0]?.id ?? 1);

const tableInlineDrafts = ref<Record<number, TableInlineDraft>>({});
/** Aligne les brouillons tableau avec le serveur quand `updated_at` change (ex. sync sticky). */
const tableDraftServerStampById = ref<Record<number, string>>({});
const tableInlineSaving = ref<Record<number, boolean>>({});

function tableInlineDraftFromFait(fait: FaitMarquantView): TableInlineDraft {
    return {
        title: fait.title,
        fait_status_id: fait.fait_status_id,
        status_id: fait.status_id,
        deadline: fait.deadline ? fait.deadline.slice(0, 10) : null,
        responsable_action_id: fait.responsable_action_id,
        prochaines_etapes: prochaineEtapesFromSaved(
            fait.prochaines_etapes,
            fait.responsable_action_id,
            defaultEtapeStatusId.value,
        ),
        commentaires: draftLinesFromSavedBodies((fait.commentaires ?? []).map((x) => x.body)),
    };
}

function tableDraftFor(fait: FaitMarquantView): TableInlineDraft {
    if (tableInlineSaving.value[fait.id]) {
        return tableInlineDrafts.value[fait.id] ?? tableInlineDraftFromFait(fait);
    }

    const stamp = fait.updated_at ?? '';
    const existing = tableInlineDrafts.value[fait.id];
    const prevStamp = tableDraftServerStampById.value[fait.id];

    if (existing !== undefined && prevStamp === stamp) {
        return existing;
    }

    const created = tableInlineDraftFromFait(fait);
    tableInlineDrafts.value = { ...tableInlineDrafts.value, [fait.id]: created };
    tableDraftServerStampById.value = { ...tableDraftServerStampById.value, [fait.id]: stamp };

    return created;
}

type TablePivotField = 'prochaines_etapes' | 'commentaires';

const tablePivotEditing = ref<{ faitId: number; field: TablePivotField } | null>(null);
const tablePivotEditBuffers = ref<Record<string, string[]>>({});
const tableEtapeEditBuffers = ref<Record<string, ProchaineEtapeInput[]>>({});
const tablePivotAccordionOpen = ref<Record<string, boolean>>({});

function mutateTableDraft(
    faitId: number,
    patch: (draft: TableInlineDraft) => TableInlineDraft,
): void {
    const fait = props.faitsMarquants.find((x) => x.id === faitId);

    if (!fait || !userCanEditFaitMarquant(fait)) {
        return;
    }

    const base = tableInlineDrafts.value[faitId] ?? tableInlineDraftFromFait(fait);

    tableInlineDrafts.value = {
        ...tableInlineDrafts.value,
        [faitId]: patch(base),
    };
}

function tablePivotEditKey(faitId: number, field: TablePivotField): string {
    return `${faitId}:${field}`;
}

function isTablePivotEditing(faitId: number, field: TablePivotField): boolean {
    const active = tablePivotEditing.value;

    return active !== null && active.faitId === faitId && active.field === field;
}

function tablePivotDisplayLines(fait: FaitMarquantView, field: TablePivotField): string[] {
    if (field === 'prochaines_etapes') {
        return prochaineEtapesForSubmit(tableDraftFor(fait).prochaines_etapes).map((etape) =>
            prochaineEtapeDisplaySummary(etape, props.actionResponsibles, props.etapeStatuses),
        );
    }

    return tableDraftFor(fait)[field].map((s) => s.trim()).filter((s) => s !== '');
}

function tableEtapesDisplayRows(fait: FaitMarquantView): ProchaineEtapeInput[] {
    return prochaineEtapesForSubmit(tableDraftFor(fait).prochaines_etapes);
}

function tableCommentairesDisplayRows(fait: FaitMarquantView): string[] {
    return tableDraftFor(fait).commentaires.map((s) => s.trim()).filter((s) => s !== '');
}

/** Libellé léger de l'auteur d'un commentaire : son nom, sinon son identifiant. */
function commentAuthorLabel(row: FaitMarquantPivotRow): string | null {
    if (row.user?.name) {
        return row.user.name;
    }

    if (row.user_id !== undefined && row.user_id !== null) {
        return `#${row.user_id}`;
    }

    return null;
}

/** Lignes de commentaires (lecture) avec l'auteur, pour l'affichage du tableau. */
function tableCommentairesReadRows(
    fait: FaitMarquantView,
): { body: string; authorLabel: string | null }[] {
    return (fait.commentaires ?? [])
        .filter((row) => (row.body ?? '').trim() !== '')
        .map((row) => ({
            body: row.body.trim(),
            authorLabel: commentAuthorLabel(row),
        }));
}

function tableEtapeResponsableLabel(responsableId: number): string {
    return props.actionResponsibles.find((u) => u.id === responsableId)?.name ?? '—';
}

function tableEtapeStatusLabel(etapeStatusId: number): string {
    return props.etapeStatuses.find((s) => s.id === etapeStatusId)?.name ?? '—';
}

function tableEtapeStatusBadge(etapeStatusId: number): { label: string; style: CSSProperties } {
    const status = props.etapeStatuses.find((s) => s.id === etapeStatusId);
    const pill = badgePillFromStatus(status?.color);

    return {
        label: status?.name ?? '—',
        style: {
            backgroundColor: pill.backgroundColor,
            color: pill.color,
            ...(pill.boxShadow !== undefined ? { boxShadow: pill.boxShadow } : {}),
        },
    };
}

function tablePivotAccordionTitle(field: TablePivotField): string {
    return field === 'prochaines_etapes' ? 'Prochaines étapes' : 'Commentaires';
}

function tablePivotAccordionSummary(fait: FaitMarquantView, field: TablePivotField): string {
    if (field === 'prochaines_etapes') {
        const count = tableEtapesDisplayRows(fait).length;

        if (count === 0) {
            return 'Aucune étape';
        }

        return count === 1 ? '1 étape' : `${count} étapes`;
    }

    const count = tableCommentairesDisplayRows(fait).length;

    if (count === 0) {
        return 'Aucun commentaire';
    }

    return count === 1 ? '1 commentaire' : `${count} commentaires`;
}

function isTablePivotAccordionExpanded(faitId: number, field: TablePivotField): boolean {
    if (isTablePivotEditing(faitId, field)) {
        return true;
    }

    return tablePivotAccordionOpen.value[tablePivotEditKey(faitId, field)] === true;
}

function toggleTablePivotAccordion(faitId: number, field: TablePivotField): void {
    if (isTablePivotEditing(faitId, field)) {
        return;
    }

    const key = tablePivotEditKey(faitId, field);

    tablePivotAccordionOpen.value = {
        ...tablePivotAccordionOpen.value,
        [key]: !tablePivotAccordionOpen.value[key],
    };
}

function startTablePivotAccordionEdit(faitId: number, field: TablePivotField): void {
    const key = tablePivotEditKey(faitId, field);

    tablePivotAccordionOpen.value = {
        ...tablePivotAccordionOpen.value,
        [key]: true,
    };
    startTablePivotEdit(faitId, field);
}

function tablePivotEditLines(faitId: number, field: TablePivotField): string[] {
    return tablePivotEditBuffers.value[tablePivotEditKey(faitId, field)] ?? [''];
}

function tableEtapeEditLines(faitId: number): ProchaineEtapeInput[] {
    const key = tablePivotEditKey(faitId, 'prochaines_etapes');
    const lines = tableEtapeEditBuffers.value[key];

    if (lines !== undefined) {
        return lines;
    }

    const fait = props.faitsMarquants.find((x) => x.id === faitId);

    return [
        defaultProchaineEtapeInput(
            fait?.responsable_action_id ?? defaultResponsableActionId(),
            defaultEtapeStatusId.value,
        ),
    ];
}

function cancelTablePivotEdit(): void {
    const active = tablePivotEditing.value;

    if (active === null) {
        return;
    }

    const key = tablePivotEditKey(active.faitId, active.field);
    const next = { ...tablePivotEditBuffers.value };
    const nextEtapes = { ...tableEtapeEditBuffers.value };

    delete next[key];
    delete nextEtapes[key];
    tablePivotEditBuffers.value = next;
    tableEtapeEditBuffers.value = nextEtapes;
    tablePivotEditing.value = null;
}

function startTablePivotEdit(faitId: number, field: TablePivotField): void {
    if (tableInlineSaving.value[faitId] === true) {
        return;
    }

    const fait = props.faitsMarquants.find((x) => x.id === faitId);

    if (!fait || !userCanEditFaitMarquant(fait)) {
        return;
    }

    cancelTablePivotEdit();

    const draft = tableInlineDrafts.value[faitId] ?? tableInlineDraftFromFait(fait);
    const key = tablePivotEditKey(faitId, field);

    if (field === 'prochaines_etapes') {
        const etapes = prochaineEtapesForSubmit(draft.prochaines_etapes);

        tableEtapeEditBuffers.value = {
            ...tableEtapeEditBuffers.value,
            [key]:
                etapes.length > 0
                    ? [...etapes, defaultProchaineEtapeInput(draft.responsable_action_id, defaultEtapeStatusId.value)]
                    : [defaultProchaineEtapeInput(draft.responsable_action_id, defaultEtapeStatusId.value)],
        };
    } else {
        const bodies = draft[field].map((s) => s.trim()).filter((s) => s !== '');

        tablePivotEditBuffers.value = {
            ...tablePivotEditBuffers.value,
            [key]: draftLinesFromSavedBodies(bodies),
        };
    }

    tablePivotEditing.value = { faitId, field };
    tablePivotAccordionOpen.value = {
        ...tablePivotAccordionOpen.value,
        [key]: true,
    };
}

function syncTableNestedTextarea(el: HTMLTextAreaElement | null): void {
    if (el === null) {
        return;
    }

    const lineHeight = parseFloat(getComputedStyle(el).lineHeight) || 22;
    const minH = lineHeight + 4;

    el.style.height = 'auto';
    el.style.height = `${Math.max(el.scrollHeight, minH)}px`;
}

function onTableEtapeBodyInput(faitId: number, index: number, event: Event): void {
    const target = event.target as HTMLTextAreaElement;

    onTableEtapeFieldInput(faitId, index, 'body', target.value);
    syncTableNestedTextarea(target);
}

function onTableCommentBodyInput(faitId: number, index: number, event: Event): void {
    const target = event.target as HTMLTextAreaElement;

    onTablePivotEditLineInput(faitId, 'commentaires', index, target.value);
    syncTableNestedTextarea(target);
}

watch(tablePivotEditing, () => {
    nextTick(() => {
        document.querySelectorAll('textarea.wb-nested-pivot-text--edit').forEach((node) => {
            syncTableNestedTextarea(node as HTMLTextAreaElement);
        });
    });
});

function onTablePivotEditLineInput(
    faitId: number,
    field: TablePivotField,
    index: number,
    value: string,
): void {
    const key = tablePivotEditKey(faitId, field);
    const lines = [...(tablePivotEditBuffers.value[key] ?? [''])];

    lines[index] = value;

    const lastIdx = lines.length - 1;

    if (index === lastIdx && value.trim() !== '') {
        lines.push('');
    }

    tablePivotEditBuffers.value = {
        ...tablePivotEditBuffers.value,
        [key]: lines,
    };
}

function removeTablePivotEditLine(faitId: number, field: TablePivotField, index: number): void {
    const key = tablePivotEditKey(faitId, field);
    const lines = [...(tablePivotEditBuffers.value[key] ?? [''])];

    lines.splice(index, 1);

    if (lines.length === 0) {
        tablePivotEditBuffers.value = { ...tablePivotEditBuffers.value, [key]: [''] };

        return;
    }

    const last = lines[lines.length - 1];

    if (last !== undefined && last.trim() !== '') {
        lines.push('');
    }

    tablePivotEditBuffers.value = {
        ...tablePivotEditBuffers.value,
        [key]: lines,
    };
}

function tablePivotLineCanRemove(lines: string[], index: number): boolean {
    if (lines.length > 1) {
        return true;
    }

    return (lines[index] ?? '').trim() !== '';
}

function tableEtapeLineCanRemove(lines: ProchaineEtapeInput[], index: number): boolean {
    if (lines.length > 1) {
        return true;
    }

    return (lines[index]?.body ?? '').trim() !== '';
}

function saveTablePivotEdit(): void {
    const active = tablePivotEditing.value;

    if (active === null || tableInlineSaving.value[active.faitId] === true) {
        return;
    }

    const key = tablePivotEditKey(active.faitId, active.field);

    if (active.field === 'prochaines_etapes') {
        const etapes = prochaineEtapesForSubmit(tableEtapeEditBuffers.value[key] ?? []);

        mutateTableDraft(active.faitId, (draft) => ({
            ...draft,
            prochaines_etapes: etapes,
        }));

        const nextEtapes = { ...tableEtapeEditBuffers.value };

        delete nextEtapes[key];
        tableEtapeEditBuffers.value = nextEtapes;
    } else {
        const lines = tablePivotEditBuffers.value[key] ?? [];

        mutateTableDraft(active.faitId, (draft) => ({
            ...draft,
            [active.field]: lines,
        }));

        const next = { ...tablePivotEditBuffers.value };

        delete next[key];
        tablePivotEditBuffers.value = next;
    }

    tablePivotEditing.value = null;

    saveTableInlineRow(active.faitId);
}

/** Fond de ligne vue tableau selon le statut workflow affiché (inline). */
function tableViewRowClass(statusId: number): string {
    const base = 'wb-general-table__row--workflow';

    if (statusId === 3) {
        return `${base} wb-general-table__row--archive`;
    }

    if (statusId === 2) {
        return `${base} wb-general-table__row--cloture`;
    }

    return `${base} wb-general-table__row--ouvert`;
}

function workflowStatusSlug(rawName: string | undefined): string {
    const n = (rawName ?? '').trim().toLowerCase();

    if (n.includes('ouvert')) return 'bon';
    if (n.includes('clotur') || n.includes('clôtur') || n.includes('ferm')) return 'cloture';
    if (n.includes('archiv')) return 'archivee';

    return statusForDragPayload(rawName);
}

function workflowStatusBadgeForOption(w: WorkflowStatusOption): {
    triggerClass: string;
    menuItemClass: string;
    style?: Record<string, string>;
} {
    const raw = w.color?.trim();

    if (raw) {
        if (isHexColor(raw)) {
            const pill = badgePillFromStatus(raw);
            const style: Record<string, string> = {
                backgroundColor: pill.backgroundColor,
                color: pill.color,
            };

            if (pill.boxShadow) {
                style.boxShadow = pill.boxShadow;
            }

            return {
                triggerClass: 'wb-fait-form-status-trigger--hex',
                menuItemClass: 'wb-fait-form-status-menu-item--hex',
                style,
            };
        }

        return {
            triggerClass: 'wb-fait-form-status-trigger--hex',
            menuItemClass: 'wb-fait-form-status-menu-item--hex',
            style: {
                backgroundColor: `color-mix(in srgb, ${raw} 26%, white)`,
                color: `color-mix(in srgb, ${raw} 78%, black)`,
                borderColor: 'rgb(15 23 42 / 14%)',
            },
        };
    }

    const slug = workflowStatusSlug(w.name);

    return {
        triggerClass: `wb-fait-form-status-trigger--${slug}`,
        menuItemClass: `wb-fait-form-status-menu-item--${slug}`,
    };
}

function workflowStatusBadgeForFait(f: FaitMarquantView): ReturnType<typeof workflowStatusBadgeForOption> {
    const w = f.workflow_status;

    if (w && typeof w.id === 'number' && typeof w.name === 'string') {
        return workflowStatusBadgeForOption(w);
    }

    const byId = props.workflowStatuses.find((x) => x.id === f.status_id);

    if (byId) {
        return workflowStatusBadgeForOption(byId);
    }

    return workflowStatusBadgeForOption({ id: 0, name: 'Ouvert' });
}

function tableFaitStatusSelectBadge(id: number): { className: string; style?: Record<string, string> } {
    const s = props.faitStatuses.find((x) => x.id === id);

    if (!s) {
        return { className: 'wb-fait-form-status-trigger--vigilance' };
    }

    const badge = faitStatusBadgeForOption(s);

    return { className: badge.triggerClass, style: badge.style };
}

function setTableWorkflowStatus(faitId: number, workflowStatusId: number): void {
    const fait = props.faitsMarquants.find((x) => x.id === faitId);

    if (!fait || !userCanEditFaitMarquant(fait)) {
        return;
    }

    const draft = tableInlineDrafts.value[faitId] ?? tableInlineDraftFromFait(fait);

    if (draft.status_id === workflowStatusId) {
        return;
    }

    tableInlineDrafts.value = {
        ...tableInlineDrafts.value,
        [faitId]: { ...draft, status_id: workflowStatusId },
    };

    saveTableInlineRow(faitId);
}

function cloturerTableFait(faitId: number): void {
    const targetId = workflowStatusClotureId.value;

    if (targetId === null) {
        return;
    }

    setTableWorkflowStatus(faitId, targetId);
}

function archiverTableFait(faitId: number): void {
    const targetId = workflowStatusArchiveId.value;

    if (targetId === null) {
        return;
    }

    setTableWorkflowStatus(faitId, targetId);
}

function tableFaitCanCloturer(fait: FaitMarquantView): boolean {
    if (!userCanEditFaitMarquant(fait)) {
        return false;
    }

    const statusId = tableDraftFor(fait).status_id;
    const clotureId = workflowStatusClotureId.value;
    const archiveId = workflowStatusArchiveId.value;

    if (clotureId !== null && statusId === clotureId) {
        return false;
    }

    if (archiveId !== null && statusId === archiveId) {
        return false;
    }

    return clotureId !== null;
}

function tableFaitCanArchiver(fait: FaitMarquantView): boolean {
    if (!userCanEditFaitMarquant(fait)) {
        return false;
    }

    const statusId = tableDraftFor(fait).status_id;
    const archiveId = workflowStatusArchiveId.value;

    return archiveId !== null && statusId !== archiveId;
}

function tableFaitIsCloture(fait: FaitMarquantView): boolean {
    const clotureId = workflowStatusClotureId.value;

    return clotureId !== null && tableDraftFor(fait).status_id === clotureId;
}

function tableFaitIsArchive(fait: FaitMarquantView): boolean {
    const archiveId = workflowStatusArchiveId.value;

    return archiveId !== null && tableDraftFor(fait).status_id === archiveId;
}

function saveTableInlineRow(faitId: number): void {
    const fait = props.faitsMarquants.find((x) => x.id === faitId);

    if (!fait) {
        return;
    }

    if (!userCanEditFaitMarquant(fait)) {
        return;
    }

    const draft = tableInlineDrafts.value[faitId] ?? tableInlineDraftFromFait(fait);
    tableInlineSaving.value = { ...tableInlineSaving.value, [faitId]: true };

    const endpoint = isDepartmentUser.value
        ? `/faits-marquants/${faitId}/draft`
        : `/faits-marquants/${faitId}`;

    const payload: Record<string, unknown> = {
        title: draft.title,
        fait_status_id: draft.fait_status_id,
        status_id: draft.status_id,
        deadline: draft.deadline,
        responsable_action_id: draft.responsable_action_id,
        prochaines_etapes: prochaineEtapesForSubmit(draft.prochaines_etapes),
        commentaires: draft.commentaires.filter((s) => s.trim() !== ''),
    };

    // Le département est requis côté serveur (mise à jour publiée comme brouillon).
    // Le fait n'étant éditable que dans un département autorisé, on renvoie le sien.
    payload.department_id = fait.department_id;

    router.put(endpoint, payload, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
        onError: () => {
            toast.error("Impossible d'enregistrer les modifications.");
        },
        onFinish: () => {
            tableInlineSaving.value = { ...tableInlineSaving.value, [faitId]: false };
        },
    });
}

type TableDropdownState = {
    faitId: number;
    left: number;
    top: number;
    minWidth: number;
} | null;

const tableDropdown = ref<TableDropdownState>(null);

const tableHistoryFaitId = ref<number | null>(null);

const weeklyTimelineWeeks = ref<FaitMarquantWeeklyTimelineWeek[]>([]);
const weeklyTimelineLoading = ref(false);
const weeklyTimelineError = ref<string | null>(null);
const weeklyTimelineTimezone = ref('Africa/Casablanca');
/** Par semaine : affichage prochaines étapes vs commentaires (fin de semaine). */
const weeklyTimelineWeekPivot = ref<Record<string, 'etapes' | 'commentaires'>>({});

function weeklyWeekPivot(weekStart: string): 'etapes' | 'commentaires' {
    return weeklyTimelineWeekPivot.value[weekStart] ?? 'etapes';
}

function setWeeklyWeekPivot(weekStart: string, tab: 'etapes' | 'commentaires'): void {
    weeklyTimelineWeekPivot.value = { ...weeklyTimelineWeekPivot.value, [weekStart]: tab };
}

watch(tableHistoryFaitId, async (id) => {
    weeklyTimelineError.value = null;
    weeklyTimelineWeeks.value = [];
    weeklyTimelineWeekPivot.value = {};

    if (id === null) {
        return;
    }

    weeklyTimelineLoading.value = true;

    try {
        const res = await fetch(`/faits-marquants/${id}/weekly-timeline`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        if (!res.ok) {
            throw new Error('weekly_timeline_http');
        }

        const data = (await res.json()) as { weeks?: FaitMarquantWeeklyTimelineWeek[]; timezone?: string };
        weeklyTimelineWeeks.value = Array.isArray(data.weeks) ? data.weeks : [];
        if (typeof data.timezone === 'string' && data.timezone.length > 0) {
            weeklyTimelineTimezone.value = data.timezone;
        }
    } catch {
        weeklyTimelineError.value = 'Impossible de charger la chronologie hebdomadaire.';
    } finally {
        weeklyTimelineLoading.value = false;
    }
});

function tableFaitStatusLabel(id: number): string {
    return props.faitStatuses.find((s) => s.id === id)?.name ?? '—';
}

function clampTableDropdownLeft(left: number, minWidth: number): number {
    if (typeof window === 'undefined') {
        return left;
    }

    const pad = 8;
    const vw = window.innerWidth;
    const w = Math.min(minWidth, vw - pad * 2);
    let x = left;

    if (x + w > vw - pad) {
        x = Math.max(pad, vw - pad - w);
    }

    if (x < pad) {
        x = pad;
    }

    return x;
}

function toggleTableFaitStatusDropdown(faitId: number, ev: MouseEvent): void {
    if (tableInlineSaving.value[faitId]) {
        return;
    }

    const rowFait = props.faitsMarquants.find((x) => x.id === faitId);

    if (!rowFait || !userCanEditFaitMarquant(rowFait)) {
        return;
    }

    if (tableDropdown.value?.faitId === faitId) {
        tableDropdown.value = null;
        return;
    }

    tableHistoryFaitId.value = null;

    const btn = ev.currentTarget as HTMLElement;
    const rect = btn.getBoundingClientRect();
    const minWidth = Math.max(rect.width, 136);
    tableDropdown.value = {
        faitId,
        left: clampTableDropdownLeft(rect.left, minWidth),
        top: rect.bottom + 6,
        minWidth,
    };
}

function pickTableFaitStatus(faitId: number, statusId: number): void {
    tableDropdown.value = null;
    const rowFait = props.faitsMarquants.find((x) => x.id === faitId);

    if (!rowFait || !userCanEditFaitMarquant(rowFait)) {
        return;
    }

    const draft = tableInlineDrafts.value[faitId];

    if (!draft || draft.fait_status_id === statusId) {
        return;
    }

    draft.fait_status_id = statusId;
    saveTableInlineRow(faitId);
}

function toggleTableHistoryPanel(faitId: number): void {
    if (tableHistoryFaitId.value === faitId) {
        tableHistoryFaitId.value = null;

        return;
    }

    tableDropdown.value = null;
    tableHistoryFaitId.value = faitId;
}

function onTableBadgePointerDownOutside(ev: Event): void {
    const target = ev.target;

    if (!(target instanceof Element)) {
        return;
    }

    if (
        target.closest('[data-table-badge-trigger]') ||
        target.closest('.wb-general-table-floating-menu') ||
        target.closest('[data-table-history-trigger]') ||
        target.closest('.wb-table-history-drawer')
    ) {
        return;
    }

    tableDropdown.value = null;
    tableHistoryFaitId.value = null;
}

function onTableBadgeKeydownEscape(ev: KeyboardEvent): void {
    if (ev.key !== 'Escape') {
        return;
    }

    if (tableDropdown.value !== null) {
        ev.stopPropagation();
        tableDropdown.value = null;

        return;
    }

    if (tableHistoryFaitId.value !== null) {
        ev.stopPropagation();
        tableHistoryFaitId.value = null;
    }
}

function onTableScrollOrResize(): void {
    tableDropdown.value = null;
}

function bindTableBadgeDismiss(): void {
    document.addEventListener('pointerdown', onTableBadgePointerDownOutside, true);
    document.addEventListener('keydown', onTableBadgeKeydownEscape, true);
    window.addEventListener('scroll', onTableScrollOrResize, true);
    window.addEventListener('resize', onTableScrollOrResize, true);
}

function unbindTableBadgeDismiss(): void {
    document.removeEventListener('pointerdown', onTableBadgePointerDownOutside, true);
    document.removeEventListener('keydown', onTableBadgeKeydownEscape, true);
    window.removeEventListener('scroll', onTableScrollOrResize, true);
    window.removeEventListener('resize', onTableScrollOrResize, true);
}

watch(
    () => tableDropdown.value !== null || tableHistoryFaitId.value !== null,
    (open) => {
        if (open) {
            bindTableBadgeDismiss();
        } else {
            unbindTableBadgeDismiss();
        }
    },
);

const tableDropdownStyle = computed((): CSSProperties => {
    const d = tableDropdown.value;

    if (!d) {
        return {};
    }

    return {
        position: 'fixed',
        left: `${d.left}px`,
        top: `${d.top}px`,
        minWidth: `${d.minWidth}px`,
    };
});

const tableHistoryFait = computed((): FaitMarquantView | null => {
    const id = tableHistoryFaitId.value;

    if (id === null) {
        return null;
    }

    return props.faitsMarquants.find((f) => f.id === id) ?? null;
});

const tableDropdownFaitDraft = computed(() => {
    const d = tableDropdown.value;

    if (!d) {
        return null;
    }

    return tableInlineDrafts.value[d.faitId] ?? null;
});

function resetOwnerTableFilters(): void {
    ownerTableFilterWorkflowId.value = null;
    ownerTableFilterFaitStatusId.value = null;
    ownerTableFilterDateStart.value = '';
    ownerTableFilterDateEnd.value = '';
}

const sidebarOpen = ref(false);
const dialogOpen = ref(false);
/** Création en vue cartes : formulaire flottant façon post-it (le panneau formulaire à droite reste pour le tableau et pour « formulaire complet »). */
const stickyCreateOpen = ref(false);
const editingId = ref<number | null>(null);

const stickyBoardRef = ref<InstanceType<typeof StickyBoard> | null>(null);

/** Après « Réduire tous », un changement de filtre département remet tous les faits visibles sur le tableau. */
watch(departmentFilterId, async () => {
    if (!showDepartmentFilterPills.value) {
        return;
    }

    if (props.showDepartmentFilter) {
        resetOwnerTableFilters();
    }

    await nextTick();
    stickyBoardRef.value?.showAllFaitsOnBoard?.();
});

function defaultFaitDepartmentId(): number | null {
    const filterId = departmentFilterId.value;
    const assigned = currentUserDepartmentIds.value;

    if (!isGlobalUser.value) {
        if (filterId != null && assigned.includes(filterId)) {
            return filterId;
        }

        return assigned[0] ?? null;
    }

    return filterId ?? props.departments[0]?.id ?? null;
}

function defaultResponsableActionId(_departmentId: number | null = defaultFaitDepartmentId()): number {
    const authUserId = page.props.auth.user?.id;
    const candidates = props.actionResponsibles;
    const match = candidates.find((u) => u.id === authUserId);

    return match?.id ?? candidates[0]?.id ?? 0;
}

const form = useForm({
    title: '',
    fait_status_id: props.faitStatuses[0]?.id ?? 1,
    status_id: props.workflowStatuses[0]?.id ?? 1,
    deadline: null as string | null,
    department_id: defaultFaitDepartmentId(),
    responsable_action_id: defaultResponsableActionId(),
});

const actionResponsiblesForForm = computed((): ActionResponsibleOption[] => {
    return props.actionResponsibles;
});

const selectedActionResponsible = computed(() =>
    actionResponsiblesForForm.value.find((u) => u.id === form.responsable_action_id),
);

const listTab = ref<'etapes' | 'commentaires'>('etapes');
const draftEtapes = ref<ProchaineEtapeInput[]>([]);
const draftCommentaires = ref<string[]>([]);

function addDraftEtape(): void {
    draftEtapes.value.push(
        defaultProchaineEtapeInput(form.responsable_action_id, defaultEtapeStatusId.value),
    );
}

function addDraftCommentaire(): void {
    draftCommentaires.value.push('');
}

function removeDraftEtape(index: number): void {
    draftEtapes.value.splice(index, 1);
}

function removeDraftCommentaire(index: number): void {
    draftCommentaires.value.splice(index, 1);
}

function onTableEtapeFieldInput(
    faitId: number,
    index: number,
    field: keyof ProchaineEtapeInput,
    value: string | number | null,
): void {
    const key = tablePivotEditKey(faitId, 'prochaines_etapes');
    const lines = [...tableEtapeEditLines(faitId)];
    const row = lines[index];

    if (!row) {
        return;
    }

    if (field === 'body') {
        row.body = String(value);
    } else if (field === 'responsable_action_id') {
        row.responsable_action_id = Number(value);
    } else if (field === 'deadline') {
        row.deadline = value === '' ? null : String(value);
    } else if (field === 'etape_status_id') {
        row.etape_status_id = Number(value);
    }

    const lastIdx = lines.length - 1;

    if (index === lastIdx && row.body.trim() !== '') {
        const fait = props.faitsMarquants.find((x) => x.id === faitId);
        lines.push(
            defaultProchaineEtapeInput(
                fait?.responsable_action_id ?? defaultResponsableActionId(),
                defaultEtapeStatusId.value,
            ),
        );
    }

    tableEtapeEditBuffers.value = { ...tableEtapeEditBuffers.value, [key]: lines };
}

function removeTableEtapeEditLine(faitId: number, index: number): void {
    const key = tablePivotEditKey(faitId, 'prochaines_etapes');
    const lines = [...tableEtapeEditLines(faitId)];

    lines.splice(index, 1);

    if (lines.length === 0) {
        const fait = props.faitsMarquants.find((x) => x.id === faitId);
        tableEtapeEditBuffers.value = {
            ...tableEtapeEditBuffers.value,
            [key]: [
                defaultProchaineEtapeInput(
                    fait?.responsable_action_id ?? defaultResponsableActionId(),
                    defaultEtapeStatusId.value,
                ),
            ],
        };

        return;
    }

    const last = lines[lines.length - 1];

    if (last !== undefined && last.body.trim() !== '') {
        const fait = props.faitsMarquants.find((x) => x.id === faitId);
        lines.push(
            defaultProchaineEtapeInput(
                fait?.responsable_action_id ?? defaultResponsableActionId(),
                defaultEtapeStatusId.value,
            ),
        );
    }

    tableEtapeEditBuffers.value = { ...tableEtapeEditBuffers.value, [key]: lines };
}

/** Couleurs pastille / donut : même logique que sticky `toolboxPaperColor` / `toolboxPaperFace`. */
function sidebarToolboxPaperColor(fait: FaitMarquantView): string {
    const slug = statusForDragPayload(fait.fait_status?.name);

    if (isHexColor(fait.fait_status?.color)) {
        return fait.fait_status!.color!;
    }

    return stickyNotePaperColorFromStatus(slug);
}

/** Sous-titre département (parité avec sticky app : seulement « DNT »). */
function departmentSubtitle(name: string): string | null {
    if (name.trim().toUpperCase() === 'DNT') {
        return 'Direction des nouvelles technologies';
    }

    return null;
}

function sidebarToolboxPaperFace(fait: FaitMarquantView): string {
    const slug = statusForDragPayload(fait.fait_status?.name);

    if (isHexColor(fait.fait_status?.color)) {
        const h = fait.fait_status!.color!;

        return `linear-gradient(165deg, color-mix(in srgb, ${h} 42%, white), ${h})`;
    }

    return stickyNoteFaceGradientFromStatus(slug);
}

function sidebarFaitIsOnBoard(faitId: number): boolean {
    return boardProjectIdsOnBoard.value.has(faitId);
}

function sidebarFaitIsSelectedInDialog(faitId: number): boolean {
    return dialogOpen.value && editingId.value === faitId;
}

function collapseAll(): void {
    stickyBoardRef.value?.clearAllFaitsFromBoard?.();
}

function expandAll(): void {
    stickyBoardRef.value?.showAllFaitsOnBoard?.();
}

function toggleFab(): void {
    sidebarOpen.value = !sidebarOpen.value;
}

watch(wbMainView, (v) => {
    if (v === 'table') {
        sidebarOpen.value = false;
        stickyCreateOpen.value = false;
        void router.reload({
            only: ['faitsMarquants'],
            replace: true,
        });

        return;
    }

    tableHistoryFaitId.value = null;
});

function resetFaitFormToCreateDefaults(): void {
    editingId.value = null;
    form.clearErrors();
    form.reset();
    form.fait_status_id = props.faitStatuses[0]?.id ?? 1;
    form.status_id = props.workflowStatuses[0]?.id ?? 1;
    form.deadline = null;
    form.department_id = defaultFaitDepartmentId();
    form.responsable_action_id = defaultResponsableActionId(form.department_id);
    draftEtapes.value = [];
    draftCommentaires.value = [];
    listTab.value = 'etapes';
}

const tablePdfExporting = ref(false);

const FAIT_STATUS_HEX_FALLBACK: Record<number, string> = {
    1: '#16a34a',
    2: '#ca8a04',
    3: '#dc2626',
};

function faitStatusColorForExport(fait: FaitMarquantView, faitStatusId: number): string | undefined {
    const fromList = props.faitStatuses.find((s) => s.id === faitStatusId)?.color;

    if (isHexColor(fromList)) {
        return fromList;
    }

    const fromFait = fait.fait_status?.color;

    if (isHexColor(fromFait)) {
        return fromFait;
    }

    return FAIT_STATUS_HEX_FALLBACK[faitStatusId];
}

async function exportTableViewPdf(): Promise<void> {
    if (tablePdfExporting.value) {
        return;
    }

    const groups = tableViewGroups.value
        .filter((g) => g.faits.length > 0)
        .map((g) => ({
            title: g.title,
            rows: g.faits.map((fait) => {
                const draft = tableDraftFor(fait);

                return {
                    title: fait.title,
                    status: tableFaitStatusLabel(draft.fait_status_id),
                    statusColor: faitStatusColorForExport(fait, draft.fait_status_id),
                    responsable: fait.responsable_action?.name ?? '—',
                    createdAt: formatDateFr(faitCreatedAtIso(fait)),
                    deadline: formatDateFr(fait.deadline),
                };
            }),
        }));

    if (groups.length === 0) {
        toast.message('Aucun sujet à exporter avec les filtres actuels.');

        return;
    }

    tablePdfExporting.value = true;

    try {
        await downloadWhiteboardTablePdf({
            groups,
            weekRangeLabel: tableDateRangeLabel.value,
            meteoSummary: faitStatusMeteoSummary.value,
            kpiItems: faitStatusKpiItems.value.map((item) => {
                const statusId = Number(item.key.replace('fait-', ''));
                const opt = props.faitStatuses.find((s) => s.id === statusId);

                return {
                    label: item.label,
                    count: item.count,
                    statusColor: isHexColor(opt?.color) ? opt.color : undefined,
                };
            }),
        });
        toast.success('Rapport PDF téléchargé.');
    } catch {
        toast.error('Impossible de générer le PDF.');
    } finally {
        tablePdfExporting.value = false;
    }
}

function openCreate(): void {
    if (!showCreateFaitMarquant.value) {
        return;
    }

    if (wbMainView.value === 'board') {
        openCreateSticky();

        return;
    }

    stickyCreateOpen.value = false;
    editingId.value = null;
    resetFaitFormToCreateDefaults();
    dialogOpen.value = true;
}

function openCreateSticky(): void {
    dialogOpen.value = false;
    editingId.value = null;
    resetFaitFormToCreateDefaults();
    stickyCreateOpen.value = true;
}

function closeStickyCreate(): void {
    stickyCreateOpen.value = false;
    closeStickyFormStatusMenus();
    resetFaitFormToCreateDefaults();
}

function openEdit(id: number): void {
    const f = props.faitsMarquants.find((x) => x.id === id);

    if (!f) {
        return;
    }

    if (!userCanEditFaitMarquant(f)) {
        return;
    }

    tableHistoryFaitId.value = null;
    stickyCreateOpen.value = false;
    editingId.value = id;
    form.clearErrors();
    form.title = f.title;
    form.fait_status_id = f.fait_status_id;
    form.status_id = f.status_id;
    form.deadline = f.deadline ? f.deadline.slice(0, 10) : null;
    form.department_id = f.department_id;
    form.responsable_action_id = f.responsable_action_id;
    draftEtapes.value = (f.prochaines_etapes ?? [])
        .map((row) =>
            prochaineEtapeFromPivotRow(row, f.responsable_action_id, defaultEtapeStatusId.value),
        )
        .filter((row) => row.body.trim() !== '');
    draftCommentaires.value = (f.commentaires ?? []).map((c) => c.body);
    listTab.value = 'etapes';

    if (wbMainView.value === 'board') {
        dialogOpen.value = false;
        stickyCreateOpen.value = true;
        return;
    }

    dialogOpen.value = true;
}

function openEditFromSidebar(id: number): void {
    stickyBoardRef.value?.focusFaitOnBoard?.(id);
}

function submitFait(): void {
    if (showFaitFormDepartmentField.value && !form.department_id) {
        form.setError('department_id', 'Le département est obligatoire.');

        return;
    }

    if (!form.responsable_action_id) {
        form.setError('responsable_action_id', 'Le responsable action est obligatoire.');

        return;
    }

    if (editingId.value === null && draftEtapes.value.length === 0) {
        if (form.deadline === null || String(form.deadline).trim() === '') {
            form.setError('deadline', 'La date limite est obligatoire.');

            return;
        }
    }

    const opts = {
        preserveScroll: true,
        onSuccess: () => {
            dialogOpen.value = false;
            stickyCreateOpen.value = false;
            resetFaitFormToCreateDefaults();
        },
    };

    const transformed = form.transform((data) => {
        const payload: Record<string, unknown> = {
            ...data,
            prochaines_etapes: prochaineEtapesForSubmit(draftEtapes.value),
            commentaires: draftCommentaires.value.filter((s) => s.trim() !== ''),
        };

        if (editingId.value !== null && isDepartmentUser.value) {
            delete payload.deadline;
        } else if (draftEtapes.value.length > 0) {
            payload.deadline = null;
        }

        return payload;
    });

    if (editingId.value === null) {
        transformed.post('/faits-marquants', opts);
        return;
    }

    const endpoint = isDepartmentUser.value
        ? `/faits-marquants/${editingId.value}/draft`
        : `/faits-marquants/${editingId.value}`;
    transformed.put(endpoint, opts);
}

const formSaveButtonLabel = computed(() => {
    return isDepartmentUser.value ? 'Enregistrer brouillon' : 'Enregistrer';
});

/** Nombre de faits avec un brouillon non soumis (bouton « Soumettre tout » en tête de page). */
const pendingDraftSubmitCount = computed(() => {
    if (!isDepartmentUser.value) {
        return 0;
    }

    return props.faitsMarquants.filter(
        (f) => f.has_unsubmitted_draft === true && userCanEditFaitMarquant(f),
    ).length;
});

const bulkDraftSubmitting = ref(false);

function submitAllDraftsToOfficial(): void {
    if (!isDepartmentUser.value || pendingDraftSubmitCount.value === 0 || bulkDraftSubmitting.value) {
        return;
    }

    bulkDraftSubmitting.value = true;
    router.post(
        '/faits-marquants/drafts/submit-all',
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                bulkDraftSubmitting.value = false;
            },
        },
    );
}

function destroyFait(): void {
    if (editingId.value === null) {
        return;
    }

    form.delete(`/faits-marquants/${editingId.value}`, {
        preserveScroll: true,
        onSuccess: () => {
            dialogOpen.value = false;
            resetFaitFormToCreateDefaults();
        },
    });
}

const deadlineInput = computed({
    get: () => (form.deadline === null ? '' : form.deadline),
    set: (v: string) => {
        form.deadline = v === '' ? null : v;
    },
});

const selectedFaitStatus = computed((): FaitStatusOption | undefined =>
    props.faitStatuses.find((x) => x.id === form.fait_status_id),
);

const selectedWorkflowStatus = computed((): WorkflowStatusOption | undefined =>
    props.workflowStatuses.find((x) => x.id === form.status_id),
);

function faitStatusBadgeForOption(s: FaitStatusOption): {
    triggerClass: string;
    menuItemClass: string;
    style?: Record<string, string>;
} {
    if (isHexColor(s.color)) {
        const pill = badgePillFromStatus(s.color);
        const style: Record<string, string> = {
            backgroundColor: pill.backgroundColor,
            color: pill.color,
        };

        if (pill.boxShadow) {
            style.boxShadow = pill.boxShadow;
        }

        return {
            triggerClass: 'wb-fait-form-status-trigger--hex',
            menuItemClass: 'wb-fait-form-status-menu-item--hex',
            style,
        };
    }

    const slug = statusForDragPayload(s.name);

    return {
        triggerClass: `wb-fait-form-status-trigger--${slug}`,
        menuItemClass: `wb-fait-form-status-menu-item--${slug}`,
    };
}

function statusBadgeForFait(f: FaitMarquantView): ReturnType<typeof faitStatusBadgeForOption> {
    const s = f.fait_status;

    if (s && typeof s.id === 'number' && typeof s.name === 'string') {
        return faitStatusBadgeForOption(s);
    }

    return faitStatusBadgeForOption({ id: 0, name: 'Sous vigilance' });
}

function faitMarquantCreatorLabel(f: FaitMarquantView): string {
    const n = f.creator?.name;

    if (n !== undefined && String(n).trim() !== '') {
        return String(n).trim();
    }

    return '—';
}

const selectedFaitStatusBadge = computed(() => {
    const s = selectedFaitStatus.value ?? props.faitStatuses[0];

    if (!s) {
        return faitStatusBadgeForOption({ id: 0, name: 'Sous vigilance' });
    }

    return faitStatusBadgeForOption(s);
});

const selectedWorkflowStatusBadge = computed(() => {
    const w = selectedWorkflowStatus.value ?? props.workflowStatuses[0];

    if (!w) {
        return workflowStatusBadgeForOption({ id: 0, name: 'Ouvert' });
    }

    return workflowStatusBadgeForOption(w);
});

/** Variables CSS alignées sur les post-its du tableau (couleur = statut sélectionné). */
const stickyCreateShellStyle = computed((): Record<string, string> => {
    const s = selectedFaitStatus.value ?? props.faitStatuses[0];
    const slug = statusForDragPayload(s?.name);

    return {
        '--sticky-bg': stickyNotePaperColorFromStatus(slug),
        '--sticky-face': stickyNoteFaceGradientFromStatus(slug),
    };
});

const stickyCreateStatusTriggerClass = computed((): string => {
    const s = selectedFaitStatus.value ?? props.faitStatuses[0];
    return `sticky-note__status-trigger--${statusForDragPayload(s?.name)}`;
});

const stickyCreateStatusTriggerStyle = computed((): Record<string, string> | undefined => {
    return undefined;
});

function stickyCreateStatusMenuItemClass(s: FaitStatusOption): string {
    return `sticky-note__status-menu-item--${statusForDragPayload(s.name)}`;
}

function stickyCreateStatusMenuItemStyle(_s: FaitStatusOption): Record<string, string> | undefined {
    return undefined;
}

function stickyCreateWorkflowStatusBadge(w: WorkflowStatusOption): {
    triggerClass: string;
    menuItemClass: string;
    style?: Record<string, string>;
} {
    const badge = workflowStatusBadgeForOption(w);

    if (badge.triggerClass.includes('--hex')) {
        return {
            triggerClass: 'sticky-note__status-trigger',
            menuItemClass: 'sticky-note__status-menu-item',
            style: badge.style,
        };
    }

    const slug = workflowStatusSlug(w.name);

    return {
        triggerClass: `sticky-note__status-trigger--${slug}`,
        menuItemClass: `sticky-note__status-menu-item--${slug}`,
    };
}

const stickyCreateWorkflowStatusTriggerClass = computed((): string => {
    const w = selectedWorkflowStatus.value ?? props.workflowStatuses[0];

    if (!w) {
        return 'sticky-note__status-trigger--bon';
    }

    return stickyCreateWorkflowStatusBadge(w).triggerClass;
});

const stickyCreateWorkflowStatusTriggerStyle = computed((): Record<string, string> | undefined => {
    const w = selectedWorkflowStatus.value ?? props.workflowStatuses[0];

    if (!w) {
        return undefined;
    }

    return stickyCreateWorkflowStatusBadge(w).style;
});

function stickyCreateWorkflowStatusMenuItemClass(w: WorkflowStatusOption): string {
    return stickyCreateWorkflowStatusBadge(w).menuItemClass;
}

function stickyCreateWorkflowStatusMenuItemStyle(w: WorkflowStatusOption): Record<string, string> | undefined {
    return stickyCreateWorkflowStatusBadge(w).style;
}

const faitStatusMenuOpen = ref(false);
const workflowStatusMenuOpen = ref(false);
const faitStatusDropdownRoot = ref<HTMLElement | null>(null);
const workflowStatusDropdownRoot = ref<HTMLElement | null>(null);
const stickyCreateFaitStatusDropdownRoot = ref<HTMLElement | null>(null);
const stickyCreateWorkflowStatusDropdownRoot = ref<HTMLElement | null>(null);

function closeFaitStatusMenu(): void {
    faitStatusMenuOpen.value = false;
}

function closeWorkflowStatusMenu(): void {
    workflowStatusMenuOpen.value = false;
}

function closeStickyFormStatusMenus(): void {
    closeFaitStatusMenu();
    closeWorkflowStatusMenu();
}

function toggleFaitStatusMenu(): void {
    if (!faitStatusMenuOpen.value) {
        closeWorkflowStatusMenu();
    }

    faitStatusMenuOpen.value = !faitStatusMenuOpen.value;
}

function toggleWorkflowStatusMenu(): void {
    if (!workflowStatusMenuOpen.value) {
        closeFaitStatusMenu();
    }

    workflowStatusMenuOpen.value = !workflowStatusMenuOpen.value;
}

function onStickyFormStatusPointerDownOutside(ev: Event): void {
    if (!faitStatusMenuOpen.value && !workflowStatusMenuOpen.value) {
        return;
    }

    const target = ev.target;

    if (!(target instanceof Node)) {
        return;
    }

    if (faitStatusDropdownRoot.value?.contains(target)) {
        return;
    }

    if (workflowStatusDropdownRoot.value?.contains(target)) {
        return;
    }

    if (stickyCreateFaitStatusDropdownRoot.value?.contains(target)) {
        return;
    }

    if (stickyCreateWorkflowStatusDropdownRoot.value?.contains(target)) {
        return;
    }

    closeStickyFormStatusMenus();
}

function onStickyFormStatusKeydownEscape(ev: KeyboardEvent): void {
    if (ev.key !== 'Escape') {
        return;
    }

    if (workflowStatusMenuOpen.value) {
        ev.stopPropagation();
        closeWorkflowStatusMenu();

        return;
    }

    if (faitStatusMenuOpen.value) {
        ev.stopPropagation();
        closeFaitStatusMenu();
    }
}

function bindStickyFormStatusMenuDismiss(): void {
    document.addEventListener('pointerdown', onStickyFormStatusPointerDownOutside, true);
    document.addEventListener('keydown', onStickyFormStatusKeydownEscape, true);
}

function unbindStickyFormStatusMenuDismiss(): void {
    document.removeEventListener('pointerdown', onStickyFormStatusPointerDownOutside, true);
    document.removeEventListener('keydown', onStickyFormStatusKeydownEscape, true);
}

const stickyFormStatusMenuOpen = computed(
    () => faitStatusMenuOpen.value || workflowStatusMenuOpen.value,
);

watch(stickyFormStatusMenuOpen, (open) => {
    if (open) {
        bindStickyFormStatusMenuDismiss();
    } else {
        unbindStickyFormStatusMenuDismiss();
    }
});

function onFaitFormDrawerEscape(ev: KeyboardEvent): void {
    if (ev.key !== 'Escape' || !dialogOpen.value) {
        return;
    }

    dialogOpen.value = false;
}

function onStickyCreateEscape(ev: KeyboardEvent): void {
    if (ev.key !== 'Escape' || !stickyCreateOpen.value || stickyFormStatusMenuOpen.value) {
        return;
    }

    closeStickyCreate();
}

watch(dialogOpen, (open) => {
    if (open) {
        document.addEventListener('keydown', onFaitFormDrawerEscape);
    } else {
        document.removeEventListener('keydown', onFaitFormDrawerEscape);
        editingId.value = null;
        closeStickyFormStatusMenus();
    }
});

watch(stickyCreateOpen, (open) => {
    if (open) {
        document.addEventListener('keydown', onStickyCreateEscape);
    } else {
        document.removeEventListener('keydown', onStickyCreateEscape);
        closeStickyFormStatusMenus();
    }
});

function pickFaitStatus(id: number): void {
    form.fait_status_id = id;
    closeFaitStatusMenu();
}

function pickWorkflowStatus(id: number): void {
    form.status_id = id;
    closeWorkflowStatusMenu();
}

function flushOnLogout(): void {
    router.flushAll();
}

onUnmounted(() => {
    if (stickyFormStatusMenuOpen.value) {
        unbindStickyFormStatusMenuDismiss();
    }

    document.removeEventListener('keydown', onFaitFormDrawerEscape);
    document.removeEventListener('keydown', onStickyCreateEscape);
    unbindTableBadgeDismiss();
});
</script>

<template>
    <Head title="Tableau blanc" />

    <div
        class="flex w-full flex-col bg-background text-foreground"
        :class="wbMainView === 'board' ? 'fixed inset-0 z-0 min-h-dvh' : 'min-h-dvh'"
    >
        <header
            v-if="wbMainView === 'board'"
            class="wb-top-header pointer-events-none absolute inset-x-0 top-3 z-30 px-3 select-none sm:top-5 sm:px-4 lg:px-6"
        >
            <div
                class="wb-top-header__row"
                :class="{ 'wb-top-header__row--with-submit': isDepartmentUser && pendingDraftSubmitCount > 0 }"
            >
                <div class="wb-top-header__brand">
                    <img
                        src="/logopmo.png"
                        alt="PMOLINK"
                        class="h-12 w-auto max-h-12 shrink-0 object-contain object-left md:h-14 md:max-h-14"
                    />
                </div>

                <div
                    class="wb-top-header__center pointer-events-auto"
                    role="toolbar"
                    aria-label="Affichage et filtres"
                >
                    <div class="wb-view-toolbar">
                        <div
                            class="wb-view-switch"
                            role="group"
                            aria-label="Choisir l'affichage : cartes ou tableau"
                        >
                            <button
                                type="button"
                                class="wb-view-switch-btn"
                                :class="{ 'wb-view-switch-btn--active': wbMainView === 'board' }"
                                :aria-pressed="wbMainView === 'board'"
                                aria-label="Vue cartes — post-its sur le tableau"
                                title="Vue cartes"
                                @click="wbMainView = 'board'"
                            >
                                <LayoutGrid class="size-[18px]" :stroke-width="2" aria-hidden="true" />
                            </button>
                            <button
                                type="button"
                                class="wb-view-switch-btn"
                                :aria-pressed="false"
                                aria-label="Vue tableau — sujets"
                                title="Vue tableau"
                                @click="wbMainView = 'table'"
                            >
                                <Table2 class="size-[18px]" :stroke-width="2" aria-hidden="true" />
                            </button>
                        </div>
                        <template v-if="showDepartmentFilterPills">
                            <span class="wb-view-toolbar-sep" aria-hidden="true" />
                            <DepartmentFilterStrip
                                v-model="departmentFilterId"
                                :departments="departments"
                                :user-department-ids="currentUserDepartmentIds"
                                :highlight-own="isDepartmentUser"
                            />
                        </template>
                    </div>
                </div>

                <div class="wb-top-header__actions pointer-events-auto">
                    <div class="wb-top-header__actions-row">
                        <button
                            v-if="isDepartmentUser && pendingDraftSubmitCount > 0"
                            type="button"
                            class="wb-header-submit-btn inline-flex h-10 shrink-0 items-center justify-center gap-1.5 rounded-lg border border-violet-300 bg-background/95 px-3 text-sm font-semibold text-violet-700 shadow-sm backdrop-blur-sm transition hover:bg-violet-50 focus-visible:ring-2 focus-visible:ring-violet-500/40 focus-visible:ring-offset-2 focus-visible:outline-none disabled:pointer-events-none disabled:opacity-50 dark:border-violet-700 dark:bg-background/90 dark:text-violet-200 dark:hover:bg-violet-950/50"
                            :disabled="bulkDraftSubmitting"
                            :aria-busy="bulkDraftSubmitting"
                            :aria-label="`Soumettre tous les brouillons vers les tables officielles (${pendingDraftSubmitCount})`"
                            @click="submitAllDraftsToOfficial"
                        >
                            <span>{{ bulkDraftSubmitting ? 'Soumission…' : 'Soumettre tout' }}</span>
                            <span class="tabular-nums opacity-90">({{ pendingDraftSubmitCount }})</span>
                        </button>
                        <Link
                            :href="logout()"
                            as="button"
                            type="button"
                            data-test="logout-button"
                            class="inline-flex h-10 shrink-0 items-center justify-center gap-2 rounded-lg border border-red-200/90 bg-background/90 px-3 text-sm font-semibold text-red-600 shadow-sm backdrop-blur-sm transition hover:border-red-300 hover:bg-red-50 hover:text-red-700 dark:border-red-800/70 dark:bg-red-950/50 dark:text-red-200 dark:hover:border-red-600 dark:hover:bg-red-900/55"
                            @click="flushOnLogout"
                        >
                            <LogOut class="size-4 shrink-0" :stroke-width="2.25" aria-hidden="true" />
                            <span class="max-sm:sr-only">Déconnexion</span>
                        </Link>
                    </div>
                    <WhiteboardOrgSwitcher />
                </div>
            </div>

            <div class="wb-top-header__meta">
                <p
                    class="wb-top-header__date ml-4 mt-1 w-fit rounded-md bg-background/85 px-3 py-1.5 text-xs leading-snug text-black shadow-sm ring-1 ring-border/60 backdrop-blur-sm dark:text-white sm:mt-1.5"
                >
                    {{ weekRangeLabel }}
                </p>
                <div class="pointer-events-auto ml-4 mt-2 flex w-full max-w-full flex-row flex-wrap items-center gap-2">
                    <WeeklyMeteoAllDepartmentsDonut
                        class="shrink-0"
                        :total="faitStatusMeteoSummary.total"
                        :meteo1="faitStatusMeteoSummary.meteo1"
                        :meteo2="faitStatusMeteoSummary.meteo2"
                        :meteo3="faitStatusMeteoSummary.meteo3"
                    />
                </div>
            </div>
        </header>

        <div v-show="wbMainView === 'board'" class="flex min-h-0 min-w-0 flex-1 flex-col">
            <StickyBoard
                ref="stickyBoardRef"
                fullscreen
                class="min-h-0 flex-1"
                :board-visible="wbMainView === 'board'"
                :faits-marquants="filteredFaitsMarquants"
                :is-fait-editable="isFaitEditableForStickyBoard"
                @edit="openEdit"
                @board-project-ids="onBoardProjectIds"
            />
        </div>

        <div v-show="wbMainView === 'table'" class="wb-general-view flex min-h-dvh w-full flex-col">
            <div
                class="wb-table-chrome relative w-full shrink-0"
                :class="wbTableChromeMinHeightClass"
            >
                <header class="wb-top-header pointer-events-none absolute inset-x-0 top-3 z-30 px-3 select-none sm:top-5 sm:px-4 lg:px-6">
                    <div
                        class="wb-top-header__row"
                        :class="{ 'wb-top-header__row--with-submit': isDepartmentUser && pendingDraftSubmitCount > 0 }"
                    >
                        <div class="wb-top-header__brand">
                            <img
                                src="/logopmo.png"
                                alt="PMOLINK"
                                class="h-12 w-auto max-h-12 shrink-0 object-contain object-left md:h-14 md:max-h-14"
                            />
                        </div>

                        <div
                            class="wb-top-header__center pointer-events-auto"
                            role="toolbar"
                            aria-label="Affichage et filtres"
                        >
                            <div class="wb-view-toolbar">
                                <div
                                    class="wb-view-switch"
                                    role="group"
                                    aria-label="Choisir l'affichage : cartes ou tableau"
                                >
                                    <button
                                        type="button"
                                        class="wb-view-switch-btn"
                                        :class="{ 'wb-view-switch-btn--active': wbMainView === 'board' }"
                                        :aria-pressed="wbMainView === 'board'"
                                        aria-label="Vue cartes — post-its sur le tableau"
                                        title="Vue cartes"
                                        @click="wbMainView = 'board'"
                                    >
                                        <LayoutGrid class="size-[18px]" :stroke-width="2" aria-hidden="true" />
                                    </button>
                                    <button
                                        type="button"
                                        class="wb-view-switch-btn"
                                        :class="{ 'wb-view-switch-btn--active': wbMainView === 'table' }"
                                        :aria-pressed="wbMainView === 'table'"
                                        aria-label="Vue tableau — sujets"
                                        title="Vue tableau"
                                        @click="wbMainView = 'table'"
                                    >
                                        <Table2 class="size-[18px]" :stroke-width="2" aria-hidden="true" />
                                    </button>
                                </div>
                                <template v-if="showDepartmentFilterPills">
                                    <span class="wb-view-toolbar-sep" aria-hidden="true" />
                                    <DepartmentFilterStrip
                                        v-model="departmentFilterId"
                                        :departments="departments"
                                        :user-department-ids="currentUserDepartmentIds"
                                        :highlight-own="isDepartmentUser"
                                    />
                                </template>
                            </div>
                        </div>

                        <div class="wb-top-header__actions pointer-events-auto">
                            <div class="wb-top-header__actions-row">
                                <button
                                    v-if="isDepartmentUser && pendingDraftSubmitCount > 0"
                                    type="button"
                                    class="wb-header-submit-btn inline-flex h-10 shrink-0 items-center justify-center gap-1.5 rounded-lg border border-violet-300 bg-background/95 px-3 text-sm font-semibold text-violet-700 shadow-sm backdrop-blur-sm transition hover:bg-violet-50 focus-visible:ring-2 focus-visible:ring-violet-500/40 focus-visible:ring-offset-2 focus-visible:outline-none disabled:pointer-events-none disabled:opacity-50 dark:border-violet-700 dark:bg-background/90 dark:text-violet-200 dark:hover:bg-violet-950/50"
                                    :disabled="bulkDraftSubmitting"
                                    :aria-busy="bulkDraftSubmitting"
                                    :aria-label="`Soumettre tous les brouillons vers les tables officielles (${pendingDraftSubmitCount})`"
                                    @click="submitAllDraftsToOfficial"
                                >
                                    <span>{{ bulkDraftSubmitting ? 'Soumission…' : 'Soumettre tout' }}</span>
                                    <span class="tabular-nums opacity-90">({{ pendingDraftSubmitCount }})</span>
                                </button>
                                <Link
                                    :href="logout()"
                                    as="button"
                                    type="button"
                                    data-test="logout-button"
                                    class="inline-flex h-10 shrink-0 items-center justify-center gap-2 rounded-lg border border-red-200/90 bg-background/90 px-3 text-sm font-semibold text-red-600 shadow-sm backdrop-blur-sm transition hover:border-red-300 hover:bg-red-50 hover:text-red-700 dark:border-red-800/70 dark:bg-red-950/50 dark:text-red-200 dark:hover:border-red-600 dark:hover:bg-red-900/55"
                                    @click="flushOnLogout"
                                >
                                    <LogOut class="size-4 shrink-0" :stroke-width="2.25" aria-hidden="true" />
                                    <span class="max-sm:sr-only">Déconnexion</span>
                                </Link>
                            </div>
                            <WhiteboardOrgSwitcher />
                        </div>
                    </div>

                    <div class="wb-top-header__meta">
                        <p
                            class="wb-top-header__date ml-4 mt-1 w-fit rounded-md bg-background/85 px-3 py-1.5 text-xs leading-snug text-black shadow-sm ring-1 ring-border/60 backdrop-blur-sm dark:text-white sm:mt-1.5"
                        >
                            {{ tableDateRangeLabel }}
                        </p>
                    </div>
                </header>
            </div>

            <div
                class="wb-general-inner w-full max-w-none flex-1 px-4 pb-8 sm:px-6 lg:px-10"
                :class="wbTableInnerTopClass"
            >
                <WhiteboardMeteoKpiRow
                    class="mt-0.5 mb-4 sm:mt-1"
                    :total="faitStatusMeteoSummary.total"
                    :meteo1="faitStatusMeteoSummary.meteo1"
                    :meteo2="faitStatusMeteoSummary.meteo2"
                    :meteo3="faitStatusMeteoSummary.meteo3"
                    :kpi-items="faitStatusKpiItems"
                />

                <div
                    class="mb-6 flex w-full flex-col items-start gap-3 sm:mb-7 sm:flex-row sm:items-end sm:justify-between sm:gap-4"
                >
                    <div
                        v-if="showDepartmentFilter"
                        class="wb-table-filter-toolbar flex w-fit max-w-full min-w-0 flex-col gap-2.5 sm:flex-row sm:flex-wrap sm:items-end sm:gap-x-3 sm:gap-y-2"
                        role="search"
                        aria-label="Filtres du tableau"
                    >
                        <div
                            class="flex items-center gap-2 sm:self-center sm:border-r sm:border-border/40 sm:pr-3"
                        >
                            <SlidersHorizontal
                                class="size-3.5 shrink-0 text-muted-foreground"
                                :stroke-width="2"
                                aria-hidden="true"
                            />
                            <span class="text-xs font-semibold text-foreground/90">Filtres</span>
                        </div>
                        <div
                            class="flex w-fit max-w-full flex-col gap-2.5 sm:flex-row sm:flex-wrap sm:items-end sm:gap-x-3 sm:gap-y-2"
                        >
                            <label class="flex w-fit flex-col gap-1">
                                <span
                                    class="text-[10px] font-bold uppercase tracking-wide text-muted-foreground"
                                    >Du</span
                                >
                                <input
                                    v-model="ownerTableFilterDateStart"
                                    type="date"
                                    class="wb-table-filter-select h-9 min-w-[10rem] max-w-[14rem] rounded-md border border-input bg-background px-2.5 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-violet-500/35 dark:bg-background"
                                    aria-label="Date de début"
                                />
                            </label>
                            <label class="flex w-fit flex-col gap-1">
                                <span
                                    class="text-[10px] font-bold uppercase tracking-wide text-muted-foreground"
                                    >Au</span
                                >
                                <input
                                    v-model="ownerTableFilterDateEnd"
                                    type="date"
                                    class="wb-table-filter-select h-9 min-w-[10rem] max-w-[14rem] rounded-md border border-input bg-background px-2.5 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-violet-500/35 dark:bg-background"
                                    aria-label="Date de fin"
                                />
                            </label>
                            <label class="flex w-fit flex-col gap-1">
                                <span
                                    class="text-[10px] font-bold uppercase tracking-wide text-muted-foreground"
                                    >État</span
                                >
                                <select
                                    class="wb-table-filter-select h-9 min-w-[10rem] max-w-[14rem] rounded-md border border-input bg-background px-2.5 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-violet-500/35 dark:bg-background"
                                    aria-label="Filtrer par état (ouvert, clôturé, archivé)"
                                    :value="ownerTableFilterWorkflowId ?? ''"
                                    @change="
                                        ownerTableFilterWorkflowId =
                                            ($event.target as HTMLSelectElement).value === ''
                                                ? null
                                                : Number(($event.target as HTMLSelectElement).value)
                                    "
                                >
                                    <option value="">Tous</option>
                                    <option v-for="w in workflowStatuses" :key="`wf-s-${w.id}`" :value="w.id">
                                        {{ w.name }}
                                    </option>
                                </select>
                            </label>
                            <label class="flex w-fit flex-col gap-1">
                                <span
                                    class="text-[10px] font-bold uppercase tracking-wide text-muted-foreground"
                                    >Statut</span
                                >
                                <select
                                    class="wb-table-filter-select h-9 min-w-[10rem] max-w-[14rem] rounded-md border border-input bg-background px-2.5 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-violet-500/35 dark:bg-background"
                                    aria-label="Filtrer par statut"
                                    :value="ownerTableFilterFaitStatusId ?? ''"
                                    @change="
                                        ownerTableFilterFaitStatusId =
                                            ($event.target as HTMLSelectElement).value === ''
                                                ? null
                                                : Number(($event.target as HTMLSelectElement).value)
                                    "
                                >
                                    <option value="">Tous</option>
                                    <option v-for="s in faitStatuses" :key="`fs-s-${s.id}`" :value="s.id">
                                        {{ s.name }}
                                    </option>
                                </select>
                            </label>
                        </div>
                        <button
                            v-if="ownerTableFiltersActive"
                            type="button"
                            class="inline-flex h-9 shrink-0 items-center justify-center gap-1.5 self-start rounded-md border border-transparent px-2 text-xs font-semibold text-violet-700 transition hover:bg-violet-50 hover:text-violet-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-violet-500/35 sm:self-end dark:text-violet-300 dark:hover:bg-violet-950/40 dark:hover:text-violet-100"
                            @click="resetOwnerTableFilters"
                        >
                            <X class="size-3.5" :stroke-width="2.5" aria-hidden="true" />
                            Effacer
                        </button>
                    </div>
                    <div class="ml-auto flex shrink-0 items-center justify-end gap-1.5 sm:pb-0.5">
                        <button
                            type="button"
                            class="inline-flex size-8 shrink-0 items-center justify-center rounded-lg border border-red-200/90 bg-background text-red-600 shadow-sm transition hover:border-red-300 hover:bg-red-50 hover:text-red-700 focus-visible:ring-2 focus-visible:ring-red-500/40 focus-visible:ring-offset-2 focus-visible:outline-none disabled:pointer-events-none disabled:opacity-50 dark:border-red-800/70 dark:bg-background/90 dark:text-red-300 dark:hover:border-red-600 dark:hover:bg-red-950/45 dark:hover:text-red-200"
                            :disabled="tablePdfExporting"
                            :aria-busy="tablePdfExporting"
                            aria-label="Exporter en PDF"
                            title="Exporter en PDF"
                            @click="exportTableViewPdf"
                        >
                            <FileText class="size-4 shrink-0" :stroke-width="2.25" aria-hidden="true" />
                        </button>
                        <button
                            v-if="showCreateFaitMarquant"
                            type="button"
                            class="inline-flex size-8 shrink-0 items-center justify-center rounded-lg bg-violet-600 text-white shadow-md transition hover:bg-violet-700 focus-visible:ring-2 focus-visible:ring-violet-500/50 focus-visible:ring-offset-2 focus-visible:outline-none dark:hover:bg-violet-500"
                            aria-label="Ajouter un sujet"
                            title="Ajouter un sujet"
                            @click="openCreate"
                        >
                            <Plus class="size-4 shrink-0" :stroke-width="2.5" aria-hidden="true" />
                        </button>
                    </div>
                </div>

                <template v-for="grp in tableViewGroups" :key="grp.title">
                    <section v-if="grp.faits.length > 0" class="wb-general-dept mb-8 last:mb-0">
                        <h2 class="wb-general-dept-title">
                            {{ grp.title.toUpperCase() }}
                            <span class="wb-general-dept-count">({{ grp.faits.length }})</span>
                        </h2>
                        <div class="wb-general-table-wrap">
                            <table class="wb-general-table">
                                <thead class="wb-general-table__head">
                                    <tr class="wb-general-table__head-row">
                                        <th class="wb-general-th wb-general-th--title">Sujet</th>
                                        <th class="wb-general-th wb-general-th--pivot">Prochaines étapes</th>
                                        <th class="wb-general-th wb-general-th--pivot">Commentaires</th>
                                        <th class="wb-general-th wb-general-th--fait-status">Statut</th>
                                        <th class="wb-general-th wb-general-th--responsable">Responsable action</th>
                                        <th class="wb-general-th wb-general-th--date">Date création</th>
                                        <th class="wb-general-th wb-general-th--deadline">Deadline</th>
                                        <th class="wb-general-th wb-general-th--action">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="fait in grp.faits"
                                        :key="fait.id"
                                        class="wb-general-table__row"
                                        :class="tableViewRowClass(tableDraftFor(fait).status_id)"
                                    >
                                        <td class="wb-general-td wb-general-td--title">
                                            <div class="wb-general-title-cell">
                                                <span class="wb-general-title-text">{{ fait.title }}</span>
                                                <span
                                                    v-if="fait.has_unsubmitted_draft === true"
                                                    class="wb-general-draft-badge"
                                                >
                                                    Brouillon
                                                </span>
                                            </div>
                                        </td>
                                        <td
                                            class="wb-general-td wb-general-td--pivot wb-general-td--pivot-etapes"
                                            :class="{
                                                'wb-general-td--pivot-saving':
                                                    tableInlineSaving[fait.id] === true &&
                                                    isTablePivotEditing(fait.id, 'prochaines_etapes'),
                                            }"
                                        >
                                            <div
                                                class="wb-general-pivot-cell"
                                                :class="{
                                                    'wb-general-pivot-cell--editing': isTablePivotEditing(
                                                        fait.id,
                                                        'prochaines_etapes',
                                                    ),
                                                }"
                                            >
                                                <div
                                                    class="wb-pivot-accordion wb-pivot-accordion--etapes"
                                                    :class="{
                                                        'wb-pivot-accordion--open': isTablePivotAccordionExpanded(
                                                            fait.id,
                                                            'prochaines_etapes',
                                                        ),
                                                    }"
                                                >
                                                    <div class="wb-pivot-accordion__header">
                                                        <button
                                                            type="button"
                                                            class="wb-pivot-accordion__trigger"
                                                            :aria-expanded="
                                                                isTablePivotAccordionExpanded(
                                                                    fait.id,
                                                                    'prochaines_etapes',
                                                                )
                                                            "
                                                            @click.stop="
                                                                toggleTablePivotAccordion(
                                                                    fait.id,
                                                                    'prochaines_etapes',
                                                                )
                                                            "
                                                        >
                                                            <span class="wb-pivot-accordion__icon" aria-hidden="true">
                                                                <ListTodo class="size-3.5" :stroke-width="2.25" />
                                                            </span>
                                                            <span class="wb-pivot-accordion__copy">
                                                                <span class="wb-pivot-accordion__label">{{
                                                                    tablePivotAccordionTitle('prochaines_etapes')
                                                                }}</span>
                                                                <span
                                                                    v-if="isTablePivotEditing(fait.id, 'prochaines_etapes')"
                                                                    class="wb-pivot-accordion__state wb-pivot-accordion__state--edit"
                                                                >Édition</span>
                                                                <span
                                                                    v-else
                                                                    class="wb-pivot-accordion__badge"
                                                                    :class="{
                                                                        'wb-pivot-accordion__badge--empty':
                                                                            tableEtapesDisplayRows(fait).length === 0,
                                                                    }"
                                                                >{{
                                                                    tablePivotAccordionSummary(
                                                                        fait,
                                                                        'prochaines_etapes',
                                                                    )
                                                                }}</span>
                                                            </span>
                                                            <ChevronDown
                                                                class="wb-pivot-accordion__chev size-4 shrink-0"
                                                                :class="{
                                                                    'wb-pivot-accordion__chev--open':
                                                                        isTablePivotAccordionExpanded(
                                                                            fait.id,
                                                                            'prochaines_etapes',
                                                                        ),
                                                                }"
                                                                :stroke-width="2.5"
                                                                aria-hidden="true"
                                                            />
                                                        </button>
                                                        <button
                                                            v-if="
                                                                !isTablePivotEditing(fait.id, 'prochaines_etapes') &&
                                                                !tableRowEditorDisabled(fait)
                                                            "
                                                            type="button"
                                                            class="wb-pivot-accordion__action"
                                                            :aria-label="
                                                                tableEtapesDisplayRows(fait).length === 0
                                                                    ? 'Ajouter une prochaine étape'
                                                                    : 'Modifier les prochaines étapes'
                                                            "
                                                            @click.stop="
                                                                startTablePivotAccordionEdit(
                                                                    fait.id,
                                                                    'prochaines_etapes',
                                                                )
                                                            "
                                                        >
                                                            <Pencil
                                                                v-if="tableEtapesDisplayRows(fait).length > 0"
                                                                class="size-3.5 shrink-0"
                                                                :stroke-width="2.25"
                                                                aria-hidden="true"
                                                            />
                                                            <Plus
                                                                v-else
                                                                class="size-3.5 shrink-0"
                                                                :stroke-width="2.25"
                                                                aria-hidden="true"
                                                            />
                                                        </button>
                                                    </div>
                                                    <div
                                                        v-show="
                                                            isTablePivotAccordionExpanded(
                                                                fait.id,
                                                                'prochaines_etapes',
                                                            )
                                                        "
                                                        class="wb-pivot-accordion__panel"
                                                    >
                                                <template
                                                    v-if="isTablePivotEditing(fait.id, 'prochaines_etapes')"
                                                >
                                                    <div class="wb-nested-pivot-wrap">
                                                        <table
                                                            class="wb-nested-pivot-table wb-nested-pivot-table--edit wb-nested-pivot-table--etapes"
                                                            aria-label="Édition prochaines étapes"
                                                        >
                                                            <colgroup>
                                                                <col class="wb-nested-pivot-col wb-nested-pivot-col--num" />
                                                                <col class="wb-nested-pivot-col wb-nested-pivot-col--body" />
                                                                <col class="wb-nested-pivot-col wb-nested-pivot-col--responsable" />
                                                                <col class="wb-nested-pivot-col wb-nested-pivot-col--deadline" />
                                                                <col class="wb-nested-pivot-col wb-nested-pivot-col--status" />
                                                                <col class="wb-nested-pivot-col wb-nested-pivot-col--action" />
                                                            </colgroup>
                                                            <thead>
                                                                <tr>
                                                                    <th class="wb-nested-pivot-th wb-nested-pivot-th--num">#</th>
                                                                    <th class="wb-nested-pivot-th wb-nested-pivot-th--body">Étape</th>
                                                                    <th class="wb-nested-pivot-th wb-nested-pivot-th--responsable">Responsable</th>
                                                                    <th class="wb-nested-pivot-th wb-nested-pivot-th--deadline">Deadline</th>
                                                                    <th class="wb-nested-pivot-th wb-nested-pivot-th--status">Statut</th>
                                                                    <th class="wb-nested-pivot-th wb-nested-pivot-th--action" />
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <tr
                                                                    v-for="(etape, i) in tableEtapeEditLines(fait.id)"
                                                                    :key="`${fait.id}-etape-edit-${i}`"
                                                                    class="wb-nested-pivot-row"
                                                                >
                                                                    <td class="wb-nested-pivot-td wb-nested-pivot-td--num">{{ i + 1 }}</td>
                                                                    <td class="wb-nested-pivot-td wb-nested-pivot-td--body">
                                                                        <textarea
                                                                            :ref="
                                                                                (el) =>
                                                                                    syncTableNestedTextarea(
                                                                                        el as HTMLTextAreaElement | null,
                                                                                    )
                                                                            "
                                                                            :value="etape.body"
                                                                            class="wb-nested-pivot-text wb-nested-pivot-text--edit"
                                                                            rows="1"
                                                                            spellcheck="false"
                                                                            :placeholder="
                                                                                i === 0
                                                                                    ? 'Saisir une prochaine étape…'
                                                                                    : `Étape ${i + 1}`
                                                                            "
                                                                            @input="onTableEtapeBodyInput(fait.id, i, $event)"
                                                                        />
                                                                    </td>
                                                                    <td class="wb-nested-pivot-td wb-nested-pivot-td--responsable">
                                                                        <select
                                                                            class="wb-nested-pivot-control wb-nested-pivot-control--select wb-nested-pivot-control--compact"
                                                                            :value="etape.responsable_action_id"
                                                                            @change="
                                                                                onTableEtapeFieldInput(
                                                                                    fait.id,
                                                                                    i,
                                                                                    'responsable_action_id',
                                                                                    Number(($event.target as HTMLSelectElement).value),
                                                                                )
                                                                            "
                                                                        >
                                                                            <option
                                                                                v-for="u in actionResponsibles"
                                                                                :key="`etape-resp-${fait.id}-${i}-${u.id}`"
                                                                                :value="u.id"
                                                                            >
                                                                                {{ u.name }}
                                                                            </option>
                                                                        </select>
                                                                    </td>
                                                                    <td class="wb-nested-pivot-td wb-nested-pivot-td--deadline">
                                                                        <input
                                                                            :value="etape.deadline ?? ''"
                                                                            type="date"
                                                                            class="wb-nested-pivot-control wb-nested-pivot-control--compact"
                                                                            @input="
                                                                                onTableEtapeFieldInput(
                                                                                    fait.id,
                                                                                    i,
                                                                                    'deadline',
                                                                                    ($event.target as HTMLInputElement).value,
                                                                                )
                                                                            "
                                                                        />
                                                                    </td>
                                                                    <td class="wb-nested-pivot-td wb-nested-pivot-td--status">
                                                                        <select
                                                                            class="wb-nested-pivot-control wb-nested-pivot-control--select wb-nested-pivot-control--compact"
                                                                            :value="etape.etape_status_id"
                                                                            @change="
                                                                                onTableEtapeFieldInput(
                                                                                    fait.id,
                                                                                    i,
                                                                                    'etape_status_id',
                                                                                    Number(($event.target as HTMLSelectElement).value),
                                                                                )
                                                                            "
                                                                        >
                                                                            <option
                                                                                v-for="s in etapeStatuses"
                                                                                :key="`etape-st-${fait.id}-${i}-${s.id}`"
                                                                                :value="s.id"
                                                                            >
                                                                                {{ s.name }}
                                                                            </option>
                                                                        </select>
                                                                    </td>
                                                                    <td class="wb-nested-pivot-td wb-nested-pivot-td--action">
                                                                        <button
                                                                            v-if="tableEtapeLineCanRemove(tableEtapeEditLines(fait.id), i)"
                                                                            type="button"
                                                                            class="wb-nested-pivot-row-remove"
                                                                            :aria-label="`Supprimer l'étape ${i + 1}`"
                                                                            @click="removeTableEtapeEditLine(fait.id, i)"
                                                                        >
                                                                            <X class="size-3.5" :stroke-width="2.5" aria-hidden="true" />
                                                                        </button>
                                                                    </td>
                                                                </tr>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                    <div class="wb-general-pivot-edit-actions">
                                                        <button
                                                            type="button"
                                                            class="wb-general-pivot-edit-cancel"
                                                            :disabled="tableInlineSaving[fait.id] === true"
                                                            @click="cancelTablePivotEdit"
                                                        >
                                                            Annuler
                                                        </button>
                                                        <button
                                                            type="button"
                                                            class="wb-general-pivot-edit-save"
                                                            :disabled="tableInlineSaving[fait.id] === true"
                                                            @click="saveTablePivotEdit"
                                                        >
                                                            <Check
                                                                class="size-3.5 shrink-0"
                                                                :stroke-width="2.5"
                                                                aria-hidden="true"
                                                            />
                                                            Enregistrer
                                                        </button>
                                                    </div>
                                                </template>
                                                <template v-else>
                                                    <div class="wb-nested-pivot-wrap">
                                                        <table
                                                            class="wb-nested-pivot-table wb-nested-pivot-table--read wb-nested-pivot-table--etapes"
                                                            aria-label="Prochaines étapes"
                                                        >
                                                            <colgroup>
                                                                <col class="wb-nested-pivot-col wb-nested-pivot-col--num" />
                                                                <col class="wb-nested-pivot-col wb-nested-pivot-col--body" />
                                                                <col class="wb-nested-pivot-col wb-nested-pivot-col--responsable" />
                                                                <col class="wb-nested-pivot-col wb-nested-pivot-col--deadline" />
                                                                <col class="wb-nested-pivot-col wb-nested-pivot-col--status" />
                                                            </colgroup>
                                                            <thead>
                                                                <tr>
                                                                    <th class="wb-nested-pivot-th wb-nested-pivot-th--num">#</th>
                                                                    <th class="wb-nested-pivot-th wb-nested-pivot-th--body">Étape</th>
                                                                    <th class="wb-nested-pivot-th wb-nested-pivot-th--responsable">Responsable</th>
                                                                    <th class="wb-nested-pivot-th wb-nested-pivot-th--deadline">Deadline</th>
                                                                    <th class="wb-nested-pivot-th wb-nested-pivot-th--status">Statut</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <tr v-if="tableEtapesDisplayRows(fait).length === 0">
                                                                    <td colspan="5" class="wb-nested-pivot-empty">
                                                                        Aucune prochaine étape
                                                                    </td>
                                                                </tr>
                                                                <tr
                                                                    v-for="(etape, i) in tableEtapesDisplayRows(fait)"
                                                                    :key="`${fait.id}-etape-read-${i}`"
                                                                    class="wb-nested-pivot-row"
                                                                >
                                                                    <td class="wb-nested-pivot-td wb-nested-pivot-td--num">{{ i + 1 }}</td>
                                                                    <td class="wb-nested-pivot-td wb-nested-pivot-td--body">
                                                                        <span class="wb-nested-pivot-text">{{ etape.body }}</span>
                                                                    </td>
                                                                    <td class="wb-nested-pivot-td wb-nested-pivot-td--responsable">{{
                                                                        tableEtapeResponsableLabel(
                                                                            etape.responsable_action_id,
                                                                        )
                                                                    }}</td>
                                                                    <td class="wb-nested-pivot-td wb-nested-pivot-td--deadline tabular-nums">{{
                                                                        etape.deadline ? formatDateFr(etape.deadline) : '—'
                                                                    }}</td>
                                                                    <td class="wb-nested-pivot-td wb-nested-pivot-td--status">
                                                                        <template
                                                                            v-for="badge in [
                                                                                tableEtapeStatusBadge(etape.etape_status_id),
                                                                            ]"
                                                                            :key="`${fait.id}-etape-st-${i}`"
                                                                        >
                                                                            <span
                                                                                class="wb-nested-pivot-status-pill"
                                                                                :style="badge.style"
                                                                            >{{ badge.label }}</span>
                                                                        </template>
                                                                    </td>
                                                                </tr>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </template>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td
                                            class="wb-general-td wb-general-td--pivot wb-general-td--pivot-comments"
                                            :class="{
                                                'wb-general-td--pivot-saving':
                                                    tableInlineSaving[fait.id] === true &&
                                                    isTablePivotEditing(fait.id, 'commentaires'),
                                            }"
                                        >
                                            <div
                                                class="wb-general-pivot-cell"
                                                :class="{
                                                    'wb-general-pivot-cell--editing': isTablePivotEditing(
                                                        fait.id,
                                                        'commentaires',
                                                    ),
                                                }"
                                            >
                                                <div
                                                    class="wb-pivot-accordion wb-pivot-accordion--comments"
                                                    :class="{
                                                        'wb-pivot-accordion--open': isTablePivotAccordionExpanded(
                                                            fait.id,
                                                            'commentaires',
                                                        ),
                                                    }"
                                                >
                                                    <div class="wb-pivot-accordion__header">
                                                        <button
                                                            type="button"
                                                            class="wb-pivot-accordion__trigger"
                                                            :aria-expanded="
                                                                isTablePivotAccordionExpanded(
                                                                    fait.id,
                                                                    'commentaires',
                                                                )
                                                            "
                                                            @click.stop="
                                                                toggleTablePivotAccordion(fait.id, 'commentaires')
                                                            "
                                                        >
                                                            <span class="wb-pivot-accordion__icon" aria-hidden="true">
                                                                <MessageSquare class="size-3.5" :stroke-width="2.25" />
                                                            </span>
                                                            <span class="wb-pivot-accordion__copy">
                                                                <span class="wb-pivot-accordion__label">{{
                                                                    tablePivotAccordionTitle('commentaires')
                                                                }}</span>
                                                                <span
                                                                    v-if="isTablePivotEditing(fait.id, 'commentaires')"
                                                                    class="wb-pivot-accordion__state wb-pivot-accordion__state--edit"
                                                                >Édition</span>
                                                                <span
                                                                    v-else
                                                                    class="wb-pivot-accordion__badge"
                                                                    :class="{
                                                                        'wb-pivot-accordion__badge--empty':
                                                                            tableCommentairesDisplayRows(fait).length === 0,
                                                                    }"
                                                                >{{
                                                                    tablePivotAccordionSummary(
                                                                        fait,
                                                                        'commentaires',
                                                                    )
                                                                }}</span>
                                                            </span>
                                                            <ChevronDown
                                                                class="wb-pivot-accordion__chev size-4 shrink-0"
                                                                :class="{
                                                                    'wb-pivot-accordion__chev--open':
                                                                        isTablePivotAccordionExpanded(
                                                                            fait.id,
                                                                            'commentaires',
                                                                        ),
                                                                }"
                                                                :stroke-width="2.5"
                                                                aria-hidden="true"
                                                            />
                                                        </button>
                                                        <button
                                                            v-if="
                                                                !isTablePivotEditing(fait.id, 'commentaires') &&
                                                                !tableRowEditorDisabled(fait)
                                                            "
                                                            type="button"
                                                            class="wb-pivot-accordion__action"
                                                            :aria-label="
                                                                tableCommentairesDisplayRows(fait).length === 0
                                                                    ? 'Ajouter un commentaire'
                                                                    : 'Modifier les commentaires'
                                                            "
                                                            @click.stop="
                                                                startTablePivotAccordionEdit(
                                                                    fait.id,
                                                                    'commentaires',
                                                                )
                                                            "
                                                        >
                                                            <Pencil
                                                                v-if="tableCommentairesDisplayRows(fait).length > 0"
                                                                class="size-3.5 shrink-0"
                                                                :stroke-width="2.25"
                                                                aria-hidden="true"
                                                            />
                                                            <Plus
                                                                v-else
                                                                class="size-3.5 shrink-0"
                                                                :stroke-width="2.25"
                                                                aria-hidden="true"
                                                            />
                                                        </button>
                                                    </div>
                                                    <div
                                                        v-show="
                                                            isTablePivotAccordionExpanded(
                                                                fait.id,
                                                                'commentaires',
                                                            )
                                                        "
                                                        class="wb-pivot-accordion__panel"
                                                    >
                                                <template v-if="isTablePivotEditing(fait.id, 'commentaires')">
                                                    <div class="wb-nested-pivot-wrap">
                                                        <table
                                                            class="wb-nested-pivot-table wb-nested-pivot-table--edit wb-nested-pivot-table--comments"
                                                            aria-label="Édition commentaires"
                                                        >
                                                            <colgroup>
                                                                <col class="wb-nested-pivot-col wb-nested-pivot-col--num" />
                                                                <col class="wb-nested-pivot-col wb-nested-pivot-col--body" />
                                                                <col class="wb-nested-pivot-col wb-nested-pivot-col--action" />
                                                            </colgroup>
                                                            <thead>
                                                                <tr>
                                                                    <th class="wb-nested-pivot-th wb-nested-pivot-th--num">#</th>
                                                                    <th class="wb-nested-pivot-th wb-nested-pivot-th--body">Commentaire</th>
                                                                    <th class="wb-nested-pivot-th wb-nested-pivot-th--action" />
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <tr
                                                                    v-for="(_, i) in tablePivotEditLines(
                                                                        fait.id,
                                                                        'commentaires',
                                                                    )"
                                                                    :key="`${fait.id}-comment-edit-${i}`"
                                                                    class="wb-nested-pivot-row"
                                                                >
                                                                    <td class="wb-nested-pivot-td wb-nested-pivot-td--num">{{ i + 1 }}</td>
                                                                    <td class="wb-nested-pivot-td wb-nested-pivot-td--body">
                                                                        <textarea
                                                                            :ref="
                                                                                (el) =>
                                                                                    syncTableNestedTextarea(
                                                                                        el as HTMLTextAreaElement | null,
                                                                                    )
                                                                            "
                                                                            :value="
                                                                                tablePivotEditLines(fait.id, 'commentaires')[i]
                                                                            "
                                                                            class="wb-nested-pivot-text wb-nested-pivot-text--edit"
                                                                            rows="1"
                                                                            spellcheck="false"
                                                                            :placeholder="
                                                                                i === 0
                                                                                    ? 'Saisir un commentaire…'
                                                                                    : `Commentaire ${i + 1}`
                                                                            "
                                                                            @input="onTableCommentBodyInput(fait.id, i, $event)"
                                                                        />
                                                                    </td>
                                                                    <td class="wb-nested-pivot-td wb-nested-pivot-td--action">
                                                                        <button
                                                                            v-if="
                                                                                tablePivotLineCanRemove(
                                                                                    tablePivotEditLines(fait.id, 'commentaires'),
                                                                                    i,
                                                                                )
                                                                            "
                                                                            type="button"
                                                                            class="wb-nested-pivot-row-remove"
                                                                            :aria-label="`Supprimer le commentaire ${i + 1}`"
                                                                            @click="
                                                                                removeTablePivotEditLine(fait.id, 'commentaires', i)
                                                                            "
                                                                        >
                                                                            <X class="size-3.5" :stroke-width="2.5" aria-hidden="true" />
                                                                        </button>
                                                                    </td>
                                                                </tr>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                    <div class="wb-general-pivot-edit-actions">
                                                        <button
                                                            type="button"
                                                            class="wb-general-pivot-edit-cancel"
                                                            :disabled="tableInlineSaving[fait.id] === true"
                                                            @click="cancelTablePivotEdit"
                                                        >
                                                            Annuler
                                                        </button>
                                                        <button
                                                            type="button"
                                                            class="wb-general-pivot-edit-save"
                                                            :disabled="tableInlineSaving[fait.id] === true"
                                                            @click="saveTablePivotEdit"
                                                        >
                                                            <Check
                                                                class="size-3.5 shrink-0"
                                                                :stroke-width="2.5"
                                                                aria-hidden="true"
                                                            />
                                                            Enregistrer
                                                        </button>
                                                    </div>
                                                </template>
                                                <template v-else>
                                                    <div class="wb-nested-pivot-wrap">
                                                        <table
                                                            class="wb-nested-pivot-table wb-nested-pivot-table--read wb-nested-pivot-table--comments"
                                                            aria-label="Commentaires"
                                                        >
                                                            <colgroup>
                                                                <col class="wb-nested-pivot-col wb-nested-pivot-col--num" />
                                                                <col class="wb-nested-pivot-col wb-nested-pivot-col--body" />
                                                            </colgroup>
                                                            <thead>
                                                                <tr>
                                                                    <th class="wb-nested-pivot-th wb-nested-pivot-th--num">#</th>
                                                                    <th class="wb-nested-pivot-th wb-nested-pivot-th--body">Commentaire</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <tr v-if="tableCommentairesReadRows(fait).length === 0">
                                                                    <td colspan="2" class="wb-nested-pivot-empty">
                                                                        Aucun commentaire
                                                                    </td>
                                                                </tr>
                                                                <tr
                                                                    v-for="(row, i) in tableCommentairesReadRows(fait)"
                                                                    :key="`${fait.id}-comment-read-${i}`"
                                                                    class="wb-nested-pivot-row"
                                                                >
                                                                    <td class="wb-nested-pivot-td wb-nested-pivot-td--num">{{ i + 1 }}</td>
                                                                    <td class="wb-nested-pivot-td wb-nested-pivot-td--body">
                                                                        <span class="wb-nested-pivot-text">{{ row.body }}</span>
                                                                        <span
                                                                            v-if="row.authorLabel"
                                                                            class="wb-nested-pivot-author"
                                                                        >
                                                                            {{ row.authorLabel }}
                                                                        </span>
                                                                    </td>
                                                                </tr>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </template>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="wb-general-td wb-general-td--fait-status">
                                            <template v-for="d in [tableDraftFor(fait)]" :key="`fs-${fait.id}`">
                                                <div class="wb-general-status-stack">
                                                    <div
                                                        class="wb-general-table-badge-dropdown"
                                                        :class="{
                                                            'wb-general-table-badge-dropdown--open':
                                                                tableDropdown?.faitId === fait.id,
                                                        }"
                                                    >
                                                        <button
                                                            type="button"
                                                            data-table-badge-trigger
                                                            class="wb-general-table-badge-trigger"
                                                            :class="tableFaitStatusSelectBadge(d.fait_status_id).className"
                                                            :style="tableFaitStatusSelectBadge(d.fait_status_id).style"
                                                            :disabled="tableRowEditorDisabled(fait)"
                                                            aria-haspopup="menu"
                                                            :aria-expanded="tableDropdown?.faitId === fait.id"
                                                            @click.stop="toggleTableFaitStatusDropdown(fait.id, $event)"
                                                        >
                                                            <span class="wb-general-table-badge-trigger__label">{{
                                                                tableFaitStatusLabel(d.fait_status_id)
                                                            }}</span>
                                                            <ChevronDown
                                                                class="wb-general-table-badge-trigger__chev size-3 shrink-0 opacity-90"
                                                                :stroke-width="2.5"
                                                                aria-hidden="true"
                                                            />
                                                        </button>
                                                    </div>
                                                    <span
                                                        v-if="tableWorkflowStatusShowsUnderFaitStatus(d.status_id)"
                                                        class="wb-general-workflow-mini-badge"
                                                        :class="tableWorkflowStatusMiniBadge(d.status_id).className"
                                                        :style="tableWorkflowStatusMiniBadge(d.status_id).style"
                                                    >
                                                        {{ tableWorkflowStatusLabel(d.status_id) }}
                                                    </span>
                                                </div>
                                            </template>
                                        </td>
                                        <td
                                            class="wb-general-td wb-general-td--muted wb-general-td--responsable"
                                            :title="fait.responsable_action?.name ?? undefined"
                                        >
                                            {{ fait.responsable_action?.name ?? '—' }}
                                        </td>
                                        <td
                                            class="wb-general-td wb-general-td--muted wb-general-td--date tabular-nums"
                                        >
                                            {{ formatDateFr(faitCreatedAtIso(fait)) }}
                                        </td>
                                        <td class="wb-general-td wb-general-td--deadline wb-general-td--muted tabular-nums">
                                            <template v-for="d in [tableDraftFor(fait)]" :key="`dl-${fait.id}`">
                                                <div class="flex flex-col gap-0.5">
                                                    <span>{{ formatDateFr(fait.deadline) }}</span>
                                                    <template
                                                        v-for="c in [deadlineDaysTableCell(fait.deadline)]"
                                                        :key="`${fait.id}-dur`"
                                                    >
                                                        <template
                                                            v-if="
                                                                c.tone !== 'empty' &&
                                                                tableWorkflowShowsDeadlineDayCount(d.status_id)
                                                            "
                                                        >
                                                            <span
                                                                v-if="c.tone === 'calm'"
                                                                class="text-[11px] font-semibold leading-tight text-green-600 dark:text-green-400"
                                                            >{{ c.text }}</span>
                                                            <span
                                                                v-else
                                                                class="text-[11px] font-semibold leading-tight text-destructive"
                                                            >{{ c.text }}</span>
                                                        </template>
                                                    </template>
                                                </div>
                                            </template>
                                        </td>
                                        <td class="wb-general-td wb-general-td--action">
                                            <div
                                                v-if="userCanEditFaitMarquant(fait)"
                                                class="wb-general-row-actions"
                                                role="group"
                                                :aria-label="`Actions — ${fait.title}`"
                                            >
                                                <button
                                                    type="button"
                                                    class="wb-general-row-action wb-general-row-action--edit"
                                                    :disabled="tableRowEditorDisabled(fait)"
                                                    :aria-busy="tableInlineSaving[fait.id] === true"
                                                    :aria-label="
                                                        tableInlineSaving[fait.id]
                                                            ? 'Enregistrement en cours'
                                                            : !userCanEditFaitMarquant(fait)
                                                              ? 'Lecture seule — autre département'
                                                              : 'Modifier le sujet'
                                                    "
                                                    :title="
                                                        tableInlineSaving[fait.id]
                                                            ? 'Enregistrement en cours'
                                                            : !userCanEditFaitMarquant(fait)
                                                              ? 'Lecture seule (autre département)'
                                                              : 'Modifier le sujet'
                                                    "
                                                    @click="openEdit(fait.id)"
                                                >
                                                    <Pencil
                                                        class="size-4 shrink-0"
                                                        :stroke-width="2.25"
                                                        aria-hidden="true"
                                                    />
                                                </button>
                                                <button
                                                    type="button"
                                                    data-table-history-trigger
                                                    class="wb-general-row-action wb-general-row-action--history"
                                                    :class="{
                                                        'wb-general-row-action--active':
                                                            tableHistoryFaitId !== null &&
                                                            tableHistoryFaitId === fait.id,
                                                    }"
                                                    :disabled="tableInlineSaving[fait.id] === true"
                                                    aria-haspopup="dialog"
                                                    :aria-expanded="
                                                        tableHistoryFaitId !== null &&
                                                        tableHistoryFaitId === fait.id
                                                    "
                                                    aria-label="Historique du sujet"
                                                    title="Historique du sujet"
                                                    @click.stop="toggleTableHistoryPanel(fait.id)"
                                                >
                                                    <History
                                                        class="size-4 shrink-0"
                                                        :stroke-width="2.25"
                                                        aria-hidden="true"
                                                    />
                                                </button>
                                                <button
                                                    type="button"
                                                    class="wb-general-row-action wb-general-row-action--cloture"
                                                    :class="{
                                                        'wb-general-row-action--done': tableFaitIsCloture(fait),
                                                    }"
                                                    :disabled="
                                                        tableInlineSaving[fait.id] === true ||
                                                        !tableFaitCanCloturer(fait)
                                                    "
                                                    :title="
                                                        tableFaitIsCloture(fait)
                                                            ? 'Déjà clôturé'
                                                            : 'Clôturer le sujet'
                                                    "
                                                    aria-label="Clôturer le sujet"
                                                    @click.stop="cloturerTableFait(fait.id)"
                                                >
                                                    <CircleCheck
                                                        class="size-4 shrink-0"
                                                        :stroke-width="2.25"
                                                        aria-hidden="true"
                                                    />
                                                </button>
                                                <button
                                                    type="button"
                                                    class="wb-general-row-action wb-general-row-action--archive"
                                                    :class="{
                                                        'wb-general-row-action--done': tableFaitIsArchive(fait),
                                                    }"
                                                    :disabled="
                                                        tableInlineSaving[fait.id] === true ||
                                                        !tableFaitCanArchiver(fait)
                                                    "
                                                    :title="
                                                        tableFaitIsArchive(fait)
                                                            ? 'Déjà archivé'
                                                            : 'Archiver le sujet'
                                                    "
                                                    aria-label="Archiver le sujet"
                                                    @click.stop="archiverTableFait(fait.id)"
                                                >
                                                    <Archive
                                                        class="size-4 shrink-0"
                                                        :stroke-width="2.25"
                                                        aria-hidden="true"
                                                    />
                                                </button>
                                            </div>
                                            <span v-else class="sr-only">Lecture seule</span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>
                </template>

                <p
                    v-if="faitsForTableGrouping.length === 0"
                    class="rounded-lg border border-dashed border-muted-foreground/30 bg-muted/20 px-4 py-10 text-center text-sm text-muted-foreground"
                >
                    Aucun sujet pour ce filtre.
                </p>
            </div>
        </div>

        <!-- Panneau latéral Faits marquants (vue cartes uniquement) -->
        <aside
            v-if="wbMainView === 'board'"
            id="faits-marquants-sheet"
            class="wb-faits-drawer flex h-full w-full max-w-full flex-col gap-0 border-l border-border bg-background p-0 shadow-xl sm:max-w-[min(100vw-1rem,24rem)]"
            :class="{ 'wb-faits-drawer--open': sidebarOpen }"
            aria-label="Sujets"
        >
                <header class="space-y-3 border-b border-border px-5 pb-4 pt-6 text-left">
                    <h2 class="text-xl font-semibold tracking-tight">
                        Sujets
                    </h2>
                    <p class="text-left text-sm leading-relaxed text-muted-foreground">
                        Glissez un sujet sur le tableau ou cliquez dessus pour l’afficher. Sujet déjà
                        sur le tableau : clic pour le sélectionner.
                    </p>
                    <button
                        v-if="showCreateFaitMarquant"
                        type="button"
                        class="flex w-full items-center justify-center rounded-lg border-2 border-dashed border-violet-400/80 bg-violet-50/50 py-3 text-sm font-medium text-violet-700 transition hover:bg-violet-100/80 dark:border-violet-500/50 dark:bg-violet-950/30 dark:text-violet-200 dark:hover:bg-violet-950/50"
                        @click="openCreate"
                    >
                        Ajouter un sujet
                    </button>
                </header>

                <div class="flex min-h-0 flex-1 flex-col overflow-hidden">
                    <div
                        class="flex flex-wrap items-center justify-between gap-2 border-b border-border px-5 py-3"
                    >
                        <p
                            class="text-[11px] font-semibold tracking-wider text-muted-foreground uppercase"
                        >
                            Sujets disponibles
                        </p>
                        <div class="flex flex-wrap gap-1.5">
                            <button type="button" class="wb-faits-list-action" @click="collapseAll">
                                Réduire tous
                            </button>
                            <button
                                type="button"
                                class="wb-faits-list-action wb-faits-list-action--primary"
                                @click="expandAll"
                            >
                                Afficher tous
                            </button>
                        </div>
                    </div>

                    <div class="min-h-0 flex-1 overflow-y-auto px-4 py-4">
                        <div
                            v-if="filteredFaitsMarquants.length > 0"
                            class="wb-faits-sidebar-groups flex flex-col gap-6"
                        >
                            <section
                                v-for="group in sidebarFaitGroups"
                                v-show="group.faits.length > 0"
                                :key="group.departmentId"
                                class="wb-faits-dept-group"
                            >
                                <h3
                                    v-if="sidebarUsesDepartmentGroups && group.departmentName"
                                    class="wb-faits-dept-group__title"
                                >
                                    <span class="wb-faits-dept-group__name">{{ group.departmentName }}</span>
                                    <span
                                        v-if="departmentSubtitle(group.departmentName)"
                                        class="wb-faits-dept-group__hint"
                                        :title="departmentSubtitle(group.departmentName) ?? undefined"
                                    >
                                        {{ departmentSubtitle(group.departmentName) }}
                                    </span>
                                </h3>
                                <ul class="wb-faits-list">
                                    <li v-for="fait in group.faits" :key="fait.id">
                                        <button
                                            type="button"
                                            class="wb-faits-chip"
                                            :class="{
                                                'wb-faits-chip--on-board': sidebarFaitIsOnBoard(fait.id),
                                                'wb-faits-chip--active': sidebarFaitIsSelectedInDialog(fait.id),
                                            }"
                                            :draggable="!boardProjectIdsOnBoard.has(fait.id)"
                                            :aria-current="
                                                sidebarFaitIsSelectedInDialog(fait.id) ? 'true' : undefined
                                            "
                                            :aria-label="
                                                sidebarFaitIsOnBoard(fait.id)
                                                    ? `${fait.title} — déjà sur le tableau`
                                                    : fait.title
                                            "
                                            @dragstart="onSidebarFaitDragStart($event, fait)"
                                            @click="openEditFromSidebar(fait.id)"
                                        >
                                            <span
                                                class="wb-faits-chip__swatch"
                                                :style="{ background: sidebarToolboxPaperFace(fait) }"
                                                aria-hidden="true"
                                            />
                                            <span class="wb-faits-chip__text">
                                                <span class="wb-faits-chip__name">{{ fait.title }}</span>
                                            </span>
                                        </button>
                                    </li>
                                </ul>
                            </section>
                        </div>
                        <p
                            v-else
                            class="rounded-lg border border-dashed border-muted-foreground/25 bg-muted/20 px-4 py-8 text-center text-sm text-muted-foreground"
                        >
                            Aucun sujet pour le moment. Utilisez le bouton ci-dessus pour en créer un.
                        </p>
                    </div>
                </div>
        </aside>

        <!-- Bouton tiroir : uniquement en vue cartes -->
        <button
            v-if="wbMainView === 'board'"
            type="button"
            :class="[faitsSheetFabBaseClass, { 'faits-sheet-fab--open': sidebarOpen }]"
            :aria-expanded="sidebarOpen"
            aria-controls="faits-marquants-sheet"
            :aria-label="sidebarOpen ? 'Fermer le panneau Sujets' : 'Ouvrir le panneau Sujets'"
            @click="toggleFab"
        >
            <span class="relative flex size-8 items-center justify-center" aria-hidden="true">
                <Folder class="size-7" :stroke-width="2" />
                <Bookmark
                    class="absolute -top-0.5 -right-0.5 size-3.5 drop-shadow-sm"
                    :stroke-width="2.5"
                    fill="currentColor"
                />
            </span>
        </button>

        <button
            type="button"
            v-if="dialogOpen"
            class="wb-fait-form-drawer-backdrop"
            aria-label="Fermer le formulaire"
            @click="dialogOpen = false"
        />

        <div
            id="fait-marquant-form-sheet"
            role="dialog"
            aria-modal="true"
            :aria-hidden="!dialogOpen"
            tabindex="-1"
            class="wb-fait-form-drawer wb-fait-form-drawer--wide wb-fait-sheet flex h-full w-full max-w-full flex-col gap-0 overflow-hidden border-l border-border bg-background p-0 text-base shadow-2xl"
            :class="{ 'wb-fait-form-drawer--open': dialogOpen }"
        >
                <!-- Fixed header -->
                <div class="relative shrink-0 border-b border-border/80 bg-gradient-to-b from-muted/50 to-muted/25 px-6 pb-4 pt-6 pr-14 sm:pr-40">
                    <h2 class="space-y-0 p-0 text-left text-xl font-semibold tracking-tight text-foreground md:text-2xl">
                        {{ editingId === null ? 'Nouveau sujet' : 'Modifier le sujet' }}
                    </h2>
                    <p class="sr-only">
                        {{
                            editingId === null
                                ? 'Création d\'un sujet.'
                                : 'Modification d\'un sujet.'
                        }}
                    </p>
                    <div class="absolute top-3 right-3 flex max-w-[calc(100%-1.5rem)] items-center justify-end gap-2 sm:top-4 sm:right-4">
                        <div
                            v-if="editingId !== null"
                            ref="workflowStatusDropdownRoot"
                            class="wb-fait-form-status-dropdown min-w-[9.5rem] shrink-0"
                            :class="{ 'wb-fait-form-status-dropdown--open': workflowStatusMenuOpen && dialogOpen }"
                        >
                            <button
                                id="fait-form-workflow-status-trigger"
                                type="button"
                                class="wb-fait-form-status-trigger h-9"
                                :class="selectedWorkflowStatusBadge.triggerClass"
                                :style="selectedWorkflowStatusBadge.style"
                                aria-label="Statut workflow"
                                aria-haspopup="menu"
                                :aria-expanded="workflowStatusMenuOpen && dialogOpen"
                                aria-controls="fait-form-workflow-status-menu"
                                @click.stop="toggleWorkflowStatusMenu"
                            >
                                <span class="wb-fait-form-status-trigger-label">{{
                                    selectedWorkflowStatus?.name ?? 'Choisir un statut'
                                }}</span>
                                <ChevronDown
                                    class="wb-fait-form-status-chevron size-4 shrink-0 opacity-90"
                                    :stroke-width="2.5"
                                    aria-hidden="true"
                                />
                            </button>
                            <ul
                                v-show="workflowStatusMenuOpen && dialogOpen"
                                id="fait-form-workflow-status-menu"
                                class="wb-fait-form-status-menu"
                                role="menu"
                                aria-labelledby="fait-form-workflow-status-trigger"
                            >
                                <li v-for="w in workflowStatuses" :key="w.id" role="none">
                                    <button
                                        type="button"
                                        class="wb-fait-form-status-menu-item"
                                        :class="workflowStatusBadgeForOption(w).menuItemClass"
                                        :style="workflowStatusBadgeForOption(w).style"
                                        role="menuitem"
                                        @click.stop="pickWorkflowStatus(w.id)"
                                    >
                                        <span class="wb-fait-form-status-menu-item-label">{{ w.name }}</span>
                                        <span
                                            v-if="form.status_id === w.id"
                                            class="wb-fait-form-status-menu-check"
                                            aria-hidden="true"
                                        >✓</span>
                                    </button>
                                </li>
                            </ul>
                        </div>
                        <button
                            type="button"
                            class="shrink-0 rounded-md p-2 opacity-70 ring-offset-background transition-opacity hover:opacity-100 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                            aria-label="Fermer"
                            @click="dialogOpen = false"
                        >
                            <X class="size-4" :stroke-width="2" aria-hidden="true" />
                        </button>
                    </div>
                </div>

                <!-- Scrollable body -->
                <div class="wb-fait-form-drawer-body min-h-0 flex-1 space-y-6 overflow-y-auto overscroll-contain px-6 py-6 sm:px-8">

                    <!-- Fait marquant -->
                    <div class="space-y-2">
                        <label class="wb-fait-form-label" for="fait-form-nom">Sujet</label>
                        <textarea
                            id="fait-form-nom"
                            v-model="form.title"
                            rows="3"
                            required
                            class="w-full min-h-[5rem] resize-y rounded-lg border border-input bg-background px-3.5 py-3 text-base leading-relaxed text-foreground shadow-sm placeholder:text-muted-foreground focus-visible:border-violet-500 focus-visible:ring-2 focus-visible:ring-violet-500/20 focus-visible:outline-none dark:focus-visible:border-violet-400"
                            placeholder="Ex. Lancement du module, validation client…"
                        />
                        <p v-if="form.errors.title" class="text-sm font-medium text-destructive">
                            {{ form.errors.title }}
                        </p>
                    </div>

                    <!-- Tab switch: Prochaines étapes / Commentaires -->
                    <div class="space-y-3">
                        <div class="wb-fait-tab-switch" role="tablist" aria-label="Listes du sujet">
                            <button
                                type="button"
                                role="tab"
                                class="wb-fait-tab-btn"
                                :aria-selected="listTab === 'etapes'"
                                @click="listTab = 'etapes'"
                            >
                                Prochaines étapes
                            </button>
                            <button
                                type="button"
                                role="tab"
                                class="wb-fait-tab-btn"
                                :aria-selected="listTab === 'commentaires'"
                                @click="listTab = 'commentaires'"
                            >
                                Commentaires
                            </button>
                        </div>

                        <!-- Prochaines etapes list -->
                        <div v-if="listTab === 'etapes'" class="wb-fait-todo-section">
                            <p v-if="draftEtapes.length === 0" class="wb-fait-todo-empty">
                                Aucune prochaine étape. Cliquez sur le bouton ci-dessous pour en ajouter.
                            </p>
                            <ol
                                v-else
                                class="wb-fait-etape-list"
                                aria-label="Prochaines étapes"
                            >
                                <li
                                    v-for="(etape, i) in draftEtapes"
                                    :key="'e' + i"
                                    class="wb-fait-etape-card"
                                >
                                    <div class="wb-fait-etape-card__head">
                                        <span class="wb-fait-etape-card__num" aria-hidden="true">{{ i + 1 }}</span>
                                        <span class="wb-fait-etape-card__title">Étape {{ i + 1 }}</span>
                                        <button
                                            type="button"
                                            class="wb-fait-etape-card__remove"
                                            :aria-label="`Supprimer l'étape ${i + 1}`"
                                            @click="removeDraftEtape(i)"
                                        >
                                            <X class="size-4" :stroke-width="2.25" aria-hidden="true" />
                                        </button>
                                    </div>
                                    <div class="wb-fait-etape-card__field">
                                        <label
                                            class="wb-fait-form-label"
                                            :for="`fait-form-etape-body-${i}`"
                                        >Description</label>
                                        <input
                                            :id="`fait-form-etape-body-${i}`"
                                            v-model="draftEtapes[i].body"
                                            class="wb-fait-etape-control"
                                            type="text"
                                            spellcheck="false"
                                            :placeholder="`Décrire la prochaine étape ${i + 1}…`"
                                        />
                                    </div>
                                    <div class="wb-fait-etape-card__meta">
                                        <div class="wb-fait-etape-card__field">
                                            <label
                                                class="wb-fait-form-label"
                                                :for="`fait-form-etape-resp-${i}`"
                                            >Responsable</label>
                                            <select
                                                :id="`fait-form-etape-resp-${i}`"
                                                v-model="draftEtapes[i].responsable_action_id"
                                                class="wb-fait-etape-control wb-fait-etape-control--select"
                                            >
                                                <option
                                                    v-for="u in actionResponsiblesForForm"
                                                    :key="`form-etape-resp-${i}-${u.id}`"
                                                    :value="u.id"
                                                >
                                                    {{ u.name }}
                                                </option>
                                            </select>
                                        </div>
                                        <div class="wb-fait-etape-card__field">
                                            <label
                                                class="wb-fait-form-label"
                                                :for="`fait-form-etape-deadline-${i}`"
                                            >Deadline</label>
                                            <input
                                                :id="`fait-form-etape-deadline-${i}`"
                                                v-model="draftEtapes[i].deadline"
                                                type="date"
                                                class="wb-fait-etape-control"
                                            />
                                        </div>
                                        <div class="wb-fait-etape-card__field">
                                            <label
                                                class="wb-fait-form-label"
                                                :for="`fait-form-etape-status-${i}`"
                                            >Statut</label>
                                            <select
                                                :id="`fait-form-etape-status-${i}`"
                                                v-model="draftEtapes[i].etape_status_id"
                                                class="wb-fait-etape-control wb-fait-etape-control--select"
                                            >
                                                <option
                                                    v-for="s in etapeStatuses"
                                                    :key="`form-etape-st-${i}-${s.id}`"
                                                    :value="s.id"
                                                >
                                                    {{ s.name }}
                                                </option>
                                            </select>
                                        </div>
                                    </div>
                                </li>
                            </ol>
                            <button type="button" class="wb-fait-todo-add" @click="addDraftEtape">
                                <Plus class="size-4 shrink-0" :stroke-width="2.25" aria-hidden="true" />
                                Ajouter une prochaine étape
                            </button>
                        </div>
                        <!-- Commentaires list -->
                        <div v-if="listTab === 'commentaires'" class="wb-fait-todo-section">
                            <p v-if="draftCommentaires.length === 0" class="wb-fait-todo-empty">
                                Aucun commentaire. Cliquez sur le bouton ci-dessous pour en ajouter.
                            </p>
                            <ol
                                v-else
                                class="wb-fait-todo-list"
                                aria-label="Commentaires"
                            >
                                <li
                                    v-for="(_, i) in draftCommentaires"
                                    :key="'c' + i"
                                    class="wb-fait-todo-item"
                                >
                                    <span class="wb-fait-todo-num" aria-hidden="true">{{ i + 1 }}.</span>
                                    <input
                                        v-model="draftCommentaires[i]"
                                        class="wb-fait-todo-input"
                                        type="text"
                                        spellcheck="false"
                                        :placeholder="`Commentaire ${i + 1}`"
                                    />
                                    <button
                                        type="button"
                                        class="wb-fait-todo-remove"
                                        :aria-label="`Supprimer le commentaire ${i + 1}`"
                                        @click="removeDraftCommentaire(i)"
                                    >×</button>
                                </li>
                            </ol>
                            <button type="button" class="wb-fait-todo-add" @click="addDraftCommentaire">
                                <Plus class="size-4 shrink-0" :stroke-width="2.25" aria-hidden="true" />
                                Ajouter un commentaire
                            </button>
                        </div>
                    </div>

                    <!-- Statut + Météo -->
                    <div class="grid grid-cols-1 items-start gap-5">
                        <!-- Statut -->
                        <div class="wb-fait-form-status-col space-y-2">
                            <label class="wb-fait-form-label" for="fait-form-statut-trigger">Statut</label>
                            <div
                                ref="faitStatusDropdownRoot"
                                class="wb-fait-form-status-dropdown"
                                :class="{ 'wb-fait-form-status-dropdown--open': faitStatusMenuOpen && dialogOpen }"
                            >
                                <button
                                    id="fait-form-statut-trigger"
                                    type="button"
                                    class="wb-fait-form-status-trigger"
                                    :class="selectedFaitStatusBadge.triggerClass"
                                    :style="selectedFaitStatusBadge.style"
                                    aria-haspopup="menu"
                                    :aria-expanded="faitStatusMenuOpen && dialogOpen"
                                    aria-controls="fait-form-statut-menu"
                                    @click.stop="toggleFaitStatusMenu"
                                >
                                    <span class="wb-fait-form-status-trigger-label">{{
                                        selectedFaitStatus?.name ?? 'Choisir un statut'
                                    }}</span>
                                    <ChevronDown
                                        class="wb-fait-form-status-chevron size-4 shrink-0 opacity-90"
                                        :stroke-width="2.5"
                                        aria-hidden="true"
                                    />
                                </button>
                                <ul
                                    v-show="faitStatusMenuOpen && dialogOpen"
                                    id="fait-form-statut-menu"
                                    class="wb-fait-form-status-menu"
                                    role="menu"
                                    aria-labelledby="fait-form-statut-trigger"
                                >
                                    <li v-for="s in faitStatuses" :key="s.id" role="none">
                                        <button
                                            type="button"
                                            class="wb-fait-form-status-menu-item"
                                            :class="faitStatusBadgeForOption(s).menuItemClass"
                                            :style="faitStatusBadgeForOption(s).style"
                                            role="menuitem"
                                            @click.stop="pickFaitStatus(s.id)"
                                        >
                                            <span class="wb-fait-form-status-menu-item-label">{{ s.name }}</span>
                                            <span
                                                v-if="form.fait_status_id === s.id"
                                                class="wb-fait-form-status-menu-check"
                                                aria-hidden="true"
                                            >✓</span>
                                        </button>
                                    </li>
                                </ul>
                            </div>
                            <p v-if="form.errors.fait_status_id" class="text-sm font-medium text-destructive">
                                {{ form.errors.fait_status_id }}
                            </p>
                        </div>

                    </div>

                    <div v-if="showFaitFormDepartmentField" class="space-y-2">
                        <label class="wb-fait-form-label" for="fait-form-department">Département</label>
                        <select
                            id="fait-form-department"
                            v-model.number="form.department_id"
                            required
                            class="mt-1 h-11 w-full rounded-lg border border-input bg-background px-3 text-base text-foreground shadow-sm focus-visible:border-violet-500 focus-visible:ring-2 focus-visible:ring-violet-500/20 focus-visible:outline-none dark:focus-visible:border-violet-400"
                        >
                            <option disabled :value="null">
                                Choisir un département
                            </option>
                            <option
                                v-for="department in faitFormDepartments"
                                :key="department.id"
                                :value="department.id"
                            >
                                {{ department.name }}
                            </option>
                        </select>
                        <p v-if="form.errors.department_id" class="text-sm font-medium text-destructive">
                            {{ form.errors.department_id }}
                        </p>
                    </div>

                    <div v-if="showFaitFormDeadlineField" class="space-y-2">
                        <label class="wb-fait-form-label" for="fait-form-deadline">Deadline</label>
                        <input
                            id="fait-form-deadline"
                            v-model="deadlineInput"
                            type="date"
                            :required="editingId === null"
                            class="mt-1 h-11 w-full rounded-lg border border-input bg-background px-3 text-base text-foreground shadow-sm focus-visible:border-violet-500 focus-visible:ring-2 focus-visible:ring-violet-500/20 focus-visible:outline-none dark:focus-visible:border-violet-400"
                        />
                        <p v-if="form.errors.deadline" class="text-sm font-medium text-destructive">
                            {{ form.errors.deadline }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <label class="wb-fait-form-label" for="fait-form-responsable-action">Responsable action</label>
                        <select
                            id="fait-form-responsable-action"
                            v-model.number="form.responsable_action_id"
                            required
                            class="mt-1 h-11 w-full rounded-lg border border-input bg-background px-3 text-base text-foreground shadow-sm focus-visible:border-violet-500 focus-visible:ring-2 focus-visible:ring-violet-500/20 focus-visible:outline-none dark:focus-visible:border-violet-400"
                        >
                            <option v-if="!selectedActionResponsible" disabled :value="0">
                                Choisir un responsable
                            </option>
                            <option
                                v-for="u in actionResponsiblesForForm"
                                :key="u.id"
                                :value="u.id"
                            >
                                {{ u.name }}
                            </option>
                        </select>
                        <p v-if="form.errors.responsable_action_id" class="text-sm font-medium text-destructive">
                            {{ form.errors.responsable_action_id }}
                        </p>
                    </div>

                </div>

                <!-- Fixed footer -->
                <div class="shrink-0 border-t border-border/80 bg-muted/25 px-6 py-4 flex items-center justify-between gap-3">
                    <!-- <button
                        v-if="editingId !== null"
                        type="button"
                        class="rounded-md px-3 py-2 text-sm font-medium text-destructive hover:bg-red-500/10 disabled:pointer-events-none disabled:opacity-50"
                        :disabled="form.processing"
                        @click="destroyFait"
                    >
                        Supprimer
                    </button> v-else -->
                    <div  aria-hidden="true" />
                    <div class="flex items-center gap-3">
                        <button
                            type="button"
                            class="inline-flex h-10 items-center justify-center rounded-md border border-input bg-background px-4 py-2 text-sm font-medium ring-offset-background transition-colors hover:bg-accent hover:text-accent-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                            @click="dialogOpen = false"
                        >
                            Annuler
                        </button>
                        <button
                            type="button"
                            class="inline-flex h-10 min-w-[8.5rem] items-center justify-center rounded-md bg-violet-600 px-5 text-sm font-medium text-white ring-offset-background transition-colors hover:bg-violet-700 focus-visible:ring-2 focus-visible:ring-violet-500 focus-visible:ring-offset-2 focus-visible:outline-none disabled:pointer-events-none disabled:opacity-50"
                            :disabled="form.processing"
                            @click="submitFait"
                        >
                            {{ formSaveButtonLabel }}
                        </button>
                    </div>
                </div>
        </div>

        <button
            v-if="tableHistoryFaitId !== null && wbMainView === 'table'"
            type="button"
            class="wb-fait-form-drawer-backdrop wb-table-history-backdrop"
            aria-label="Fermer l’historique"
            @click="tableHistoryFaitId = null"
        />

        <div
            v-if="wbMainView === 'table'"
            id="fait-marquant-history-sheet"
            role="dialog"
            aria-modal="true"
            :aria-hidden="tableHistoryFaitId === null"
            tabindex="-1"
            class="wb-fait-form-drawer wb-fait-sheet wb-table-history-drawer flex h-full w-full max-w-full flex-col gap-0 overflow-hidden border-l border-border bg-background p-0 text-base shadow-2xl sm:max-w-[min(100vw-2rem,32rem)]"
            :class="{ 'wb-fait-form-drawer--open': tableHistoryFaitId !== null }"
        >
            <div class="relative shrink-0 border-b border-border/80 bg-gradient-to-b from-muted/50 to-muted/25 px-6 pb-4 pt-6 pr-14">
                <h2
                    id="wb-table-history-title"
                    class="space-y-0 p-0 text-left text-xl font-semibold tracking-tight text-foreground md:text-2xl"
                >
                    Historique
                </h2>
                <div class="absolute top-3 right-3 sm:top-4 sm:right-4">
                    <button
                        type="button"
                        class="shrink-0 rounded-md p-2 opacity-70 ring-offset-background transition-opacity hover:opacity-100 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                        aria-label="Fermer"
                        @click="tableHistoryFaitId = null"
                    >
                        <X class="size-4" :stroke-width="2" aria-hidden="true" />
                    </button>
                </div>
            </div>

            <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-6 py-4">
                <p v-if="tableHistoryFait === null" class="text-sm text-muted-foreground">
                    Sujet introuvable.
                </p>
                <template v-else>
                    <p v-if="weeklyTimelineLoading" class="text-sm text-muted-foreground">
                        Chargement de la chronologie (fuseau Maroc)…
                    </p>
                    <p v-else-if="weeklyTimelineError" class="text-sm font-medium text-destructive">
                        {{ weeklyTimelineError }}
                    </p>
                    <div
                        v-else
                        class="wb-table-history-weeks wb-table-history-weeks--timeline"
                        aria-label="Chronologie par semaine"
                    >
                        <p class="mb-4 text-[11px] leading-relaxed text-muted-foreground">
                            Lun–dim · {{ weeklyTimelineTimezone }} · fin de semaine (snapshots publiés ; listes = dernière version en base à cette date).
                        </p>
                        <section
                            v-for="(w, wi) in weeklyTimelineWeeks"
                            :key="`wk-${w.week_start}-${w.week_end}`"
                            class="wb-table-history-week-block wb-table-history-week-block--timeline"
                        >
                            <template v-if="w.snapshot">
                                <div class="wb-table-history-week-card-head">
                                    <div class="wb-table-history-week-head-left">
                                        <h3 class="wb-table-history-week-dates">
                                            {{ w.week_label }}
                                        </h3>
                                        <p class="wb-table-history-week-range-iso text-muted-foreground">
                                            {{ w.week_start }} → {{ w.week_end }}
                                        </p>
                                    </div>
                                    <span
                                        class="wb-table-history-week-workflow-badge wb-table-history-meta-pill wb-general-status-pill text-xs"
                                        :style="w.snapshot.workflow_status?.color && isHexColor(w.snapshot.workflow_status.color) ? { backgroundColor: w.snapshot.workflow_status.color, color: '#fff' } : {}"
                                    >{{ w.snapshot.workflow_status?.name ?? '—' }}</span>
                                </div>
                                <p class="wb-table-history-week-kicker">Sujet</p>
                                <p class="wb-table-history-week-fait-title">{{ w.snapshot.title }}</p> 
                                <p
                                    v-if="w.snapshot.responsable_action?.name"
                                    class="wb-table-history-week-fait-title"
                                >
                                  <span class="wb-table-history-week-kicker">Responsable Action :</span> {{ w.snapshot.responsable_action.name }}
                                </p> 
                                <div class="wb-table-history-week-fait-status-row">
                                    <span
                                        class="wb-table-history-meta-pill wb-general-status-pill text-xs"
                                        :style="w.snapshot.fait_status?.color && isHexColor(w.snapshot.fait_status.color) ? { backgroundColor: w.snapshot.fait_status.color, color: '#fff' } : {}"
                                    > {{ w.snapshot.fait_status?.name ?? '—' }}</span>
                                </div>
                                <div
                                    class="wb-fait-tab-switch wb-table-history-week-pivot"
                                    role="tablist"
                                    :aria-label="`Listes semaine ${w.week_start}`"
                                >
                                    <button
                                        type="button"
                                        role="tab"
                                        class="wb-fait-tab-btn"
                                        :aria-selected="weeklyWeekPivot(w.week_start) === 'etapes'"
                                        @click="setWeeklyWeekPivot(w.week_start, 'etapes')"
                                    >
                                        Prochaines étapes
                                    </button>
                                    <button
                                        type="button"
                                        role="tab"
                                        class="wb-fait-tab-btn"
                                        :aria-selected="weeklyWeekPivot(w.week_start) === 'commentaires'"
                                        @click="setWeeklyWeekPivot(w.week_start, 'commentaires')"
                                    >
                                        Commentaires
                                    </button>
                                </div>
                                <div v-if="weeklyWeekPivot(w.week_start) === 'etapes'" class="wb-table-history-week-list-wrap">
                                    <ol v-if="w.prochaines_etapes.length" class="wb-fait-todo-list wb-table-history-day-list">
                                        <li
                                            v-for="row in w.prochaines_etapes"
                                            :key="`w-${wi}-et-${row.id}`"
                                            class="wb-fait-todo-item wb-table-history-readonly-item"
                                        >
                                            <span class="wb-fait-todo-num" aria-hidden="true">{{ row.sequence_number ?? '—' }}.</span>
                                            <div class="m-0 flex-1 text-sm font-medium leading-snug text-foreground">
                                                <p class="wb-fait-todo-body m-0">{{ row.body }}</p>
                                                <p
                                                    v-if="row.responsable_action || row.deadline || row.etape_status"
                                                    class="m-0 mt-0.5 text-[11px] text-muted-foreground"
                                                >
                                                    <template v-if="row.responsable_action">{{
                                                        row.responsable_action.name
                                                    }}</template>
                                                    <template v-if="row.deadline">
                                                        <span v-if="row.responsable_action"> · </span>{{ row.deadline }}
                                                    </template>
                                                    <template v-if="row.etape_status">
                                                        <span v-if="row.responsable_action || row.deadline"> · </span
                                                        >{{ row.etape_status.name }}
                                                    </template>
                                                </p>
                                            </div>
                                        </li>
                                    </ol>
                                    <p v-else class="text-xs text-muted-foreground">Aucune prochaine étape à cette date.</p>
                                </div>
                                <div v-else class="wb-table-history-week-list-wrap">
                                    <ol v-if="w.commentaires.length" class="wb-fait-todo-list wb-table-history-day-list">
                                        <li
                                            v-for="row in w.commentaires"
                                            :key="`w-${wi}-co-${row.id}`"
                                            class="wb-fait-todo-item wb-table-history-readonly-item"
                                        >
                                            <span class="wb-fait-todo-num" aria-hidden="true">{{ row.sequence_number ?? '—' }}.</span>
                                            <p class="wb-fait-todo-body m-0 flex-1 text-sm font-medium leading-snug text-foreground">
                                                {{ row.body }}
                                            </p>
                                        </li>
                                    </ol>
                                    <p v-else class="text-xs text-muted-foreground">Aucun commentaire à cette date.</p>
                                </div>
                            </template>
                            <template v-else>
                                <h3 class="wb-table-history-week-dates">
                                    {{ w.week_label }}
                                </h3>
                                <p class="wb-table-history-week-range-iso text-muted-foreground">{{ w.week_start }} → {{ w.week_end }}</p>
                                <p class="text-xs text-muted-foreground">Aucun snapshot publié pour cette période.</p>
                            </template>
                        </section>
                        <p
                            v-if="weeklyTimelineWeeks.length === 0 && !weeklyTimelineLoading"
                            class="text-sm text-muted-foreground"
                        >
                            Aucune semaine à afficher.
                        </p>
                    </div>
                </template>
            </div>
        </div>

        <Teleport v-if="teleportReady" to="body">
            <div
                v-if="wbMainView === 'board' && stickyCreateOpen"
                class="wb-sticky-create-overlay"
                @click.self="closeStickyCreate"
            >
                <div
                    class="wb-st-c-shell"
                    :style="stickyCreateShellStyle"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="wb-sticky-create-title"
                    @click.stop
                >
                    <div class="wb-st-c-top-actions">
                        <button
                            type="button"
                            class="wb-st-c-dismiss"
                            aria-label="Fermer"
                            @click="closeStickyCreate"
                        >
                            <span class="wb-st-c-dismiss-ring" aria-hidden="true" />
                            <X class="wb-st-c-dismiss-icon size-3.5" :stroke-width="2" aria-hidden="true" />
                        </button>
                    </div>
                    <div class="sticky-note">
                        <div class="sticky-note__accent" aria-hidden="true">
                            <span class="sticky-note__drag" title="Glisser pour déplacer">⋮⋮</span>
                        </div>
                        <div class="sticky-note__body wb-sticky-create-body">
                            <div class="sticky-note__title-row">
                                <textarea
                                    id="wb-sticky-create-fait-title"
                                    v-model="form.title"
                                    class="sticky-note__title"
                                    rows="3"
                                    required
                                    spellcheck="false"
                                    placeholder="Sujet"
                                    aria-label="Sujet"
                                />
                            </div>
                            <p v-if="form.errors.title" class="text-[11px] font-semibold text-destructive">
                                {{ form.errors.title }}
                            </p>

                            <div class="sticky-note__toolbar">
                                <div class="sticky-note__switch" role="tablist" aria-label="Contenu du post-it">
                                    <button
                                        type="button"
                                        class="sticky-note__switch-btn"
                                        role="tab"
                                        :aria-selected="listTab === 'etapes'"
                                        @click="listTab = 'etapes'"
                                    >
                                        Prochaines étapes
                                    </button>
                                    <button
                                        type="button"
                                        class="sticky-note__switch-btn"
                                        role="tab"
                                        :aria-selected="listTab === 'commentaires'"
                                        @click="listTab = 'commentaires'"
                                    >
                                        Commentaires
                                    </button>
                                </div>
                            </div>

                            <div class="sticky-note__edit">
                                <div v-if="listTab === 'etapes'" class="wb-fait-todo-section">
                                    <p v-if="draftEtapes.length === 0" class="wb-fait-todo-empty">
                                        Aucune prochaine étape.
                                    </p>
                                    <ol
                                        v-else
                                        class="sticky-note__edit-list"
                                        aria-label="Édition prochaines étapes"
                                    >
                                        <li
                                            v-for="(etape, i) in draftEtapes"
                                            :key="i"
                                            class="sticky-note__edit-row sticky-note__edit-row--etape"
                                        >
                                            <span class="sticky-note__edit-num" aria-hidden="true">{{ i + 1 }}.</span>
                                            <div class="sticky-note__edit-etape-stack">
                                                <textarea
                                                    v-model="draftEtapes[i].body"
                                                    class="sticky-note__edit-input"
                                                    rows="1"
                                                    spellcheck="false"
                                                    :placeholder="`Étape ${i + 1}`"
                                                />
                                                <div class="sticky-note__edit-etape-meta">
                                                    <select
                                                        v-model="draftEtapes[i].responsable_action_id"
                                                        class="sticky-note__edit-select"
                                                    >
                                                        <option
                                                            v-for="u in actionResponsiblesForForm"
                                                            :key="`sticky-etape-resp-${i}-${u.id}`"
                                                            :value="u.id"
                                                        >
                                                            {{ u.name }}
                                                        </option>
                                                    </select>
                                                    <input
                                                        v-model="draftEtapes[i].deadline"
                                                        type="date"
                                                        class="sticky-note__edit-date"
                                                    />
                                                    <select
                                                        v-model="draftEtapes[i].etape_status_id"
                                                        class="sticky-note__edit-select"
                                                    >
                                                        <option
                                                            v-for="s in etapeStatuses"
                                                            :key="`sticky-etape-st-${i}-${s.id}`"
                                                            :value="s.id"
                                                        >
                                                            {{ s.name }}
                                                        </option>
                                                    </select>
                                                </div>
                                            </div>
                                            <button
                                                type="button"
                                                class="sticky-note__edit-remove"
                                                :aria-label="`Supprimer l'étape ${i + 1}`"
                                                @click="removeDraftEtape(i)"
                                            >×</button>
                                        </li>
                                    </ol>
                                    <button type="button" class="wb-fait-todo-add" @click="addDraftEtape">
                                        <Plus class="size-4 shrink-0" :stroke-width="2.25" aria-hidden="true" />
                                        Ajouter une étape
                                    </button>
                                </div>
                                <div v-else class="wb-fait-todo-section">
                                    <p v-if="draftCommentaires.length === 0" class="wb-fait-todo-empty">
                                        Aucun commentaire.
                                    </p>
                                    <ol
                                        v-else
                                        class="sticky-note__edit-list"
                                        aria-label="Édition commentaires"
                                    >
                                        <li v-for="(_, i) in draftCommentaires" :key="i" class="sticky-note__edit-row">
                                            <span class="sticky-note__edit-num" aria-hidden="true">{{ i + 1 }}.</span>
                                            <textarea
                                                v-model="draftCommentaires[i]"
                                                class="sticky-note__edit-input"
                                                rows="1"
                                                spellcheck="false"
                                                :placeholder="`Commentaire ${i + 1}`"
                                            />
                                            <button
                                                type="button"
                                                class="sticky-note__edit-remove"
                                                :aria-label="`Supprimer le commentaire ${i + 1}`"
                                                @click="removeDraftCommentaire(i)"
                                            >×</button>
                                        </li>
                                    </ol>
                                    <button type="button" class="wb-fait-todo-add" @click="addDraftCommentaire">
                                        <Plus class="size-4 shrink-0" :stroke-width="2.25" aria-hidden="true" />
                                        Ajouter un commentaire
                                    </button>
                                </div>
                            </div>

                            <div class="sticky-note__footer">
                                <div
                                    class="sticky-note__metrics"
                                    :class="{ 'wb-sticky-create-metrics': showFaitFormDeadlineField }"
                                >
                                    <div class="sticky-note__metric sticky-note__metric--statut">
                                        <span class="sticky-note__metric-label">Statut</span>
                                        <div
                                            ref="stickyCreateFaitStatusDropdownRoot"
                                            class="sticky-note__status-dropdown"
                                            :class="{ 'sticky-note__status-dropdown--open': faitStatusMenuOpen && stickyCreateOpen }"
                                        >
                                            <button
                                                id="wb-sticky-fait-statut-trigger"
                                                type="button"
                                                class="sticky-note__status-trigger"
                                                :class="stickyCreateStatusTriggerClass"
                                                :style="stickyCreateStatusTriggerStyle"
                                                aria-haspopup="menu"
                                                :aria-expanded="faitStatusMenuOpen && stickyCreateOpen"
                                                aria-controls="wb-sticky-fait-statut-menu"
                                                @click.stop="toggleFaitStatusMenu"
                                            >
                                                <span class="sticky-note__status-trigger-label">{{
                                                    selectedFaitStatus?.name ?? 'Choisir un statut'
                                                }}</span>
                                                <ChevronDown class="sticky-note__status-chevron" :size="10" :stroke-width="2.5" aria-hidden="true" />
                                            </button>
                                            <ul
                                                v-show="faitStatusMenuOpen && stickyCreateOpen"
                                                id="wb-sticky-fait-statut-menu"
                                                class="sticky-note__status-menu"
                                                role="menu"
                                                aria-labelledby="wb-sticky-fait-statut-trigger"
                                            >
                                                <li v-for="s in faitStatuses" :key="s.id" role="none">
                                                    <button
                                                        type="button"
                                                        class="sticky-note__status-menu-item"
                                                        :class="stickyCreateStatusMenuItemClass(s)"
                                                        :style="stickyCreateStatusMenuItemStyle(s)"
                                                        role="menuitem"
                                                        @click.stop="pickFaitStatus(s.id)"
                                                    >
                                                        <span class="sticky-note__status-menu-item-label">{{ s.name }}</span>
                                                        <span
                                                            v-if="form.fait_status_id === s.id"
                                                            class="sticky-note__status-menu-check"
                                                            aria-hidden="true"
                                                        >✓</span>
                                                    </button>
                                                </li>
                                            </ul>
                                        </div>
                                        <p v-if="form.errors.fait_status_id" class="text-[10px] font-semibold text-destructive">
                                            {{ form.errors.fait_status_id }}
                                        </p>
                                    </div>

                                    <div v-if="showFaitFormDepartmentField" class="sticky-note__metric">
                                        <span class="sticky-note__metric-label">Département</span>
                                        <select
                                            id="wb-sticky-department"
                                            v-model.number="form.department_id"
                                            required
                                            class="wb-sticky-create-date-input"
                                        >
                                            <option disabled :value="null">
                                                Choisir
                                            </option>
                                            <option
                                                v-for="department in faitFormDepartments"
                                                :key="department.id"
                                                :value="department.id"
                                            >
                                                {{ department.name }}
                                            </option>
                                        </select>
                                        <p v-if="form.errors.department_id" class="text-[10px] font-semibold text-destructive">
                                            {{ form.errors.department_id }}
                                        </p>
                                    </div>


                                    <div class="sticky-note__metric sticky-note__metric--action">
                                        <span class="sticky-note__metric-label">Action</span>
                                        <div
                                            ref="stickyCreateWorkflowStatusDropdownRoot"
                                            class="sticky-note__status-dropdown"
                                            :class="{ 'sticky-note__status-dropdown--open': workflowStatusMenuOpen && stickyCreateOpen }"
                                        >
                                            <button
                                                id="wb-sticky-workflow-action-trigger"
                                                type="button"
                                                class="sticky-note__status-trigger"
                                                :class="stickyCreateWorkflowStatusTriggerClass"
                                                :style="stickyCreateWorkflowStatusTriggerStyle"
                                                aria-haspopup="menu"
                                                :aria-expanded="workflowStatusMenuOpen && stickyCreateOpen"
                                                aria-controls="wb-sticky-workflow-action-menu"
                                                @click.stop="toggleWorkflowStatusMenu"
                                            >
                                                <span class="sticky-note__status-trigger-label">{{
                                                    selectedWorkflowStatus?.name ?? 'Choisir une action'
                                                }}</span>
                                                <ChevronDown class="sticky-note__status-chevron" :size="10" :stroke-width="2.5" aria-hidden="true" />
                                            </button>
                                            <ul
                                                v-show="workflowStatusMenuOpen && stickyCreateOpen"
                                                id="wb-sticky-workflow-action-menu"
                                                class="sticky-note__status-menu"
                                                role="menu"
                                                aria-labelledby="wb-sticky-workflow-action-trigger"
                                            >
                                                <li v-for="w in workflowStatuses" :key="w.id" role="none">
                                                    <button
                                                        type="button"
                                                        class="sticky-note__status-menu-item"
                                                        :class="stickyCreateWorkflowStatusMenuItemClass(w)"
                                                        :style="stickyCreateWorkflowStatusMenuItemStyle(w)"
                                                        role="menuitem"
                                                        @click.stop="pickWorkflowStatus(w.id)"
                                                    >
                                                        <span class="sticky-note__status-menu-item-label">{{ w.name }}</span>
                                                        <span
                                                            v-if="form.status_id === w.id"
                                                            class="sticky-note__status-menu-check"
                                                            aria-hidden="true"
                                                        >✓</span>
                                                    </button>
                                                </li>
                                            </ul>
                                        </div>
                                        <p v-if="form.errors.status_id" class="text-[10px] font-semibold text-destructive">
                                            {{ form.errors.status_id }}
                                        </p>
                                    </div>

                                    <div class="sticky-note__metric sticky-note__metric--responsable">
                                        <span class="sticky-note__metric-label">Responsable action</span>
                                        <select
                                            id="wb-sticky-responsable-action"
                                            v-model.number="form.responsable_action_id"
                                            required
                                            class="wb-sticky-create-date-input"
                                        >
                                            <option v-if="!selectedActionResponsible" disabled :value="0">
                                                Choisir
                                            </option>
                                            <option
                                                v-for="u in actionResponsiblesForForm"
                                                :key="u.id"
                                                :value="u.id"
                                            >
                                                {{ u.name }}
                                            </option>
                                        </select>
                                        <p v-if="form.errors.responsable_action_id" class="text-[10px] font-semibold text-destructive">
                                            {{ form.errors.responsable_action_id }}
                                        </p>
                                    </div>

                                    <div v-if="showFaitFormDeadlineField" class="sticky-note__metric wb-sticky-create-deadline">
                                        <div class="sticky-note__metric-head">
                                            <span class="sticky-note__metric-label">Deadline</span>
                                        </div>
                                        <input
                                            id="wb-sticky-deadline"
                                            v-model="deadlineInput"
                                            type="date"
                                            :required="editingId === null"
                                            class="wb-sticky-create-date-input"
                                        />
                                        <p v-if="form.errors.deadline" class="text-[10px] font-semibold text-destructive">
                                            {{ form.errors.deadline }}
                                        </p>
                                    </div>
                                </div>

                                <div class="sticky-note__actions">
                                    <button
                                        type="button"
                                        class="sticky-note__action-btn sticky-note__action-btn--archive"
                                        @click="closeStickyCreate"
                                    >
                                        Annuler
                                    </button>
                                    <button
                                        type="button"
                                        class="sticky-note__action-btn sticky-note__action-btn--close"
                                        :disabled="form.processing"
                                        @click="submitFait"
                                    >
                                        {{ formSaveButtonLabel }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Floating table badge dropdown — teleported to body to escape overflow clipping -->
        <Teleport v-if="teleportReady" to="body">
            <ul
                v-if="tableDropdown !== null"
                class="wb-general-table-floating-menu wb-fait-form-status-menu"
                role="menu"
                :style="tableDropdownStyle"
            >
                <li v-for="s in faitStatuses" :key="s.id" role="none">
                    <button
                        type="button"
                        class="wb-fait-form-status-menu-item"
                        :class="faitStatusBadgeForOption(s).menuItemClass"
                        :style="faitStatusBadgeForOption(s).style"
                        role="menuitem"
                        @click.stop="pickTableFaitStatus(tableDropdown.faitId, s.id)"
                    >
                        <span class="wb-fait-form-status-menu-item-label">{{ s.name }}</span>
                        <span
                            v-if="tableDropdownFaitDraft?.fait_status_id === s.id"
                            class="wb-fait-form-status-menu-check"
                            aria-hidden="true"
                            >✓</span
                        >
                    </button>
                </li>
            </ul>
        </Teleport>
    </div>
</template>

<style scoped>
/* En-tête : grille 3 colonnes (logo | filtres centrés | actions) */
.wb-top-header {
    width: 100%;
}

.wb-top-header__row {
    display: grid;
    grid-template-columns: minmax(0, 16%) minmax(0, 1fr) minmax(0, max-content);
    align-items: center;
    gap: 0.5rem;
    width: 100%;
}

.wb-top-header__row--with-submit {
    grid-template-columns: minmax(0, 14%) minmax(0, 1fr) minmax(0, max-content);
}

.wb-top-header__brand {
    min-width: 0;
}

.wb-top-header__brand img {
    max-width: 100%;
    height: auto;
}

.wb-top-header__center {
    display: flex;
    min-width: 0;
    justify-content: center;
    overflow: hidden;
}

.wb-top-header__actions {
    display: flex;
    min-width: 0;
    flex-shrink: 0;
    flex-direction: column;
    align-items: flex-end;
    justify-content: flex-start;
    gap: 0.375rem;
}

.wb-top-header__actions-row {
    display: flex;
    min-width: 0;
    flex-shrink: 0;
    flex-wrap: nowrap;
    align-items: center;
    justify-content: flex-end;
    gap: 0.5rem;
}

.wb-top-header__meta {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    max-width: min(100%, 26rem);
}

@media (max-width: 1366px) {
    .wb-top-header__row {
        grid-template-columns: minmax(0, 18%) minmax(0, 1fr) minmax(0, max-content);
        gap: 0.4rem;
    }

    .wb-top-header__row--with-submit {
        grid-template-columns: minmax(0, 16%) minmax(0, 1fr) minmax(0, max-content);
    }

    .wb-header-submit-btn {
        padding-inline: 0.65rem;
        font-size: 0.8125rem;
    }
}

@media (max-width: 1180px) {
    .wb-top-header__row {
        grid-template-columns: minmax(0, 20%) minmax(0, 1fr) minmax(0, max-content);
        gap: 0.35rem;
    }

    .wb-top-header__row--with-submit {
        grid-template-columns: minmax(0, 18%) minmax(0, 1fr) minmax(0, max-content);
    }
}

/* Barre vue + filtres départements (une ligne, défilement horizontal) */
.wb-view-toolbar {
    display: flex;
    width: 100%;
    max-width: 100%;
    align-items: center;
    gap: 0.5rem;
    padding: 0.85rem 0.35rem 0.75rem;
    overflow: visible;
    min-width: 0;
    flex-wrap: nowrap;
    justify-content: flex-start;
}

@media (min-width: 640px) {
    .wb-view-toolbar {
        padding: 0.95rem 0.5rem 0.8rem;
    }
}

/* Le carrousel filtres doit toujours se contracter dans la zone centrale. */
.wb-view-toolbar :deep(.wb-dept-filter-carousel) {
    flex: 1 1 0;
    min-width: 0;
    max-width: 100%;
    overflow: hidden;
}

/* Segmented control : cartes / tableau (avant les filtres département) */
.wb-view-switch {
    display: inline-flex;
    flex-shrink: 0;
    gap: 2px;
    padding: 3px;
    border-radius: 9999px;
    border: 1px solid rgb(226 232 240);
    background: rgb(255 255 255 / 92%);
    box-shadow: 0 1px 3px rgb(15 23 42 / 6%);
}

.dark .wb-view-switch {
    border-color: rgb(51 65 85);
    background: rgb(30 41 59 / 92%);
    box-shadow: 0 1px 3px rgb(0 0 0 / 25%);
}

.wb-view-switch-btn {
    display: flex;
    width: 38px;
    height: 32px;
    align-items: center;
    justify-content: center;
    margin: 0;
    padding: 0;
    border: none;
    border-radius: 9999px;
    background: transparent;
    color: rgb(100 116 139);
    cursor: pointer;
    transition:
        background 0.15s ease,
        color 0.15s ease,
        box-shadow 0.15s ease,
        transform 0.1s ease;
}

.dark .wb-view-switch-btn {
    color: rgb(148 163 184);
}

.wb-view-switch-btn:hover:not(.wb-view-switch-btn--active) {
    color: rgb(67 56 202);
    background: rgb(241 245 249);
}

.dark .wb-view-switch-btn:hover:not(.wb-view-switch-btn--active) {
    color: rgb(199 210 254);
    background: rgb(51 65 85 / 80%);
}

.wb-view-switch-btn:active:not(.wb-view-switch-btn--active) {
    transform: scale(0.96);
}

.wb-view-switch-btn:focus {
    outline: none;
}

.wb-view-switch-btn:focus-visible {
    outline: 2px solid rgb(165 180 252);
    outline-offset: 1px;
}

.wb-view-switch-btn--active {
    background: linear-gradient(165deg, rgb(129 140 248), rgb(79 70 229));
    color: #ffffff;
    box-shadow:
        0 0 0 1px rgb(255 255 255 / 22%) inset,
        0 2px 8px rgb(79 70 229 / 35%);
    cursor: default;
    pointer-events: none;
}

.wb-view-toolbar-sep {
    width: 1px;
    height: 22px;
    flex-shrink: 0;
    margin: 0 2px 0 4px;
    background: rgb(226 232 240);
}

.dark .wb-view-toolbar-sep {
    background: rgb(51 65 85);
}

/* Vue tableau générale */
.wb-general-view {
    background: rgb(244 245 248);
}

.dark .wb-general-view {
    background: rgb(15 23 42 / 85%);
}

.wb-general-dept-title {
    margin: 0 0 0.875rem;
    padding-left: 0.65rem;
    border-left: 3px solid var(--wb-table-accent, rgb(124 58 237));
    font-size: 0.95rem;
    font-weight: 800;
    letter-spacing: 0.06em;
    color: rgb(30 41 59);
}

.dark .wb-general-dept-title {
    color: rgb(226 232 240);
}

.wb-general-dept-count {
    font-weight: 600;
    color: rgb(100 116 139);
}

.dark .wb-general-dept-count {
    color: rgb(148 163 184);
}

/* —— Vue tableau général —— */
.wb-general-table-wrap {
    --wb-table-accent: rgb(124 58 237);
    --wb-table-accent-soft: rgb(237 233 254);
    --wb-table-surface: rgb(255 255 255);
    --wb-table-line: rgb(226 232 240);
    --wb-table-muted: rgb(100 116 139);
    --wb-table-radius: 0.875rem;

    overflow: auto;
    border-radius: var(--wb-table-radius);
    border: 1px solid var(--wb-table-line);
    background: var(--wb-table-surface);
    box-shadow:
        0 1px 2px rgb(15 23 42 / 4%),
        0 8px 24px rgb(15 23 42 / 5%);
    scroll-behavior: smooth;
    -webkit-overflow-scrolling: touch;
}

.dark .wb-general-table-wrap {
    --wb-table-accent-soft: rgb(46 16 101 / 45%);
    --wb-table-surface: rgb(15 23 42 / 72%);
    --wb-table-line: rgb(51 65 85);
    --wb-table-muted: rgb(148 163 184);
    box-shadow:
        0 1px 2px rgb(0 0 0 / 22%),
        0 10px 28px rgb(0 0 0 / 28%);
}

.wb-general-table {
    width: 100%;
    min-width: 96rem;
    border-collapse: separate;
    border-spacing: 0;
    text-align: left;
    font-size: 0.8125rem;
    line-height: 1.45;
}

.wb-general-table__head {
    position: sticky;
    top: 0;
    z-index: 4;
}

.wb-general-table__head-row {
    background: linear-gradient(180deg, rgb(248 250 252) 0%, rgb(241 245 249) 100%);
    box-shadow: inset 0 -1px 0 var(--wb-table-line);
}

.dark .wb-general-table__head-row {
    background: linear-gradient(180deg, rgb(30 41 59) 0%, rgb(15 23 42 / 95) 100%);
}

.wb-general-table__row {
    transition: background-color 0.16s ease;
}

.wb-general-table__row--ouvert:hover {
    background: rgb(248 250 252 / 65%);
}

.wb-general-table__row--cloture {
    background: rgb(220 252 231 / 55%);
}

.wb-general-table__row--cloture:hover {
    background: rgb(187 247 208 / 45%);
}

.wb-general-table__row--archive {
    background: rgb(241 245 249 / 75%);
}

.wb-general-table__row--archive:hover {
    background: rgb(226 232 240 / 55%);
}

.dark .wb-general-table__row--ouvert:hover {
    background: rgb(30 41 59 / 35%);
}

.dark .wb-general-table__row--cloture {
    background: rgb(6 78 59 / 22%);
}

.dark .wb-general-table__row--cloture:hover {
    background: rgb(6 78 59 / 32%);
}

.dark .wb-general-table__row--archive {
    background: rgb(51 65 85 / 28%);
}

.dark .wb-general-table__row--archive:hover {
    background: rgb(51 65 85 / 42%);
}

.wb-general-table__row:hover .wb-general-td--title {
    box-shadow: inset 3px 0 0 var(--wb-table-accent);
}

.wb-general-th {
    padding: 0.7rem 0.8rem;
    font-size: 0.625rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--wb-table-muted);
    white-space: nowrap;
    vertical-align: bottom;
}

.wb-general-th--narrow {
    width: 6rem;
}

.wb-general-th--title {
    width: 17.5rem;
    min-width: 13rem;
    max-width: 17.5rem;
    white-space: normal;
    line-height: 1.35;
}

.wb-general-th--responsable {
    width: 9rem;
    min-width: 8rem;
    max-width: 9rem;
    white-space: normal;
    line-height: 1.3;
}

.wb-general-th--fait-status {
    width: 9.5rem;
    min-width: 8.5rem;
    max-width: 9.5rem;
}

.wb-general-td--fait-status {
    width: 9.5rem;
    min-width: 8.5rem;
    max-width: 9.5rem;
    vertical-align: top;
    padding-top: 0.5rem;
}

.wb-general-td--fait-status .wb-general-table-badge-dropdown {
    display: block;
}

.wb-general-status-stack {
    display: flex;
    flex-direction: column;
    align-items: stretch;
    gap: 0.25rem;
}

.wb-general-td--fait-status .wb-general-table-badge-trigger {
    display: flex;
    width: 100%;
    min-height: 1.5rem;
    padding: 0.25rem 0.35rem 0.25rem 0.5rem;
    font-size: 0.6rem;
    font-weight: 700;
    gap: 0.15rem;
    letter-spacing: 0.01em;
    line-height: 1.15;
}

.wb-general-workflow-mini-badge {
    display: inline-flex;
    width: fit-content;
    max-width: 100%;
    align-items: center;
    border-radius: 9999px;
    border: 1px solid transparent;
    padding: 0.12rem 0.42rem;
    font-size: 0.55rem;
    font-weight: 800;
    line-height: 1.15;
    letter-spacing: 0.03em;
    text-transform: uppercase;
    box-shadow: 0 1px 2px rgb(15 23 42 / 7%);
}

.wb-general-th--status {
    min-width: 8rem;
    width: 9rem;
}


.wb-general-th--date {
    width: 7.5rem;
}

.wb-general-th--deadline {
    min-width: 9.5rem;
    width: 10rem;
}

.wb-general-th--pivot {
    min-width: 11.5rem;
    width: 13.5rem;
    max-width: 28rem;
    white-space: normal;
    line-height: 1.35;
}

.wb-general-th--pivot + .wb-general-th--pivot {
    box-shadow: inset 1px 0 0 rgb(226 232 240 / 55%);
}

.dark .wb-general-th--pivot + .wb-general-th--pivot {
    box-shadow: inset 1px 0 0 rgb(51 65 85 / 55%);
}

.wb-general-th--action {
    width: 5.5rem;
    min-width: 5.5rem;
    text-align: center;
}

.wb-general-table tbody td {
    vertical-align: top;
    border-bottom: 1px solid rgb(241 245 249);
}

.dark .wb-general-table tbody td {
    border-bottom-color: rgb(30 41 59 / 85%);
}

.wb-general-table__row:last-child td {
    border-bottom: none;
}

.wb-general-td {
    padding: 0.72rem 0.8rem;
    vertical-align: top;
    color: rgb(15 23 42);
    background: transparent;
}

.dark .wb-general-td {
    color: rgb(241 245 249);
}

.wb-general-td--title {
    width: 17.5rem;
    min-width: 13rem;
    max-width: 17.5rem;
    vertical-align: top;
    padding-top: 0.62rem;
    padding-left: 0.95rem;
    font-weight: 600;
    line-height: 1.4;
    overflow-wrap: anywhere;
    word-break: break-word;
    box-shadow: inset 3px 0 0 transparent;
    transition: box-shadow 0.16s ease;
}

.wb-general-title-cell {
    display: block;
    min-width: 0;
}

.wb-general-title-text {
    display: inline;
}

.wb-general-draft-badge {
    display: inline-flex;
    width: 4.75rem;
    height: 1.25rem;
    align-items: center;
    justify-content: center;
    margin-left: 0.45rem;
    border-radius: 9999px;
    background: rgb(254 243 199);
    color: rgb(146 64 14);
    font-size: 0.64rem;
    font-weight: 800;
    line-height: 1;
    letter-spacing: 0.025em;
    text-transform: uppercase;
    vertical-align: 0.12em;
    white-space: nowrap;
    box-shadow:
        0 0 0 1px rgb(251 191 36 / 35%) inset,
        0 1px 2px rgb(146 64 14 / 10%);
}

.dark .wb-general-draft-badge {
    background: rgb(120 53 15 / 65%);
    color: rgb(254 243 199);
    box-shadow: 0 0 0 1px rgb(251 191 36 / 24%) inset;
}

.wb-general-td--responsable {
    width: 9rem;
    min-width: 8rem;
    max-width: 9rem;
    vertical-align: top;
    padding-top: 0.5rem;
    font-size: 0.78rem;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.wb-general-td--muted {
    color: rgb(71 85 105);
}

.dark .wb-general-td--muted {
    color: rgb(148 163 184);
}

.wb-general-td--deadline,
.wb-general-td--date {
    vertical-align: top;
    padding-top: 0.5rem;
}

.wb-general-td--pivot {
    min-width: 11.5rem;
    width: 13.5rem;
    max-width: 28rem;
    vertical-align: top;
    padding: 0.55rem 0.65rem;
    background: rgb(248 250 252 / 35%);
}

.dark .wb-general-td--pivot {
    background: rgb(15 23 42 / 28%);
}

.wb-general-td--pivot:has(.wb-pivot-accordion--open) {
    min-width: 34rem;
    width: clamp(34rem, 42vw, 40rem);
    max-width: none;
    padding: 0.5rem 0.6rem;
}

.wb-general-td--pivot-etapes:has(.wb-pivot-accordion--etapes.wb-pivot-accordion--open) {
    min-width: 52rem;
    width: clamp(52rem, 64vw, 68rem);
}

.wb-general-td--pivot-comments:has(.wb-pivot-accordion--comments.wb-pivot-accordion--open) {
    min-width: 22rem;
    width: clamp(22rem, 28vw, 28rem);
}

.wb-general-td--pivot-saving {
    opacity: 0.72;
    pointer-events: none;
}

.wb-general-pivot-cell {
    position: relative;
    min-width: 0;
}

/* Accordion — prochaines étapes / commentaires */
.wb-pivot-accordion {
    width: 100%;
    min-width: 0;
    border-radius: 0.75rem;
    border: 1px solid rgb(226 232 240 / 90%);
    background: linear-gradient(180deg, rgb(255 255 255) 0%, rgb(248 250 252) 100%);
    overflow: hidden;
    transition:
        border-color 0.18s ease,
        box-shadow 0.18s ease,
        transform 0.18s ease;
}

.wb-pivot-accordion:hover {
    border-color: rgb(203 213 225);
}

.wb-pivot-accordion--open {
    border-color: rgb(167 139 250 / 65%);
    box-shadow:
        0 0 0 1px rgb(139 92 246 / 10%),
        0 8px 20px rgb(109 40 217 / 12%);
}

.dark .wb-pivot-accordion {
    border-color: rgb(51 65 85);
    background: rgb(15 23 42 / 72%);
}

.dark .wb-pivot-accordion--open {
    border-color: rgb(109 40 217 / 50%);
    box-shadow:
        0 0 0 1px rgb(139 92 246 / 18%),
        0 8px 20px rgb(0 0 0 / 28%);
}

.wb-pivot-accordion__header {
    display: flex;
    align-items: stretch;
}

.wb-pivot-accordion__trigger {
    flex: 1;
    display: flex;
    min-width: 0;
    align-items: center;
    gap: 0.6rem;
    padding: 0.6rem 0.7rem;
    border: none;
    background: transparent;
    font-family: inherit;
    text-align: left;
    cursor: pointer;
}

.wb-pivot-accordion--open .wb-pivot-accordion__trigger {
    background: rgb(250 249 255 / 80%);
}

.wb-pivot-accordion__trigger:hover {
    background: rgb(248 250 252);
}

.dark .wb-pivot-accordion--open .wb-pivot-accordion__trigger {
    background: rgb(46 16 101 / 22%);
}

.dark .wb-pivot-accordion__trigger:hover {
    background: rgb(30 41 59 / 55%);
}

.wb-pivot-accordion__icon {
    display: grid;
    flex-shrink: 0;
    place-items: center;
    width: 1.75rem;
    height: 1.75rem;
    border-radius: 0.45rem;
    background: var(--wb-table-accent-soft, rgb(237 233 254));
    color: var(--wb-table-accent, rgb(124 58 237));
}

.dark .wb-pivot-accordion__icon {
    background: rgb(76 29 149 / 35%);
    color: rgb(196 181 253);
}

.wb-pivot-accordion__copy {
    display: flex;
    min-width: 0;
    flex: 1;
    flex-direction: column;
    gap: 0.1rem;
}

.wb-pivot-accordion__label {
    font-size: 0.6875rem;
    font-weight: 800;
    letter-spacing: 0.02em;
    color: rgb(30 41 59);
    line-height: 1.2;
}

.dark .wb-pivot-accordion__label {
    color: rgb(241 245 249);
}

.wb-pivot-accordion__badge {
    display: inline-flex;
    width: fit-content;
    max-width: 100%;
    align-items: center;
    border-radius: 9999px;
    padding: 0.08rem 0.45rem;
    background: rgb(237 233 254);
    font-size: 0.625rem;
    font-weight: 700;
    color: rgb(91 33 182);
    line-height: 1.25;
}

.wb-pivot-accordion__badge--empty {
    background: rgb(241 245 249);
    color: rgb(100 116 139);
}

.dark .wb-pivot-accordion__badge {
    background: rgb(76 29 149 / 40%);
    color: rgb(221 214 254);
}

.dark .wb-pivot-accordion__badge--empty {
    background: rgb(30 41 59);
    color: rgb(148 163 184);
}

.wb-pivot-accordion__state {
    display: inline-flex;
    width: fit-content;
    border-radius: 9999px;
    padding: 0.08rem 0.45rem;
    font-size: 0.625rem;
    font-weight: 800;
    letter-spacing: 0.03em;
    text-transform: uppercase;
    line-height: 1.25;
}

.wb-pivot-accordion__state--edit {
    background: rgb(254 243 199);
    color: rgb(146 64 14);
}

.dark .wb-pivot-accordion__state--edit {
    background: rgb(120 53 15 / 55%);
    color: rgb(254 243 199);
}

.wb-pivot-accordion__chev {
    flex-shrink: 0;
    margin-left: 0.15rem;
    color: rgb(148 163 184);
    transition:
        transform 0.22s cubic-bezier(0.4, 0, 0.2, 1),
        color 0.15s ease;
}

.wb-pivot-accordion--open .wb-pivot-accordion__chev,
.wb-pivot-accordion__chev--open {
    transform: rotate(180deg);
    color: var(--wb-table-accent, rgb(124 58 237));
}

.wb-pivot-accordion__action {
    display: grid;
    place-items: center;
    width: 2.5rem;
    flex-shrink: 0;
    border: none;
    border-left: 1px solid rgb(241 245 249);
    background: transparent;
    color: var(--wb-table-accent, rgb(124 58 237));
    cursor: pointer;
    transition:
        background 0.15s ease,
        color 0.15s ease;
}

.wb-pivot-accordion__action:hover:not(:disabled) {
    background: rgb(245 243 255);
    color: rgb(91 33 182);
}

.wb-pivot-accordion__action:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}

.dark .wb-pivot-accordion__action {
    border-left-color: rgb(51 65 85);
    color: rgb(196 181 253);
}

.wb-pivot-accordion__panel {
    padding: 0.5rem;
    background: rgb(248 250 252 / 70%);
    border-top: 1px solid rgb(241 245 249);
}

.wb-pivot-accordion--open .wb-pivot-accordion__panel {
    padding: 0.55rem;
    background: rgb(255 255 255);
}

.dark .wb-pivot-accordion__panel {
    background: rgb(2 6 23 / 35%);
    border-top-color: rgb(51 65 85);
}

.dark .wb-pivot-accordion--open .wb-pivot-accordion__panel {
    background: rgb(15 23 42 / 72%);
}

.wb-general-pivot-cell--editing .wb-pivot-accordion {
    border-color: rgb(139 92 246 / 60%);
    box-shadow: 0 0 0 3px rgb(139 92 246 / 14%);
}

/* Nested table (inside accordion panel) */
.wb-nested-pivot-wrap {
    width: 100%;
    min-width: 0;
    overflow-x: auto;
    overflow-y: visible;
    border-radius: 0.625rem;
    background: rgb(255 255 255);
    box-shadow: inset 0 0 0 1px rgb(226 232 240 / 95%);
}

.wb-pivot-accordion--open .wb-nested-pivot-wrap {
    width: 100%;
    min-width: 100%;
    border-radius: 0.625rem;
    box-shadow: inset 0 0 0 1px rgb(226 232 240 / 95%);
    background: rgb(255 255 255);
}

.dark .wb-nested-pivot-wrap {
    background: rgb(15 23 42 / 55%);
    box-shadow: inset 0 0 0 1px rgb(51 65 85);
}

.dark .wb-pivot-accordion--open .wb-nested-pivot-wrap {
    background: rgb(15 23 42 / 82%);
    box-shadow: inset 0 0 0 1px rgb(71 85 105 / 95%);
}

.wb-nested-pivot-table {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
    font-size: 0.71875rem;
    line-height: 1.4;
}

.wb-nested-pivot-table--read,
.wb-nested-pivot-table--edit {
    font-size: 0.8125rem;
    line-height: 1.55;
}

.wb-nested-pivot-table--read:not(.wb-nested-pivot-table--etapes),
.wb-nested-pivot-table--edit:not(.wb-nested-pivot-table--etapes) {
    table-layout: auto;
}

.wb-nested-pivot-table--etapes.wb-nested-pivot-table--read,
.wb-nested-pivot-table--etapes.wb-nested-pivot-table--edit {
    table-layout: fixed;
}

.wb-nested-pivot-table--read .wb-nested-pivot-th,
.wb-nested-pivot-table--edit .wb-nested-pivot-th {
    padding: 0.45rem 0.55rem;
    font-size: 0.625rem;
}

.wb-nested-pivot-table--read .wb-nested-pivot-td,
.wb-nested-pivot-table--edit .wb-nested-pivot-td {
    padding: 0.5rem 0.55rem;
    overflow: visible;
    line-height: 1.55;
    vertical-align: top;
}

.wb-nested-pivot-table--read .wb-nested-pivot-th--body,
.wb-nested-pivot-table--edit .wb-nested-pivot-th--body {
    width: auto;
    min-width: 14rem;
}

.wb-nested-pivot-table--etapes .wb-nested-pivot-th--body,
.wb-nested-pivot-table--etapes .wb-nested-pivot-td--body {
    min-width: 0;
}

.wb-nested-pivot-table--etapes .wb-nested-pivot-th--responsable,
.wb-nested-pivot-table--etapes .wb-nested-pivot-th--deadline,
.wb-nested-pivot-table--etapes .wb-nested-pivot-th--status {
    white-space: nowrap;
}

.wb-nested-pivot-table--read .wb-nested-pivot-td--body,
.wb-nested-pivot-table--edit .wb-nested-pivot-td--body {
    max-width: none;
    width: auto;
}

.wb-nested-pivot-table--read .wb-nested-pivot-td--deadline {
    white-space: nowrap;
    vertical-align: top;
}

.wb-nested-pivot-text {
    display: block;
    width: 100%;
    overflow-wrap: break-word;
    word-break: break-word;
    white-space: pre-wrap;
}

.wb-nested-pivot-author {
    display: block;
    margin-top: 0.15rem;
    font-size: 0.6875rem;
    font-style: italic;
    line-height: 1.2;
    color: rgb(148 163 184);
}

.dark .wb-nested-pivot-author {
    color: rgb(100 116 139);
}

.wb-nested-pivot-table--etapes .wb-nested-pivot-text--edit {
    border-radius: 0.2rem;
}

.wb-nested-pivot-text--edit {
    box-sizing: border-box;
    width: 100%;
    min-width: 0;
    min-height: 1.55em;
    margin: 0;
    padding: 0.3rem 0.4rem;
    border: 1px solid rgb(226 232 240 / 70%);
    border-radius: 0.25rem;
    background: rgb(255 255 255 / 90%);
    font-family: inherit;
    font-size: inherit;
    line-height: 1.55;
    color: rgb(30 41 59);
    resize: none;
    overflow: hidden;
    outline: none;
}

.dark .wb-nested-pivot-text--edit {
    border-color: rgb(71 85 105 / 80%);
    background: rgb(15 23 42 / 75%);
    color: rgb(226 232 240);
}

.wb-nested-pivot-text--edit::placeholder {
    color: rgb(148 163 184);
}

.wb-nested-pivot-text--edit:focus-visible {
    border-color: rgb(167 139 250 / 55%);
    background: rgb(250 249 255 / 92%);
    box-shadow: 0 0 0 2px rgb(139 92 246 / 10%);
}

.dark .wb-nested-pivot-text--edit:focus-visible {
    background: rgb(30 41 59 / 55%);
    border-color: rgb(139 92 246 / 45%);
}

.wb-nested-pivot-table--edit .wb-nested-pivot-control {
    font-size: 0.8125rem;
    line-height: 1.55;
}

.wb-nested-pivot-table--etapes.wb-nested-pivot-table--edit .wb-nested-pivot-control--compact {
    width: 100%;
    max-width: 100%;
    height: 1.95rem;
    min-height: 0;
    margin: 0;
    padding: 0 0.35rem;
    font-size: 0.8125rem;
}

.wb-nested-pivot-table--etapes.wb-nested-pivot-table--edit .wb-nested-pivot-control--select {
    padding: 0 1.3rem 0 0.35rem;
    text-overflow: clip;
}

.wb-nested-pivot-table--etapes.wb-nested-pivot-table--edit .wb-nested-pivot-td--deadline .wb-nested-pivot-control {
    padding: 0 0.2rem;
}

.wb-nested-pivot-table--edit tbody tr:nth-child(even) .wb-nested-pivot-td {
    background: rgb(248 250 252 / 70%);
}

.dark .wb-nested-pivot-table--edit tbody tr:nth-child(even) .wb-nested-pivot-td {
    background: rgb(30 41 59 / 45%);
}

.wb-nested-pivot-table--edit tbody tr:hover .wb-nested-pivot-td {
    background: rgb(245 243 255 / 85%);
}

.dark .wb-nested-pivot-table--edit tbody tr:hover .wb-nested-pivot-td {
    background: rgb(46 16 101 / 28%);
}

.wb-nested-pivot-th {
    position: sticky;
    top: 0;
    z-index: 1;
    padding: 0.45rem 0.55rem;
    border-bottom: 1px solid rgb(226 232 240);
    background: rgb(248 250 252);
    font-size: 0.5625rem;
    font-weight: 800;
    letter-spacing: 0.07em;
    text-transform: uppercase;
    color: rgb(100 116 139);
    text-align: left;
    white-space: nowrap;
}

.dark .wb-nested-pivot-th {
    border-bottom-color: rgb(51 65 85);
    background: rgb(30 41 59);
    color: rgb(148 163 184);
}

.wb-nested-pivot-table--etapes .wb-nested-pivot-col--num {
    width: 1.85rem;
}

.wb-nested-pivot-table--etapes .wb-nested-pivot-col--body {
    width: auto;
}

.wb-nested-pivot-table--etapes.wb-nested-pivot-table--read .wb-nested-pivot-col--body {
    width: 58%;
}

.wb-nested-pivot-table--etapes.wb-nested-pivot-table--edit .wb-nested-pivot-col--body {
    width: 54%;
}

.wb-nested-pivot-table--etapes .wb-nested-pivot-col--responsable {
    width: 7.5rem;
}

.wb-nested-pivot-table--etapes .wb-nested-pivot-col--deadline {
    width: 7.25rem;
}

.wb-nested-pivot-table--etapes .wb-nested-pivot-col--status {
    width: 6.5rem;
}

.wb-nested-pivot-table--etapes .wb-nested-pivot-col--action {
    width: 2.6rem;
}

/* Give "Étape" more room when accordion is open */
.wb-pivot-accordion--open .wb-nested-pivot-table--etapes .wb-nested-pivot-col--body {
    width: auto;
}

.wb-pivot-accordion--open .wb-nested-pivot-table--etapes .wb-nested-pivot-col--responsable,
.wb-pivot-accordion--open .wb-nested-pivot-table--etapes .wb-nested-pivot-th--responsable,
.wb-pivot-accordion--open .wb-nested-pivot-table--etapes .wb-nested-pivot-td--responsable {
    width: 9.25rem;
}

.wb-pivot-accordion--open .wb-nested-pivot-table--etapes .wb-nested-pivot-col--deadline,
.wb-pivot-accordion--open .wb-nested-pivot-table--etapes .wb-nested-pivot-th--deadline,
.wb-pivot-accordion--open .wb-nested-pivot-table--etapes .wb-nested-pivot-td--deadline {
    width: 8.4rem;
}

.wb-pivot-accordion--open .wb-nested-pivot-table--etapes .wb-nested-pivot-col--status,
.wb-pivot-accordion--open .wb-nested-pivot-table--etapes .wb-nested-pivot-th--status,
.wb-pivot-accordion--open .wb-nested-pivot-table--etapes .wb-nested-pivot-td--status {
    width: 8.5rem;
}

.wb-nested-pivot-table--comments .wb-nested-pivot-col--num {
    width: 1.85rem;
}

.wb-nested-pivot-table--comments .wb-nested-pivot-col--body {
    width: auto;
}

.wb-nested-pivot-table--comments .wb-nested-pivot-col--action {
    width: 2.6rem;
}

@media (max-width: 1600px) {
    .wb-general-td--pivot:has(.wb-pivot-accordion--open) {
        min-width: 28rem;
        width: clamp(28rem, 44vw, 36rem);
    }

    .wb-general-td--pivot-etapes:has(.wb-pivot-accordion--etapes.wb-pivot-accordion--open) {
        min-width: 46rem;
        width: clamp(46rem, 62vw, 58rem);
    }

    .wb-general-td--pivot-comments:has(.wb-pivot-accordion--comments.wb-pivot-accordion--open) {
        min-width: 20rem;
        width: clamp(20rem, 26vw, 25rem);
    }
}

@media (max-width: 1280px) {
    .wb-general-td--pivot:has(.wb-pivot-accordion--open) {
        min-width: 24rem;
        width: 24rem;
        padding: 0.45rem;
    }

    .wb-general-td--pivot-etapes:has(.wb-pivot-accordion--etapes.wb-pivot-accordion--open) {
        min-width: 40rem;
        width: clamp(40rem, 70vw, 48rem);
    }

    .wb-general-td--pivot-comments:has(.wb-pivot-accordion--comments.wb-pivot-accordion--open) {
        min-width: 18rem;
        width: 18rem;
    }

    .wb-nested-pivot-table--etapes .wb-nested-pivot-col--responsable {
        width: 8.5rem;
    }

    .wb-nested-pivot-table--etapes .wb-nested-pivot-col--deadline {
        width: 7.6rem;
    }

    .wb-nested-pivot-table--etapes .wb-nested-pivot-col--status {
        width: 7.6rem;
    }

    .wb-nested-pivot-table--etapes .wb-nested-pivot-th--responsable,
    .wb-nested-pivot-table--etapes .wb-nested-pivot-td--responsable {
        width: 8.5rem;
    }

    .wb-nested-pivot-table--etapes .wb-nested-pivot-th--deadline,
    .wb-nested-pivot-table--etapes .wb-nested-pivot-td--deadline {
        width: 7.6rem;
    }

    .wb-nested-pivot-table--etapes .wb-nested-pivot-th--status,
    .wb-nested-pivot-table--etapes .wb-nested-pivot-td--status {
        width: 7.6rem;
    }
}

.wb-nested-pivot-th--num {
    width: 1.85rem;
    text-align: center;
}

.wb-nested-pivot-th--body {
    width: auto;
}

.wb-nested-pivot-table--etapes .wb-nested-pivot-th--responsable,
.wb-nested-pivot-table--etapes .wb-nested-pivot-td--responsable {
    width: 7.5rem;
}

.wb-nested-pivot-table--etapes .wb-nested-pivot-th--deadline,
.wb-nested-pivot-table--etapes .wb-nested-pivot-td--deadline {
    width: 7.25rem;
}

.wb-nested-pivot-table--etapes .wb-nested-pivot-th--status,
.wb-nested-pivot-table--etapes .wb-nested-pivot-td--status {
    width: 6.5rem;
}

.wb-nested-pivot-th--action {
    width: 2.6rem;
}

.wb-nested-pivot-row .wb-nested-pivot-td {
    vertical-align: top;
}

.wb-nested-pivot-td {
    padding: 0.42rem 0.55rem;
    border-bottom: 1px solid rgb(241 245 249);
    vertical-align: top;
    color: rgb(30 41 59);
    overflow: hidden;
}

.wb-nested-pivot-table--read .wb-nested-pivot-td,
.wb-nested-pivot-table--edit .wb-nested-pivot-td {
    overflow: visible;
}

.dark .wb-nested-pivot-td {
    border-bottom-color: rgb(51 65 85 / 55%);
    color: rgb(226 232 240);
}

.wb-nested-pivot-table--read tbody tr:nth-child(even) .wb-nested-pivot-td {
    background: rgb(248 250 252 / 70%);
}

.dark .wb-nested-pivot-table--read tbody tr:nth-child(even) .wb-nested-pivot-td {
    background: rgb(30 41 59 / 45%);
}

.wb-nested-pivot-table--read tbody tr:hover .wb-nested-pivot-td {
    background: rgb(245 243 255 / 85%);
}

.dark .wb-nested-pivot-table--read tbody tr:hover .wb-nested-pivot-td {
    background: rgb(46 16 101 / 28%);
}

.wb-nested-pivot-table tbody tr:last-child .wb-nested-pivot-td {
    border-bottom: none;
}

.wb-nested-pivot-td--num {
    width: 1.85rem;
    text-align: center;
    font-size: 0.65rem;
    font-weight: 800;
    font-variant-numeric: tabular-nums;
    color: rgb(124 58 237);
}

.dark .wb-nested-pivot-td--num {
    color: rgb(196 181 253);
}

.wb-nested-pivot-td--deadline {
    white-space: nowrap;
    font-variant-numeric: tabular-nums;
}

.wb-nested-pivot-td--status {
    width: auto;
}

.wb-nested-pivot-status-pill {
    display: inline-flex;
    max-width: 100%;
    align-items: center;
    border-radius: 9999px;
    padding: 0.12rem 0.42rem;
    font-size: 0.625rem;
    font-weight: 700;
    line-height: 1.2;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.wb-nested-pivot-td--action {
    width: 2.6rem;
    text-align: center;
}

.wb-nested-pivot-empty {
    padding: 0.7rem 0.65rem;
    text-align: center;
    font-size: 0.71875rem;
    font-weight: 500;
    color: rgb(148 163 184);
    background: rgb(248 250 252 / 50%);
}

.dark .wb-nested-pivot-empty {
    background: rgb(30 41 59 / 35%);
    color: rgb(148 163 184);
}

.wb-nested-pivot-control {
    display: block;
    width: 100%;
    min-width: 0;
    height: 1.95rem;
    padding: 0 0.5rem;
    border-radius: 0.4rem;
    border: 1px solid rgb(226 232 240);
    background: rgb(255 255 255);
    font-family: inherit;
    font-size: 0.71875rem;
    color: rgb(15 23 42);
    outline: none;
    transition:
        border-color 0.14s ease,
        box-shadow 0.14s ease;
}

.wb-nested-pivot-control:focus-visible {
    border-color: rgb(139 92 246);
    box-shadow: 0 0 0 3px rgb(139 92 246 / 16%);
}

.wb-nested-pivot-control--select {
    padding-right: 1.65rem;
    cursor: pointer;
}

.wb-nested-pivot-control--compact {
    height: 2.35rem;
    min-height: 2.35rem;
}

.dark .wb-nested-pivot-control {
    border-color: rgb(71 85 105);
    background: rgb(15 23 42);
    color: rgb(241 245 249);
}

.wb-nested-pivot-row-remove {
    display: inline-grid;
    place-items: center;
    width: 1.75rem;
    height: 1.75rem;
    margin: 0 auto;
    border: none;
    border-radius: 0.4rem;
    background: transparent;
    color: rgb(148 163 184);
    cursor: pointer;
    transition:
        background 0.14s ease,
        color 0.14s ease;
}

.wb-nested-pivot-row-remove:hover {
    background: rgb(254 226 226);
    color: rgb(220 38 38);
}

.dark .wb-nested-pivot-row-remove:hover {
    background: rgb(127 29 29 / 35%);
    color: rgb(252 165 165);
}

.wb-general-pivot-add-trigger {
    display: flex;
    width: 100%;
    align-items: center;
    gap: 0.3rem;
    margin-top: 0.3rem;
    min-height: 1.625rem;
    padding: 0.22rem 0.45rem;
    border: 1px dashed rgb(196 181 253 / 70%);
    border-radius: 0.4rem;
    background: rgb(250 249 255 / 80%);
    font-family: inherit;
    font-size: 0.75rem;
    font-weight: 500;
    line-height: 1.25;
    color: rgb(124 58 237 / 85%);
    text-align: left;
    cursor: pointer;
    transition:
        border-color 0.15s ease,
        background 0.15s ease,
        color 0.15s ease,
        box-shadow 0.15s ease;
}

.wb-general-pivot-add-trigger--solo {
    margin-top: 0;
}

.wb-general-pivot-read-list + .wb-general-pivot-add-trigger {
    margin-top: 0.35rem;
}

.wb-general-pivot-add-trigger:hover:not(:disabled) {
    border-color: rgb(167 139 250);
    background: rgb(245 243 255);
    color: rgb(91 33 182);
    box-shadow: 0 0 0 2px rgb(139 92 246 / 12%);
}

.wb-general-pivot-add-trigger:disabled {
    opacity: 0.55;
    cursor: not-allowed;
}

.dark .wb-general-pivot-add-trigger {
    border-color: rgb(109 40 217 / 45%);
    background: rgb(46 16 101 / 18%);
    color: rgb(196 181 253);
}

.dark .wb-general-pivot-add-trigger:hover:not(:disabled) {
    border-color: rgb(139 92 246 / 55%);
    background: rgb(49 46 129 / 35%);
    color: rgb(221 214 254);
}

.wb-general-pivot-read-list,
.wb-general-pivot-edit-list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}

.wb-general-pivot-read-item {
    display: flex;
    align-items: baseline;
    gap: 0.45rem;
    padding: 0.15rem 0;
}

.wb-general-pivot-read-item .wb-general-pivot-num {
    width: 1.35rem;
    font-size: 0.875rem;
    line-height: 1.45;
    text-align: right;
}

.wb-general-pivot-read-text {
    flex: 1;
    min-width: 0;
    margin: 0;
    font-size: 0.875rem;
    font-weight: 500;
    line-height: 1.45;
    color: rgb(30 41 59);
    overflow-wrap: anywhere;
    word-break: break-word;
    white-space: pre-wrap;
}

.dark .wb-general-pivot-read-text {
    color: rgb(226 232 240);
}

.wb-general-pivot-edit-actions {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: flex-end;
    gap: 0.45rem;
    margin: 0.15rem 0 0;
    padding: 0.45rem 0 0;
    border-top: 1px dashed rgb(226 232 240);
}

.dark .wb-general-pivot-edit-actions {
    border-top-color: rgb(51 65 85);
}

.wb-general-pivot-edit-cancel,
.wb-general-pivot-edit-save {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    border-radius: 0.5rem;
    padding: 0.38rem 0.7rem;
    font-family: inherit;
    font-size: 0.6875rem;
    font-weight: 700;
    line-height: 1.2;
    cursor: pointer;
    transition:
        background 0.15s ease,
        border-color 0.15s ease,
        color 0.15s ease,
        box-shadow 0.15s ease,
        transform 0.12s ease;
}

.wb-general-pivot-edit-cancel {
    border: 1px solid rgb(226 232 240);
    background: rgb(255 255 255);
    color: rgb(71 85 105);
}

.wb-general-pivot-edit-cancel:hover:not(:disabled) {
    background: rgb(248 250 252);
    border-color: rgb(203 213 225);
}

.wb-general-pivot-edit-save {
    border: 1px solid rgb(109 40 217);
    background: linear-gradient(180deg, rgb(139 92 246) 0%, rgb(124 58 237) 100%);
    color: white;
    box-shadow: 0 2px 6px rgb(109 40 217 / 28%);
}

.wb-general-pivot-edit-save:hover:not(:disabled) {
    background: linear-gradient(180deg, rgb(124 58 237) 0%, rgb(109 40 217) 100%);
    box-shadow: 0 3px 10px rgb(109 40 217 / 35%);
    transform: translateY(-1px);
}

.wb-general-pivot-edit-cancel:disabled,
.wb-general-pivot-edit-save:disabled {
    opacity: 0.55;
    cursor: not-allowed;
}

.dark .wb-general-pivot-edit-cancel {
    border-color: rgb(51 65 85);
    background: rgb(30 41 59);
    color: rgb(203 213 225);
}

.dark .wb-general-pivot-edit-save {
    border-color: rgb(167 139 250);
    background: rgb(109 40 217);
}

.wb-general-pivot-edit-list .wb-general-pivot-item {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    min-height: 2rem;
    padding: 0.15rem 0.35rem 0.15rem 0.45rem;
    border-radius: 0.5rem;
    border: 1px solid rgb(226 232 240);
    border-left: 2px solid rgb(167 139 250 / 70%);
    background: rgb(248 250 252);
    transition:
        border-color 0.15s ease,
        box-shadow 0.15s ease,
        background 0.15s ease;
}

.wb-general-pivot-edit-list .wb-general-pivot-num {
    line-height: 1.4;
    text-align: right;
}

.dark .wb-general-pivot-item {
    border-color: rgb(51 65 85);
    border-left-color: rgb(139 92 246 / 55%);
    background: rgb(15 23 42 / 55%);
}

.wb-general-pivot-item:focus-within {
    border-color: rgb(167 139 250 / 65%);
    border-left-color: rgb(139 92 246);
    background: rgb(255 255 255);
    box-shadow: 0 0 0 2px rgb(139 92 246 / 14%);
}

.dark .wb-general-pivot-item:focus-within {
    background: rgb(30 27 75 / 35%);
    box-shadow: 0 0 0 2px rgb(139 92 246 / 22%);
}

.wb-general-pivot-num {
    flex-shrink: 0;
    width: 1.2rem;
    font-size: 0.75rem;
    font-weight: 700;
    font-variant-numeric: tabular-nums;
    line-height: 1;
    text-align: center;
    color: rgb(124 58 237 / 75%);
}

.dark .wb-general-pivot-num {
    color: rgb(167 139 250 / 85%);
}

.wb-general-pivot-input {
    flex: 1;
    min-width: 0;
    height: 1.85rem;
    padding: 0 0.15rem;
    border: none;
    background: transparent;
    font: inherit;
    font-size: 0.875rem;
    line-height: 1.4;
    color: rgb(15 23 42);
    outline: none;
}

.wb-general-pivot-input::placeholder {
    color: rgb(148 163 184);
    opacity: 0.9;
}

.wb-general-pivot-input:disabled {
    cursor: not-allowed;
}

.dark .wb-general-pivot-input {
    color: rgb(241 245 249);
}

.dark .wb-general-pivot-input::placeholder {
    color: rgb(100 116 139);
}

.wb-general-pivot-item--etape {
    align-items: flex-start;
}

.wb-general-pivot-etape-fields {
    display: flex;
    flex: 1;
    min-width: 0;
    flex-direction: column;
    gap: 0.35rem;
}

.wb-general-pivot-etape-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
}

.wb-general-pivot-select,
.wb-general-pivot-date {
    min-width: 0;
    max-width: 100%;
    height: 1.65rem;
    border-radius: 0.375rem;
    border: 1px solid rgb(226 232 240);
    background: rgb(255 255 255);
    padding: 0 0.4rem;
    font-size: 0.75rem;
    color: rgb(15 23 42);
}

/* Prochaines étapes — formulaire drawer (cartes structurées) */
.wb-fait-etape-list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 0.875rem;
}

.wb-fait-etape-card {
    display: flex;
    flex-direction: column;
    gap: 0.875rem;
    padding: 1rem 1.125rem;
    border-radius: 0.75rem;
    border: 1px solid rgb(226 232 240);
    background: rgb(248 250 252 / 80%);
    box-shadow: 0 1px 2px rgb(15 23 42 / 4%);
}

.dark .wb-fait-etape-card {
    border-color: rgb(51 65 85 / 70%);
    background: rgb(15 23 42 / 35%);
}

.wb-fait-etape-card:focus-within {
    border-color: rgb(167 139 250 / 55%);
    box-shadow: 0 0 0 2px rgb(139 92 246 / 12%);
}

.wb-fait-etape-card__head {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.wb-fait-etape-card__num {
    display: grid;
    place-items: center;
    width: 1.75rem;
    height: 1.75rem;
    border-radius: 9999px;
    background: rgb(237 233 254);
    font-size: 0.8125rem;
    font-weight: 700;
    color: rgb(109 40 217);
}

.dark .wb-fait-etape-card__num {
    background: rgb(76 29 149 / 35%);
    color: rgb(196 181 253);
}

.wb-fait-etape-card__title {
    flex: 1;
    min-width: 0;
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--foreground);
}

.wb-fait-etape-card__remove {
    display: grid;
    place-items: center;
    width: 2rem;
    height: 2rem;
    border: none;
    border-radius: 0.5rem;
    background: transparent;
    color: var(--muted-foreground);
    cursor: pointer;
    transition:
        background-color 0.15s ease,
        color 0.15s ease;
}

.wb-fait-etape-card__remove:hover {
    background: rgb(241 245 249);
    color: rgb(220 38 38);
}

.wb-fait-etape-card__field {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    min-width: 0;
}

.wb-fait-etape-card__meta {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.75rem 1rem;
}

@media (max-width: 720px) {
    .wb-fait-etape-card__meta {
        grid-template-columns: 1fr;
    }
}

.wb-fait-etape-control {
    width: 100%;
    min-width: 0;
    height: 2.75rem;
    padding: 0 0.75rem;
    border-radius: 0.5rem;
    border: 1px solid var(--input);
    background: var(--background);
    font-family: inherit;
    font-size: 0.9375rem;
    line-height: 1.4;
    color: var(--foreground);
    box-shadow: 0 1px 2px rgb(15 23 42 / 4%);
    outline: none;
    transition:
        border-color 0.15s ease,
        box-shadow 0.15s ease;
}

.wb-fait-etape-control:focus-visible {
    border-color: rgb(139 92 246);
    box-shadow: 0 0 0 2px rgb(139 92 246 / 20%);
}

.wb-fait-etape-control--select {
    padding-right: 2rem;
    cursor: pointer;
}

.wb-general-pivot-remove {
    flex-shrink: 0;
    display: grid;
    place-items: center;
    width: 1.5rem;
    height: 1.5rem;
    border: none;
    border-radius: 0.35rem;
    background: transparent;
    color: rgb(100 116 139);
    cursor: pointer;
    opacity: 0.55;
    transition:
        opacity 0.15s ease,
        background 0.15s ease,
        color 0.15s ease;
}

.wb-general-pivot-remove:hover:not(:disabled) {
    opacity: 1;
    background: rgb(254 226 226);
    color: rgb(220 38 38);
}

.dark .wb-general-pivot-remove:hover:not(:disabled) {
    background: rgb(127 29 29 / 35%);
    color: rgb(248 113 113);
}

.wb-general-pivot-remove:disabled {
    cursor: not-allowed;
}

.wb-general-pivot-remove:focus-visible {
    outline: 2px solid rgb(139 92 246 / 45%);
    outline-offset: 1px;
    opacity: 1;
}

.wb-general-td--num {
    text-align: right;
}

.wb-general-inline-select {
    width: 100%;
    min-height: 2rem;
    border-radius: 0.55rem;
    border: 1px solid rgb(203 213 225);
    background: rgb(255 255 255);
    padding: 0.25rem 0.6rem;
    font: inherit;
    font-size: 0.8rem;
    color: rgb(30 41 59);
}

.wb-general-table-badge-dropdown {
    position: relative;
    z-index: 1;
    width: 100%;
    min-width: 0;
}

.wb-general-table-badge-dropdown--open {
    z-index: 50;
}

.wb-general-table-badge-trigger {
    display: inline-flex;
    width: 100%;
    align-items: center;
    justify-content: space-between;
    gap: 0.35rem;
    min-height: 2rem;
    border-radius: 999px;
    border: 1px solid transparent;
    padding: 0.4rem 0.55rem 0.4rem 0.85rem;
    margin: 0;
    font: inherit;
    font-size: 0.75rem;
    font-weight: 800;
    letter-spacing: 0.02em;
    line-height: 1.25;
    cursor: pointer;
    box-shadow: 0 1px 2px rgb(15 23 42 / 6%);
    text-align: left;
    transition:
        border-color 0.12s ease,
        box-shadow 0.12s ease,
        opacity 0.12s ease;
}

.wb-general-table-badge-trigger:disabled {
    cursor: not-allowed;
    opacity: 0.65;
}

.wb-general-table-badge-trigger__label {
    min-width: 0;
    flex: 1;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.wb-general-table-badge-trigger__chev {
    flex-shrink: 0;
    transition: transform 0.2s ease;
}

.wb-general-table-badge-dropdown--open .wb-general-table-badge-trigger__chev {
    transform: rotate(180deg);
}

/*
 * Pastilles tableau : fond clair + texte foncé + bordure (maquette type badges).
 * Les classes `wb-fait-form-status-trigger--*` viennent du mapping formulaire / sticky.
 */
.wb-general-table-badge-trigger.wb-fait-form-status-trigger--bon {
    background-color: rgb(220 252 231);
    color: rgb(22 101 52);
    border-color: rgb(167 243 208);
}

.wb-general-table-badge-trigger.wb-fait-form-status-trigger--vigilance {
    background-color: rgb(255 237 213);
    color: rgb(154 52 18);
    border-color: rgb(253 186 116);
}

.wb-general-table-badge-trigger.wb-fait-form-status-trigger--critique {
    background-color: rgb(254 226 226);
    color: rgb(127 29 29);
    border-color: rgb(252 165 165);
}

.wb-general-table-badge-trigger.wb-fait-form-status-trigger--cloture {
    background-color: rgb(209 250 229);
    color: rgb(6 78 59);
    border-color: rgb(110 231 183);
}

.wb-general-table-badge-trigger.wb-fait-form-status-trigger--archivee {
    background-color: rgb(241 245 249);
    color: rgb(51 65 85);
    border-color: rgb(203 213 225);
}

.wb-general-table-badge-trigger.wb-fait-form-status-trigger--hex {
    border-color: rgb(15 23 42 / 14%);
    box-shadow: 0 1px 2px rgb(15 23 42 / 6%);
}

.dark .wb-general-table-badge-trigger.wb-fait-form-status-trigger--bon {
    background-color: rgb(20 83 45 / 35%);
    color: rgb(187 247 208);
    border-color: rgb(34 197 94 / 35%);
}

.dark .wb-general-table-badge-trigger.wb-fait-form-status-trigger--vigilance {
    background-color: rgb(154 52 18 / 28%);
    color: rgb(254 215 170);
    border-color: rgb(251 146 60 / 35%);
}

.dark .wb-general-table-badge-trigger.wb-fait-form-status-trigger--critique {
    background-color: rgb(127 29 29 / 35%);
    color: rgb(254 202 202);
    border-color: rgb(248 113 113 / 35%);
}

.dark .wb-general-table-badge-trigger.wb-fait-form-status-trigger--cloture {
    background-color: rgb(6 78 59 / 35%);
    color: rgb(167 243 208);
    border-color: rgb(52 211 153 / 35%);
}

.dark .wb-general-table-badge-trigger.wb-fait-form-status-trigger--archivee {
    background-color: rgb(30 41 59);
    color: rgb(226 232 240);
    border-color: rgb(71 85 105);
}

.dark .wb-general-table-badge-trigger.wb-fait-form-status-trigger--hex {
    border-color: rgb(148 163 184 / 35%);
}

.dark .wb-general-inline-select {
    border-color: rgb(71 85 105);
    background: rgb(15 23 42);
    color: rgb(241 245 249);
}

.dark .wb-general-table-badge-trigger {
    box-shadow: 0 1px 2px rgb(0 0 0 / 25%);
}

.wb-general-table-badge-trigger:focus-visible {
    outline: 2px solid rgb(139 92 246 / 45%);
    outline-offset: 1px;
}

.wb-general-inline-select:focus-visible {
    outline: 2px solid rgb(139 92 246 / 45%);
    outline-offset: 1px;
}

.wb-general-inline-range-wrap {
    display: flex;
    align-items: center;
    gap: 0.45rem;
}

.wb-general-inline-range {
    flex: 1;
    min-width: 0;
    cursor: pointer;
    accent-color: rgb(124 58 237);
}

.wb-general-inline-range-value {
    min-width: 2.9rem;
    text-align: right;
    font-size: 0.78rem;
    font-weight: 700;
    color: rgb(71 85 105);
}

.dark .wb-general-inline-range-value {
    color: rgb(148 163 184);
}

.wb-general-table tbody td.wb-general-td--action {
    vertical-align: middle;
}

.wb-general-td--action {
    width: 5.5rem;
    min-width: 5.5rem;
    padding-right: 0.45rem;
    padding-left: 0.45rem;
    text-align: center;
}

.wb-general-row-actions {
    display: inline-grid;
    grid-template-columns: repeat(2, minmax(0, 2rem));
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
}

.wb-general-row-action {
    display: inline-grid;
    place-items: center;
    width: 2.05rem;
    height: 2.05rem;
    flex-shrink: 0;
    border-radius: 0.5rem;
    border: 1px solid rgb(226 232 240);
    background: rgb(255 255 255);
    color: rgb(71 85 105);
    cursor: pointer;
    transition:
        border-color 0.15s ease,
        background 0.15s ease,
        color 0.15s ease,
        box-shadow 0.15s ease;
}

.wb-general-row-action:hover:not(:disabled) {
    border-color: rgb(199 210 254);
    background: rgb(245 243 255);
    color: rgb(91 33 182);
    box-shadow: 0 1px 3px rgb(99 102 241 / 12%);
}

.wb-general-row-action--edit {
    border-color: rgb(147 197 253 / 80%);
    background: rgb(239 246 255);
    color: rgb(29 78 216);
}

.wb-general-row-action--cloture:not(.wb-general-row-action--done) {
    border-color: rgb(134 239 172 / 85%);
    background: rgb(240 253 244);
    color: rgb(22 101 52);
}

.wb-general-row-action--archive:not(.wb-general-row-action--done) {
    border-color: rgb(203 213 225 / 95%);
    background: rgb(248 250 252);
    color: rgb(71 85 105);
}

.wb-general-row-action--history {
    border-color: rgb(196 181 253 / 90%);
    background: rgb(245 243 255);
    color: rgb(109 40 217);
}

.wb-general-row-action--active {
    border-color: rgb(167 139 250);
    background: rgb(237 233 254);
    color: rgb(91 33 182);
    box-shadow: 0 0 0 2px rgb(139 92 246 / 18%);
}

.wb-general-row-action:disabled {
    cursor: not-allowed;
    opacity: 0.5;
}

.wb-general-row-action:focus-visible {
    outline: 2px solid rgb(139 92 246 / 55%);
    outline-offset: 2px;
}

.dark .wb-general-row-action {
    border-color: rgb(51 65 85);
    background: rgb(30 41 59);
    color: rgb(203 213 225);
}

.dark .wb-general-row-action:hover:not(:disabled) {
    border-color: rgb(99 102 241 / 55%);
    background: rgb(49 46 129 / 45%);
    color: rgb(221 214 254);
}

.dark .wb-general-row-action--edit {
    border-color: rgb(59 130 246 / 45%);
    background: rgb(30 58 138 / 32%);
    color: rgb(147 197 253);
}

.dark .wb-general-row-action--cloture:not(.wb-general-row-action--done) {
    border-color: rgb(34 197 94 / 45%);
    background: rgb(20 83 45 / 35%);
    color: rgb(134 239 172);
}

.dark .wb-general-row-action--archive:not(.wb-general-row-action--done) {
    border-color: rgb(148 163 184 / 45%);
    background: rgb(51 65 85 / 55%);
    color: rgb(203 213 225);
}

.dark .wb-general-row-action--history {
    border-color: rgb(139 92 246 / 45%);
    background: rgb(49 46 129 / 45%);
    color: rgb(221 214 254);
}

.dark .wb-general-row-action--active {
    border-color: rgb(139 92 246 / 55%);
    background: rgb(49 46 129 / 55%);
    color: rgb(221 214 254);
    box-shadow: 0 0 0 2px rgb(139 92 246 / 25%);
}

.wb-general-row-action--cloture:hover:not(:disabled):not(.wb-general-row-action--done) {
    border-color: rgb(134 239 172);
    background: rgb(240 253 244);
    color: rgb(22 163 74);
}

.wb-general-row-action--archive:hover:not(:disabled):not(.wb-general-row-action--done) {
    border-color: rgb(203 213 225);
    background: rgb(248 250 252);
    color: rgb(71 85 105);
}

.wb-general-row-action--done {
    opacity: 1;
    cursor: default;
}

.wb-general-row-action--cloture.wb-general-row-action--done {
    border-color: rgb(134 239 172 / 80%);
    background: rgb(220 252 231 / 90%);
    color: rgb(22 101 52);
}

.wb-general-row-action--archive.wb-general-row-action--done {
    border-color: rgb(203 213 225);
    background: rgb(241 245 249);
    color: rgb(100 116 139);
}

.dark .wb-general-row-action--cloture:hover:not(:disabled):not(.wb-general-row-action--done) {
    border-color: rgb(34 197 94 / 45%);
    background: rgb(20 83 45 / 35%);
    color: rgb(134 239 172);
}

.dark .wb-general-row-action--archive:hover:not(:disabled):not(.wb-general-row-action--done) {
    border-color: rgb(148 163 184 / 45%);
    background: rgb(51 65 85 / 55%);
    color: rgb(203 213 225);
}

.dark .wb-general-row-action--cloture.wb-general-row-action--done {
    border-color: rgb(34 197 94 / 35%);
    background: rgb(20 83 45 / 40%);
    color: rgb(134 239 172);
}

.dark .wb-general-row-action--archive.wb-general-row-action--done {
    border-color: rgb(100 116 139 / 45%);
    background: rgb(51 65 85 / 65%);
    color: rgb(148 163 184);
}

.wb-general-status-pill.wb-fait-form-status-trigger {
    max-width: 11rem;
    padding: 0.25rem 0.55rem;
    font-size: 0.7rem;
    line-height: 1.25;
    white-space: normal;
}

/* Liste latérale : même design que StickyBoard.vue (.sticky-board__chip). */
.wb-faits-list-action {
    font-family: inherit;
    font-size: 0.68rem;
    font-weight: 600;
    letter-spacing: 0.02em;
    padding: 5px 10px;
    border-radius: 6px;
    border: 1px solid #e2e8f0;
    background: #f8fafc;
    color: #475569;
    cursor: pointer;
    transition:
        background 0.15s ease,
        border-color 0.15s ease,
        opacity 0.15s ease;
}

.wb-faits-list-action:hover:not(:disabled) {
    background: #f1f5f9;
    border-color: #cbd5e1;
}

.wb-faits-list-action--primary {
    border-color: #c7d2fe;
    background: #eef2ff;
    color: #4338ca;
}

.wb-faits-list-action--primary:hover:not(:disabled) {
    background: #e0e7ff;
    border-color: #a5b4fc;
}

.wb-faits-list-action:focus {
    outline: none;
}

.wb-faits-list-action:focus-visible {
    outline: 2px solid #818cf8;
    outline-offset: 2px;
}

.wb-faits-list-action:disabled {
    opacity: 0.45;
    cursor: not-allowed;
}

.dark .wb-faits-list-action {
    background: rgb(30 41 59 / 60%);
    border-color: rgb(51 65 85);
    color: rgb(203 213 225);
}

.dark .wb-faits-list-action:hover:not(:disabled) {
    background: rgb(30 41 59 / 80%);
    border-color: rgb(71 85 105);
}

.dark .wb-faits-list-action--primary {
    background: rgb(67 56 202 / 30%);
    border-color: rgb(99 102 241 / 60%);
    color: rgb(199 210 254);
}

.dark .wb-faits-list-action--primary:hover:not(:disabled) {
    background: rgb(67 56 202 / 45%);
    border-color: rgb(129 140 248);
}

.wb-faits-sidebar-groups {
    gap: 16px;
}

.wb-faits-dept-group__title {
    display: flex;
    flex-direction: column;
    gap: 2px;
    margin: 0 0 8px 2px;
    padding: 0;
    font: inherit;
}

.wb-faits-dept-group__name {
    font-size: 0.72rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.07em;
    color: #475569;
}

.wb-faits-dept-group__hint {
    font-size: 0.68rem;
    font-weight: 500;
    letter-spacing: 0.01em;
    color: #94a3b8;
    line-height: 1.3;
}

.dark .wb-faits-dept-group__name {
    color: rgb(203 213 225);
}

.dark .wb-faits-dept-group__hint {
    color: rgb(148 163 184);
}

.wb-faits-list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.wb-faits-chip {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: flex-start;
    gap: 10px;
    padding: 12px 14px;
    border-radius: 10px;
    border: 1px solid #e2e5eb;
    background: #fafbfc;
    cursor: grab;
    font-family: inherit;
    font-size: 0.92rem;
    font-weight: 500;
    color: #1e293b;
    text-align: left;
    transition:
        border-color 0.15s ease,
        box-shadow 0.15s ease,
        transform 0.1s ease;
}

.dark .wb-faits-chip {
    background: rgb(30 41 59 / 60%);
    border-color: rgb(51 65 85);
    color: rgb(226 232 240);
}

.wb-faits-chip--on-board {
    cursor: pointer;
    background: rgb(99 102 241 / 6%);
    border-color: #c7d2fe;
}

.dark .wb-faits-chip--on-board {
    background: rgb(99 102 241 / 14%);
    border-color: rgb(99 102 241 / 55%);
}

.wb-faits-chip--active {
    border-color: #6366f1;
    box-shadow:
        0 0 0 2px rgb(99 102 241 / 22%),
        0 4px 14px rgb(99 102 241 / 14%);
    background: rgb(99 102 241 / 10%);
}

.wb-faits-chip:hover {
    border-color: #c7d2fe;
    box-shadow: 0 4px 14px rgb(99 102 241 / 12%);
}

.wb-faits-chip--active:hover {
    border-color: #4f46e5;
    box-shadow:
        0 0 0 2px rgb(99 102 241 / 28%),
        0 4px 14px rgb(99 102 241 / 18%);
}

.wb-faits-chip:active {
    cursor: grabbing;
    transform: scale(0.98);
}

.wb-faits-chip--on-board:active {
    cursor: pointer;
    transform: scale(0.99);
}

.wb-faits-chip:focus-visible {
    outline: 2px solid #818cf8;
    outline-offset: 2px;
}

.wb-faits-chip__swatch {
    width: 14px;
    height: 14px;
    border-radius: 4px;
    border: 1px solid rgb(15 23 42 / 12%);
    flex-shrink: 0;
}

.wb-faits-chip__text {
    display: flex;
    flex-direction: row;
    align-items: center;
    gap: 10px;
    min-width: 0;
    flex: 1;
    align-self: stretch;
}

.wb-faits-chip__name {
    font-weight: 600;
    font-size: 0.9rem;
    line-height: 1.3;
    min-width: 0;
    flex: 1;
    text-align: left;
    overflow-wrap: anywhere;
}

.wb-faits-chip__meter {
    --sticky-ink: color-mix(in srgb, var(--sticky-bg) 24%, #0f172a);
    --sticky-progress: color-mix(in srgb, var(--sticky-bg) 50%, #1e293b);
    --sticky-progress-deep: color-mix(in srgb, var(--sticky-bg) 64%, #0f172a);
    --sticky-progress-track: color-mix(in srgb, var(--sticky-bg) 26%, rgb(15 23 42 / 12%));
    --sticky-progress-label: color-mix(in srgb, var(--sticky-bg) 30%, #334155);
    --sticky-donut-face: color-mix(in srgb, var(--sticky-progress-deep) 82%, var(--sticky-progress));
    --sticky-donut-track: color-mix(in srgb, var(--sticky-progress) 40%, #020617);
    --sticky-donut-arc: color-mix(in srgb, #ffffff 88%, var(--sticky-bg));
    --sticky-donut-label: color-mix(in srgb, #ffffff 93%, var(--sticky-bg));
    flex-shrink: 0;
    display: flex;
    align-items: center;
}

.wb-faits-chip__donut {
    flex-shrink: 0;
}

/* Panneau droit fait marquant : formulaire pleine taille */
.wb-fait-sheet {
    line-height: 1.5;
}

.wb-fait-form-label {
    display: block;
    font-size: 0.875rem;
    font-weight: 600;
    letter-spacing: -0.01em;
    color: var(--foreground);
}

/* ── Tab switch (Prochaines étapes / Commentaires) ───────────── */
.wb-fait-tab-switch {
    display: flex;
    gap: 2px;
    padding: 3px;
    border-radius: 10px;
    background: rgb(245 243 255);
    border: 1px solid rgb(221 214 254 / 80%);
}

.dark .wb-fait-tab-switch {
    background: rgb(46 16 101 / 25%);
    border-color: rgb(109 40 217 / 28%);
}

.wb-fait-tab-btn {
    flex: 1;
    margin: 0;
    padding: 0.45rem 0.75rem;
    font-size: 0.875rem;
    font-weight: 600;
    font-family: inherit;
    line-height: 1.2;
    border-radius: 7px;
    border: 1px solid transparent;
    cursor: pointer;
    color: rgb(109 40 217 / 75%);
    background: transparent;
    transition:
        background 0.16s ease,
        color 0.16s ease,
        box-shadow 0.16s ease;
}

.dark .wb-fait-tab-btn {
    color: rgb(196 181 253 / 75%);
}

.wb-fait-tab-btn:hover {
    color: rgb(109 40 217);
    background: rgb(139 92 246 / 8%);
}

.wb-fait-tab-btn[aria-selected='true'] {
    background: var(--background);
    color: rgb(109 40 217);
    border-color: rgb(196 181 253 / 65%);
    box-shadow:
        0 1px 3px rgb(109 40 217 / 10%),
        inset 0 1px 0 rgb(255 255 255 / 55%);
}

.dark .wb-fait-tab-btn[aria-selected='true'] {
    color: rgb(196 181 253);
    border-color: rgb(109 40 217 / 45%);
}

.wb-fait-tab-btn:focus-visible {
    outline: 2px solid rgb(139 92 246 / 55%);
    outline-offset: 1px;
}

/* ── Todo list ───────────────────────────────────────────────── */
.wb-fait-todo-list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.wb-fait-todo-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 4px 6px 4px 10px;
    border-radius: 8px;
    border: 1px solid rgb(221 214 254 / 55%);
    border-left: 3px solid rgb(167 139 250 / 55%);
    background: rgb(250 249 255);
    box-shadow: 0 1px 2px rgb(109 40 217 / 4%);
    transition:
        border-color 0.15s ease,
        box-shadow 0.15s ease;
}

.dark .wb-fait-todo-item {
    border-color: rgb(109 40 217 / 30%);
    border-left-color: rgb(109 40 217 / 50%);
    background: rgb(46 16 101 / 12%);
}

.wb-fait-todo-item:focus-within {
    border-color: rgb(167 139 250 / 70%);
    border-left-color: rgb(139 92 246);
    box-shadow:
        0 0 0 2px rgb(139 92 246 / 15%),
        0 1px 3px rgb(109 40 217 / 8%);
}

.wb-fait-todo-num {
    flex-shrink: 0;
    width: 1.4rem;
    font-size: 0.8125rem;
    font-weight: 700;
    font-variant-numeric: tabular-nums;
    color: rgb(139 92 246 / 70%);
    text-align: right;
    line-height: 1;
}

.wb-fait-todo-input {
    flex: 1;
    min-width: 0;
    height: 2.25rem;
    padding: 0 4px;
    font-family: inherit;
    font-size: 0.9375rem;
    line-height: 1.4;
    color: var(--foreground);
    background: transparent;
    border: none;
    outline: none;
}

.wb-fait-todo-input::placeholder {
    color: var(--muted-foreground);
    opacity: 0.65;
}

.wb-fait-todo-remove {
    flex-shrink: 0;
    width: 1.75rem;
    height: 1.75rem;
    display: grid;
    place-items: center;
    border: none;
    border-radius: 6px;
    background: transparent;
    color: var(--muted-foreground);
    font-size: 1.1rem;
    line-height: 1;
    cursor: pointer;
    opacity: 0.5;
    transition:
        opacity 0.15s ease,
        background 0.15s ease,
        color 0.15s ease;
}

.wb-fait-todo-remove:hover {
    opacity: 1;
    background: rgb(239 68 68 / 10%);
    color: rgb(220 38 38);
}

.wb-fait-todo-empty {
    padding: 0.75rem 1rem;
    border-radius: 8px;
    border: 1px dashed rgb(196 181 253 / 55%);
    background: rgb(245 243 255 / 50%);
    font-size: 0.875rem;
    color: rgb(139 92 246 / 65%);
    text-align: center;
}

.wb-fait-todo-section {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.wb-fait-todo-add {
    display: inline-flex;
    width: fit-content;
    align-items: center;
    gap: 0.4rem;
    margin-top: 0.125rem;
    padding: 0.45rem 0.85rem;
    border: 1px dashed rgb(167 139 250 / 65%);
    border-radius: 0.5rem;
    background: rgb(250 249 255);
    font-family: inherit;
    font-size: 0.8125rem;
    font-weight: 600;
    color: rgb(109 40 217);
    cursor: pointer;
    transition:
        background 0.15s ease,
        border-color 0.15s ease,
        color 0.15s ease;
}

.wb-fait-todo-add:hover {
    background: rgb(245 243 255);
    border-color: rgb(139 92 246 / 75%);
    color: rgb(91 33 182);
}

.dark .wb-fait-todo-add {
    border-color: rgb(109 40 217 / 45%);
    background: rgb(46 16 101 / 18%);
    color: rgb(196 181 253);
}

.dark .wb-fait-todo-add:hover {
    background: rgb(46 16 101 / 32%);
    border-color: rgb(139 92 246 / 55%);
}

/* ── Statut column z-index context ──────────────────────────── */
.wb-fait-form-status-col {
    position: relative;
    z-index: 2;
}

/* Statut : pastilles type post-it — StickyNoteNode.vue */
.wb-fait-form-status-dropdown {
    position: relative;
    z-index: 0;
    width: 100%;
}

.wb-fait-form-status-dropdown--open {
    z-index: 30;
}

.wb-fait-form-status-trigger {
    display: inline-flex;
    width: 100%;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    min-height: 2.75rem;
    padding: 0.5rem 0.75rem 0.5rem 0.9rem;
    border-radius: 999px;
    border: 1px solid transparent;
    cursor: pointer;
    font-family: inherit;
    font-size: 0.9375rem;
    font-weight: 700;
    letter-spacing: 0.01em;
    line-height: 1.35;
    background-clip: padding-box;
    box-shadow: 0 1px 3px rgb(15 23 42 / 10%);
    transition:
        background 0.14s ease,
        border-color 0.14s ease,
        color 0.14s ease,
        box-shadow 0.14s ease,
        transform 0.12s ease;
}

.wb-fait-form-status-trigger-label {
    min-width: 0;
    text-align: left;
    white-space: normal;
}

.wb-fait-form-status-chevron {
    flex-shrink: 0;
    transition: transform 0.2s ease;
}

.wb-fait-form-status-dropdown--open .wb-fait-form-status-chevron {
    transform: rotate(180deg);
}

.wb-fait-form-status-trigger:hover {
    transform: translateY(-0.5px);
}

.wb-fait-form-status-trigger:active {
    transform: translateY(0);
}

.wb-fait-form-status-trigger:focus {
    outline: none;
}

.wb-fait-form-status-trigger:focus-visible {
    outline: 2px solid rgb(139 92 246 / 55%);
    outline-offset: 2px;
}

.wb-fait-form-status-trigger--bon {
    background: linear-gradient(165deg, rgb(187 247 208), rgb(110 231 183));
    border-color: rgb(74 222 128 / 60%);
    color: rgb(6 78 59);
    box-shadow:
        0 0 0 1px rgb(255 255 255 / 55%) inset,
        0 2px 8px rgb(22 163 74 / 20%);
}

.wb-fait-form-status-trigger--vigilance {
    background: linear-gradient(165deg, rgb(251 146 60), rgb(234 88 12));
    border-color: rgb(194 65 12 / 58%);
    color: white;
    box-shadow:
        0 0 0 1px rgb(255 255 255 / 32%) inset,
        0 2px 8px rgb(234 88 12 / 35%);
}

.wb-fait-form-status-trigger--critique {
    background: linear-gradient(165deg, rgb(248 113 113), rgb(220 38 38));
    border-color: rgb(185 28 28 / 58%);
    color: white;
    box-shadow:
        0 0 0 1px rgb(255 255 255 / 32%) inset,
        0 2px 8px rgb(220 38 38 / 34%);
}

.wb-fait-form-status-trigger--cloture {
    background: linear-gradient(165deg, rgb(52 211 153), rgb(22 163 74));
    border-color: rgb(21 128 61 / 55%);
    color: white;
    box-shadow:
        0 0 0 1px rgb(255 255 255 / 35%) inset,
        0 2px 8px rgb(22 163 74 / 32%);
}

.wb-fait-form-status-trigger--archivee {
    background: linear-gradient(165deg, rgb(148 163 184), rgb(100 116 139));
    border-color: rgb(71 85 105 / 56%);
    color: white;
    box-shadow:
        0 0 0 1px rgb(255 255 255 / 32%) inset,
        0 2px 8px rgb(51 65 85 / 28%);
}

.wb-fait-form-status-trigger--hex {
    border-color: rgb(15 23 42 / 12%);
}

.wb-fait-form-status-menu {
    position: absolute;
    left: 0;
    right: 0;
    top: calc(100% + 6px);
    z-index: 50;
    margin: 0;
    padding: 6px;
    list-style: none;
    border-radius: 10px;
    background: var(--popover);
    border: 1px solid var(--border);
    box-shadow:
        0 4px 6px rgb(15 23 42 / 6%),
        0 18px 40px rgb(15 23 42 / 16%);
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.wb-fait-form-status-menu-item {
    display: flex;
    width: 100%;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    margin: 0;
    min-height: 2.5rem;
    padding: 0.45rem 0.65rem;
    border: 1px solid transparent;
    border-radius: 8px;
    cursor: pointer;
    font-family: inherit;
    font-size: 0.875rem;
    font-weight: 700;
    letter-spacing: 0.015em;
    text-align: left;
    background-clip: padding-box;
    transition:
        background 0.12s ease,
        border-color 0.12s ease,
        transform 0.1s ease;
}

.wb-fait-form-status-menu-item:hover {
    transform: translateY(-0.5px);
}

.wb-fait-form-status-menu-item:focus {
    outline: none;
}

.wb-fait-form-status-menu-item:focus-visible {
    outline: 2px solid rgb(139 92 246 / 45%);
    outline-offset: 0;
}

.wb-fait-form-status-menu-item-label {
    flex: 1;
    min-width: 0;
    line-height: 1.3;
    white-space: normal;
}

.wb-fait-form-status-menu-check {
    flex-shrink: 0;
    font-size: 1rem;
    font-weight: 900;
    line-height: 1;
    opacity: 0.92;
}

.wb-fait-form-status-menu-item--bon {
    background: rgb(220 252 231 / 95%);
    border-color: rgb(134 239 172 / 58%);
    color: rgb(6 78 59);
}

.wb-fait-form-status-menu-item--bon:hover {
    background: linear-gradient(165deg, rgb(187 247 208), rgb(134 239 172));
    border-color: rgb(74 222 128 / 55%);
    color: rgb(6 78 59);
}

.wb-fait-form-status-menu-item--vigilance {
    background: rgb(255 237 213 / 95%);
    border-color: rgb(251 146 60 / 55%);
    color: rgb(154 52 18);
}

.wb-fait-form-status-menu-item--vigilance:hover {
    background: linear-gradient(165deg, rgb(253 186 116), rgb(249 115 22));
    border-color: rgb(234 88 12 / 55%);
    color: rgb(67 20 7);
}

.wb-fait-form-status-menu-item--critique {
    background: rgb(254 226 226 / 95%);
    border-color: rgb(248 113 113 / 55%);
    color: rgb(127 29 29);
}

.wb-fait-form-status-menu-item--critique:hover {
    background: linear-gradient(165deg, rgb(252 165 165), rgb(239 68 68));
    border-color: rgb(220 38 38 / 55%);
    color: rgb(69 10 10);
}

.wb-fait-form-status-menu-item--cloture {
    background: rgb(209 250 229 / 95%);
    border-color: rgb(110 231 183 / 55%);
    color: rgb(6 78 59);
}

.wb-fait-form-status-menu-item--cloture:hover {
    background: linear-gradient(165deg, rgb(110 231 183), rgb(52 211 153));
    border-color: rgb(16 185 129 / 55%);
    color: rgb(6 78 59);
}

.wb-fait-form-status-menu-item--archivee {
    background: rgb(226 232 240 / 95%);
    border-color: rgb(148 163 184 / 56%);
    color: rgb(51 65 85);
}

.wb-fait-form-status-menu-item--archivee:hover {
    background: linear-gradient(165deg, rgb(203 213 225), rgb(148 163 184));
    border-color: rgb(100 116 139 / 58%);
    color: rgb(30 41 59);
}

.wb-fait-form-status-menu-item--hex:hover {
    filter: brightness(0.97);
}

/* Table status dropdown: teleported to body. Compound selector beats `.wb-fait-form-status-menu`
   (position absolute + top/left). top/left/minWidth come from inline :style — do not !important-unset them. */
.wb-general-table-floating-menu.wb-fait-form-status-menu {
    position: fixed;
    right: auto;
    margin: 0;
    z-index: 9999;
    max-width: min(10.5rem, 92vw);
    max-height: min(14rem, 80vh);
    padding: 3px;
    overflow-y: auto;
}

.wb-general-table-floating-menu.wb-fait-form-status-menu .wb-fait-form-status-menu-item {
    min-height: 1.75rem;
    padding: 0.28rem 0.4rem;
    font-size: 0.6rem;
    font-weight: 700;
    line-height: 1.15;
    border-radius: 6px;
}

.wb-general-table-floating-menu.wb-fait-form-status-menu .wb-fait-form-status-menu-item-label {
    line-height: 1.15;
}

.wb-table-history-backdrop.wb-fait-form-drawer-backdrop {
    z-index: 215;
}

.wb-table-history-drawer.wb-fait-form-drawer {
    z-index: 220;
}

.wb-table-history-readonly-item {
    align-items: flex-start;
}

.wb-table-history-readonly-item .wb-fait-todo-body {
    white-space: pre-wrap;
    word-break: break-word;
}

.wb-table-history-fait-card {
    margin-bottom: 0.25rem;
    border-radius: 14px;
    border: 1px solid rgb(221 214 254 / 0.65);
    background: linear-gradient(165deg, rgb(255 255 255) 0%, rgb(248 250 252) 48%, rgb(245 243 255) 100%);
    padding: 1.1rem 1.15rem 1.15rem;
    box-shadow:
        0 1px 2px rgb(15 23 42 / 5%),
        0 0 0 1px rgb(255 255 255 / 80%) inset;
}

.dark .wb-table-history-fait-card {
    border-color: rgb(76 29 149 / 0.45);
    background: linear-gradient(165deg, rgb(30 41 59) 0%, rgb(15 23 42 / 95%) 55%, rgb(30 27 75 / 55%) 100%);
    box-shadow: 0 1px 3px rgb(0 0 0 / 25%);
}

.wb-table-history-fait-kicker {
    margin: 0 0 0.5rem;
    font-size: 0.69rem;
    font-weight: 800;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: rgb(91 33 182);
}

.dark .wb-table-history-fait-kicker {
    color: rgb(196 181 253);
}

.wb-table-history-fait-title-wrap {
    margin-bottom: 1rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid rgb(226 232 240 / 0.9);
}

.dark .wb-table-history-fait-title-wrap {
    border-bottom-color: rgb(51 65 85);
}

.wb-table-history-fait-title {
    margin: 0;
    max-width: 100%;
    font-size: 1.08rem;
    font-weight: 700;
    line-height: 1.5;
    letter-spacing: -0.015em;
    color: rgb(15 23 42);
    white-space: pre-wrap;
    overflow-wrap: anywhere;
    word-break: break-word;
}

.dark .wb-table-history-fait-title {
    color: rgb(248 250 252);
}

.wb-table-history-fait-meta {
    margin: 0;
    display: grid;
    gap: 0.65rem 0.75rem;
}

@media (min-width: 380px) {
    .wb-table-history-fait-meta {
        grid-template-columns: minmax(6.5rem, 8.5rem) 1fr;
        align-items: start;
    }
}

.wb-table-history-fait-meta-row {
    display: contents;
}

.wb-table-history-fait-meta dt {
    margin: 0;
    padding-top: 0.2rem;
    font-size: 0.68rem;
    font-weight: 800;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: rgb(100 116 139);
}

.dark .wb-table-history-fait-meta dt {
    color: rgb(148 163 184);
}

.wb-table-history-fait-meta-dd {
    margin: 0;
    min-width: 0;
}

.wb-table-history-fait-meta-value {
    margin: 0;
    padding-top: 0.15rem;
    font-size: 0.875rem;
    font-weight: 600;
    line-height: 1.4;
    color: rgb(30 41 59);
}

.dark .wb-table-history-fait-meta-value {
    color: rgb(226 232 240);
}

.wb-table-history-meta-pill {
    display: inline-flex;
    max-width: 100%;
    align-items: center;
    justify-content: flex-start;
    min-height: 1.65rem;
    padding: 0.28rem 0.65rem;
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 700;
    line-height: 1.25;
    text-align: left;
    white-space: normal;
    overflow-wrap: anywhere;
    word-break: break-word;
}

.wb-table-history-by-day {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

.wb-table-history-day-block {
    display: flex;
    flex-direction: column;
    gap: 0.55rem;
    overflow: hidden;
    border-radius: 14px;
    border: 1px solid rgb(233 213 255 / 0.75);
    background: rgb(255 255 255);
    padding: 0;
    box-shadow: 0 2px 10px rgb(91 33 182 / 7%);
}

.dark .wb-table-history-day-block {
    border-color: rgb(76 29 149 / 0.4);
    background: rgb(15 23 42 / 55%);
    box-shadow: 0 2px 12px rgb(0 0 0 / 22%);
}

.wb-table-history-day-heading {
    margin: 0;
    padding: 0.65rem 0.85rem 0.7rem;
    text-align: center;
    font-size: 0.98rem;
    font-weight: 800;
    font-family: inherit;
    line-height: 1.4;
    letter-spacing: 0.01em;
    color: rgb(67 56 202);
    background: linear-gradient(180deg, rgb(245 243 255) 0%, rgb(250 245 255) 100%);
    border-bottom: 1px solid rgb(221 214 254 / 0.75);
}

.dark .wb-table-history-day-heading {
    color: rgb(221 214 254);
    background: linear-gradient(180deg, rgb(49 46 129 / 55%) 0%, rgb(30 27 75 / 35%) 100%);
    border-bottom-color: rgb(91 33 182 / 0.45);
}

.wb-table-history-weeks {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.wb-table-history-weeks--timeline {
    margin-top: 0;
}

.wb-table-history-week-card-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.75rem;
    margin-bottom: 0.65rem;
}

.wb-table-history-week-head-left {
    min-width: 0;
    flex: 1;
}

.wb-table-history-week-card-head .wb-table-history-week-range-iso {
    margin-bottom: 0;
}

.wb-table-history-week-workflow-badge {
    margin-top: 0.15rem;
    flex-shrink: 0;
    max-width: 11rem;
    text-align: right;
    line-height: 1.25;
}

.wb-table-history-week-dates {
    margin: 0 0 0.15rem;
    font-size: 1.02rem;
    font-weight: 800;
    line-height: 1.25;
    letter-spacing: 0.01em;
    color: rgb(30 41 59);
}

.dark .wb-table-history-week-dates {
    color: rgb(241 245 249);
}

.wb-table-history-week-range-iso {
    margin: 0 0 0.75rem;
    font-size: 0.72rem;
    font-weight: 600;
}

.wb-table-history-week-kicker {
    margin: 0 0 0.15rem;
    font-size: 0.68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: rgb(100 116 139);
}

.dark .wb-table-history-week-kicker {
    color: rgb(148 163 184);
}

.wb-table-history-week-fait-title {
    margin: 0 0 0.65rem;
    font-size: 0.95rem;
    font-weight: 700;
    line-height: 1.35;
    color: rgb(15 23 42);
}

.dark .wb-table-history-week-fait-title {
    color: rgb(248 250 252);
}

.wb-table-history-week-fait-status-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.35rem;
    margin-bottom: 0.65rem;
}

.wb-table-history-week-metrics {
    display: flex;
    flex-wrap: wrap;
    gap: 0.85rem 1.25rem;
    margin-bottom: 0.45rem;
}

.wb-table-history-week-metric {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
}

.wb-table-history-week-metric-label {
    font-size: 0.65rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: rgb(100 116 139);
}

.dark .wb-table-history-week-metric-label {
    color: rgb(148 163 184);
}

.wb-table-history-week-metric-value {
    font-size: 0.88rem;
    font-weight: 700;
    color: rgb(30 41 59);
}

.dark .wb-table-history-week-metric-value {
    color: rgb(226 232 240);
}

.wb-table-history-week-snapshot-at {
    margin: 0 0 0.65rem;
    font-size: 0.7rem;
}

.wb-table-history-week-pivot {
    margin-bottom: 0.5rem;
}

.wb-table-history-week-list-wrap {
    margin-top: 0.15rem;
}

.wb-table-history-week-block {
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
    border-radius: 14px;
    border: 1px solid rgb(233 213 255 / 0.75);
    background: rgb(255 255 255);
    padding: 0 0 0.75rem;
    box-shadow: 0 2px 10px rgb(91 33 182 / 7%);
}

.dark .wb-table-history-week-block {
    border-color: rgb(76 29 149 / 0.4);
    background: rgb(15 23 42 / 55%);
    box-shadow: 0 2px 12px rgb(0 0 0 / 22%);
}

.wb-table-history-week-block.wb-table-history-week-block--timeline {
    margin-bottom: 0.25rem;
    padding: 0.85rem 0.95rem 1rem;
    border-radius: 12px;
    border: 1px solid rgb(226 232 240 / 0.95);
    background: rgb(248 250 252 / 0.85);
    box-shadow: 0 1px 6px rgb(15 23 42 / 6%);
}

.dark .wb-table-history-week-block.wb-table-history-week-block--timeline {
    border-color: rgb(51 65 85 / 0.55);
    background: rgb(15 23 42 / 45%);
    box-shadow: 0 1px 8px rgb(0 0 0 / 25%);
}

.wb-table-history-week-heading {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    margin: 0;
    padding: 0.65rem 0.85rem 0.7rem;
    font-size: 0.95rem;
    font-weight: 800;
    line-height: 1.35;
    color: rgb(67 56 202);
    background: linear-gradient(180deg, rgb(245 243 255) 0%, rgb(250 245 255) 100%);
    border-bottom: 1px solid rgb(221 214 254 / 0.75);
}

.dark .wb-table-history-week-heading {
    color: rgb(221 214 254);
    background: linear-gradient(180deg, rgb(49 46 129 / 55%) 0%, rgb(30 27 75 / 35%) 100%);
    border-bottom-color: rgb(91 33 182 / 0.45);
}

.wb-table-history-week-iso {
    font-size: 0.72rem;
    font-weight: 600;
}

.wb-table-history-week-snapshot {
    margin: 0;
    padding: 0 0.85rem;
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    font-size: 0.85rem;
}

.wb-table-history-week-snapshot-row {
    display: grid;
    grid-template-columns: minmax(6.5rem, 38%) 1fr;
    gap: 0.35rem 0.5rem;
    align-items: baseline;
}

.wb-table-history-week-snapshot-row dt {
    margin: 0;
    font-weight: 600;
    color: rgb(100 116 139);
}

.dark .wb-table-history-week-snapshot-row dt {
    color: rgb(148 163 184);
}

.wb-table-history-week-snapshot-row dd {
    margin: 0;
    min-width: 0;
}

.wb-table-history-week-columns {
    display: grid;
    gap: 0.85rem;
    padding: 0 0.85rem;
}

@media (min-width: 480px) {
    .wb-table-history-week-columns {
        grid-template-columns: 1fr 1fr;
    }
}

.wb-table-history-week-subtitle {
    margin: 0 0 0.35rem;
    font-size: 0.78rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: rgb(100 116 139);
}

.dark .wb-table-history-week-subtitle {
    color: rgb(148 163 184);
}

.wb-table-history-day-list {
    margin: 0;
    padding: 0.35rem 0.55rem 0.65rem;
}

.wb-faits-drawer {
    position: fixed;
    top: 0;
    right: 0;
    bottom: 0;
    z-index: 55;
    width: min(100vw - 1rem, 24rem);
    max-width: 100%;
    transform: translateX(100%);
    transition: transform 0.38s cubic-bezier(0.22, 1.28, 0.36, 1);
    pointer-events: none;
}

.wb-faits-drawer--open {
    transform: translateX(0);
    pointer-events: auto;
}

.wb-fait-form-drawer-backdrop {
    position: fixed;
    inset: 0;
    z-index: 200;
    margin: 0;
    padding: 0;
    border: none;
    cursor: default;
    background: rgb(0 0 0 / 35%);
}

.wb-fait-form-drawer {
    position: fixed;
    top: 0;
    right: 0;
    bottom: 0;
    z-index: 210;
    width: min(100vw - 1.5rem, 44rem);
    max-width: 100%;
    transform: translateX(100%);
    transition: transform 0.38s cubic-bezier(0.22, 1.28, 0.36, 1);
    pointer-events: none;
}

.wb-fait-form-drawer--wide {
    width: min(60vw, calc(100vw - 1.5rem));
    max-width: none;
}

@media (max-width: 900px) {
    .wb-fait-form-drawer--wide {
        width: min(92vw, calc(100vw - 1rem));
    }
}

.wb-fait-form-drawer--open {
    transform: translateX(0);
    pointer-events: auto;
}

.wb-fait-form-drawer-body {
    font-size: 1rem;
}

@media (prefers-reduced-motion: reduce) {
    .wb-faits-drawer,
    .wb-fait-form-drawer,
    .wb-table-history-drawer {
        transition-duration: 0.01ms;
    }
}

/* Aligné sur StickyBoard.vue (.sticky-board__fab, .toolbox-drawer-*) */
.faits-sheet-fab {
    position: fixed;
    z-index: 100;
    bottom: calc(1.5rem + env(safe-area-inset-bottom, 0px));
    right: calc(1.5rem + env(safe-area-inset-right, 0px));
    transition:
        right 0.38s cubic-bezier(0.22, 1.28, 0.36, 1),
        transform 0.38s cubic-bezier(0.22, 1.28, 0.36, 1),
        box-shadow 0.25s ease,
        background-color 0.2s ease;
}

.faits-sheet-fab:hover {
    transform: scale(1.06);
}

.faits-sheet-fab:active {
    transform: scale(0.94);
}

.faits-sheet-fab--open {
    right: calc(min(100vw - 1rem, 24rem) + 0.75rem + env(safe-area-inset-right, 0px));
    box-shadow:
        0 8px 22px rgb(124 58 237 / 38%),
        0 2px 8px rgb(15 23 42 / 14%),
        inset 0 1px 0 rgb(255 255 255 / 20%);
}

.faits-sheet-fab--open:hover {
    transform: scale(1.05);
}

@media (prefers-reduced-motion: reduce) {
    .faits-sheet-fab {
        transition-duration: 0.01ms;
    }
}

/* Création flottante façon post-it (vue cartes) */
.wb-sticky-create-overlay {
    position: fixed;
    inset: 0;
    z-index: 215;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
    padding-bottom: calc(1rem + env(safe-area-inset-bottom, 0px));
    background: rgb(2 6 23 / 56%);
    backdrop-filter: blur(4px);
}

.wb-st-c-top-actions {
    position: absolute;
    top: -6px;
    right: -6px;
    z-index: 6;
    display: flex;
    align-items: center;
    gap: 8px;
}

.wb-st-c-submit {
    margin: 0;
    border: none;
    border-radius: 999px;
    cursor: pointer;
    padding: 0 10px;
    height: 22px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-family: inherit;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.02em;
    white-space: nowrap;
    color: #5b21b6;
    background: linear-gradient(155deg, rgb(255 255 255 / 94%), #ede9fe);
    box-shadow:
        inset 0 1px 0 rgb(255 255 255 / 85%),
        0 0 0 1px rgb(139 92 246 / 35%),
        0 2px 4px rgb(15 23 42 / 8%),
        0 6px 14px rgb(15 23 42 / 10%);
    transition: color 0.2s ease, box-shadow 0.2s ease, transform 0.14s ease, filter 0.2s ease;
}

.wb-st-c-submit:hover:not(:disabled) {
    color: #4c1d95;
    filter: saturate(1.08);
}

.wb-st-c-submit:disabled {
    cursor: not-allowed;
    opacity: 0.55;
}

.wb-st-c-shell {
    --sticky-ink: color-mix(in srgb, var(--sticky-bg) 24%, #0f172a);
    --sticky-ink-soft: color-mix(in srgb, var(--sticky-bg) 42%, #475569);
    --sticky-surface: color-mix(in srgb, var(--sticky-bg) 30%, white);
    --sticky-surface-strong: color-mix(in srgb, var(--sticky-bg) 52%, white);
    --sticky-edge: color-mix(in srgb, var(--sticky-bg) 58%, rgb(15 23 42 / 16%));
    --sticky-marker: color-mix(in srgb, var(--sticky-bg) 36%, #334155);
    --sticky-progress: color-mix(in srgb, var(--sticky-bg) 50%, #1e293b);
    --sticky-progress-deep: color-mix(in srgb, var(--sticky-bg) 64%, #0f172a);
    --sticky-progress-track: color-mix(in srgb, var(--sticky-bg) 26%, rgb(15 23 42 / 12%));
    --sticky-donut-face: color-mix(in srgb, var(--sticky-progress-deep) 82%, var(--sticky-progress));
    --sticky-donut-track: color-mix(in srgb, var(--sticky-progress) 40%, #020617);
    --sticky-donut-arc: color-mix(in srgb, #ffffff 88%, var(--sticky-bg));
    --sticky-donut-label: color-mix(in srgb, #ffffff 93%, var(--sticky-bg));
    --sticky-panel: color-mix(in srgb, var(--sticky-bg) 14%, white);
    --sticky-control-border: color-mix(in srgb, var(--sticky-edge) 72%, transparent);
    /* Match canvas StickyNoteNode: caps/readout + light range track on tinted paper */
    --sticky-metric-cap: rgb(255 255 255 / 96%);
    --sticky-metric-readout: rgb(255 255 255 / 98%);
    --sticky-progress-track: rgb(255 255 255 / 58%);

    position: relative;
    width: min(100%, 720px);
    max-width: 760px;
    height: min(86vh, 560px);
    font-family: 'Poppins', system-ui, sans-serif;
}

.wb-st-c-dismiss {
    position: relative;
    width: 22px;
    height: 22px;
    padding: 0;
    margin: 0;
    border: none;
    border-radius: 999px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    isolation: isolate;
    color: color-mix(in srgb, var(--sticky-ink-soft) 55%, #64748b);
    background: linear-gradient(155deg, rgb(255 255 255 / 94%), color-mix(in srgb, var(--sticky-bg) 22%, white));
    box-shadow:
        inset 0 1px 0 rgb(255 255 255 / 85%),
        0 0 0 1px color-mix(in srgb, var(--sticky-edge) 55%, rgb(255 255 255 / 40%)),
        0 2px 4px rgb(15 23 42 / 8%),
        0 6px 14px rgb(15 23 42 / 12%);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    transition: color 0.2s ease, box-shadow 0.2s ease, transform 0.14s ease, filter 0.2s ease;
}

.wb-st-c-dismiss:hover {
    color: #dc2626;
    filter: saturate(1.05);
}

.wb-st-c-dismiss-ring {
    position: absolute;
    inset: 1px;
    border-radius: inherit;
    border: 1px solid rgb(255 255 255 / 55%);
    pointer-events: none;
    opacity: 0.85;
}

.wb-st-c-dismiss-icon {
    position: relative;
    z-index: 1;
}

.sticky-note {
    position: relative;
    width: 100%;
    height: 100%;
    display: flex;
    container-type: inline-size;
    border-radius: 12px;
    border: 1px solid var(--sticky-edge);
    background: var(--sticky-face);
    background-color: var(--sticky-bg);
    box-shadow: 0 5px 10px -3px rgb(15 23 42 / 14%), 0 14px 28px -10px rgb(15 23 42 / 22%);
    overflow: hidden;
}

.sticky-note__accent {
    width: 52px;
    flex-shrink: 0;
    background: color-mix(in srgb, var(--sticky-bg) 72%, #334155);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: flex-start;
    padding: 10px 5px 8px;
    box-shadow: inset -1px 0 0 rgb(255 255 255 / 26%);
}

.sticky-note__drag {
    user-select: none;
    color: rgb(255 255 255 / 92%);
    font-size: 13px;
    letter-spacing: -2px;
    line-height: 1;
    opacity: 0.95;
}

.sticky-note__accent-tools {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 8px;
    width: 100%;
    margin-top: auto;
}

.sticky-note__accent-donut {
    flex-shrink: 0;
}

.sticky-note__body {
    flex: 1;
    padding: 14px 14px 12px 12px;
    display: flex;
    flex-direction: column;
    gap: 10px;
    min-width: 0;
    min-height: 0;
    overflow: hidden;
}

.wb-sticky-create-body {
    overflow-y: auto;
    overscroll-behavior: contain;
}

.sticky-note__title-row {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    flex-shrink: 0;
    min-width: 0;
    padding-right: 4px;
}

.sticky-note__title {
    border: 1px solid var(--sticky-control-border);
    background: var(--sticky-panel);
    border-radius: 8px;
    padding: 8px 10px;
    line-height: 1.4;
    font-family: inherit;
    font-size: clamp(15px, 1.7cqi, 18px);
    font-weight: 600;
    color: var(--sticky-ink);
    width: 100%;
    box-sizing: border-box;
    flex: 1;
    min-width: 0;
    min-height: calc(1.35em + 12px);
    cursor: text;
    resize: none;
    overflow-x: hidden;
    overflow-y: hidden;
    overflow-wrap: anywhere;
    word-break: break-word;
}

.sticky-note__title::placeholder {
    color: color-mix(in srgb, var(--sticky-ink) 42%, transparent);
}

.sticky-note__title:focus {
    outline: 2px solid color-mix(in srgb, var(--sticky-bg) 30%, #6366f1);
    outline-offset: 0;
}

.wb-sticky-create-body .sticky-note__title {
    min-height: 5.25rem;
    resize: vertical;
    overflow-y: auto;
}

.sticky-note__toolbar {
    display: flex;
    align-items: center;
    gap: 5px;
    flex-shrink: 0;
    min-width: 0;
}

.sticky-note__switch {
    display: flex;
    flex: 1;
    min-width: 0;
    gap: 1px;
    padding: 2px;
    border-radius: 8px;
    background: color-mix(in srgb, var(--sticky-bg) 20%, white);
    border: 1px solid var(--sticky-control-border);
}

.sticky-note__switch-btn {
    flex: 1;
    margin: 0;
    border: 1px solid transparent;
    padding: 8px 8px;
    font-size: clamp(13px, 1.35cqi, 15px);
    font-weight: 600;
    letter-spacing: 0.01em;
    border-radius: 6px;
    cursor: pointer;
    font-family: inherit;
    color: var(--sticky-ink-soft);
    background: transparent;
    line-height: 1.1;
    transition: background 0.16s ease, color 0.16s ease, border-color 0.16s ease, box-shadow 0.16s ease;
}

.sticky-note__switch-btn[aria-selected='true'] {
    background: color-mix(in srgb, var(--sticky-bg) 68%, white);
    color: var(--sticky-ink);
    border-color: color-mix(in srgb, var(--sticky-edge) 85%, transparent);
    box-shadow: 0 1px 3px rgb(15 23 42 / 10%), inset 0 1px 0 rgb(255 255 255 / 35%);
}

.sticky-note__edit {
    flex: 1 1 auto;
    display: flex;
    flex-direction: column;
    gap: 8px;
    min-height: 0;
}

.sticky-note__edit-list {
    list-style: none;
    margin: 0;
    padding: 0;
    border-radius: 8px;
    border: 1px solid color-mix(in srgb, var(--sticky-edge) 70%, transparent);
    background: color-mix(in srgb, var(--sticky-bg) 14%, white);
    overflow-x: hidden;
    overflow-y: auto;
    flex: 1 1 auto;
    min-height: 0;
}

.sticky-note__edit-row {
    display: grid;
    grid-template-columns: 1.7rem 1fr 28px;
    gap: 8px;
    align-items: start;
    padding: 9px 10px;
    border-bottom: 1px solid color-mix(in srgb, var(--sticky-edge) 75%, transparent);
    background: color-mix(in srgb, var(--sticky-bg) 8%, white);
}

.sticky-note__edit-row:nth-child(even) {
    background: color-mix(in srgb, var(--sticky-bg) 16%, white);
}

.sticky-note__edit-row:last-child {
    border-bottom: none;
}

.sticky-note__edit-num {
    font-size: clamp(13px, 1.2cqi, 15px);
    font-weight: 700;
    color: var(--sticky-marker);
    text-align: right;
    line-height: 1.4;
    padding-top: 7px;
}

.sticky-note__edit-input {
    width: 100%;
    min-width: 0;
    box-sizing: border-box;
    border: 1px solid color-mix(in srgb, var(--sticky-edge) 55%, transparent);
    border-radius: 6px;
    padding: 8px 10px;
    font-family: inherit;
    font-size: clamp(14px, 1.35cqi, 16px);
    line-height: 1.4;
    color: var(--sticky-ink);
    background: color-mix(in srgb, var(--sticky-bg) 6%, white);
    resize: none;
    overflow: hidden;
    min-height: calc(1.35em + 12px);
}

.sticky-note__edit-remove {
    width: 28px;
    height: 28px;
    padding: 0;
    border: none;
    border-radius: 6px;
    background: transparent;
    color: var(--sticky-ink-soft);
    font-size: 1.15rem;
    line-height: 1;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-top: 6px;
}

.sticky-note__footer {
    display: flex;
    flex-direction: column;
    gap: 10px;
    flex-shrink: 0;
    margin-top: 0;
    padding-top: 10px;
    border-top: 1px solid color-mix(in srgb, var(--sticky-edge) 50%, transparent);
}

.sticky-note__metrics {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
    grid-template-areas: "statut action";
    gap: 10px 12px;
}

.wb-sticky-create-metrics {
    grid-template-areas:
        "statut action"
        "deadline deadline";
}

.sticky-note__metric--statut {
    grid-area: statut;
}

.sticky-note__metric--action {
    grid-area: action;
}

.wb-sticky-create-deadline {
    grid-area: deadline;
}

.sticky-note__metric {
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-width: 0;
}

.sticky-note__metric-label {
    font-size: clamp(10px, 0.95cqi, 12px);
    font-weight: 800;
    letter-spacing: 0.07em;
    text-transform: uppercase;
    color: var(--sticky-metric-cap);
    line-height: 1;
    text-shadow: 0 1px 1px rgb(2 6 23 / 38%);
}

.sticky-note__metric-head {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 6px;
    min-width: 0;
}

.sticky-note__metric-readout {
    font-size: clamp(14px, 1.25cqi, 16px);
    font-weight: 800;
    letter-spacing: 0.01em;
    font-variant-numeric: tabular-nums;
    color: var(--sticky-metric-readout);
    line-height: 1;
    text-shadow: 0 1px 1px rgb(2 6 23 / 42%);
}

.sticky-note__status-dropdown {
    position: relative;
    z-index: 4;
    flex-shrink: 0;
    width: auto;
}

.sticky-note__status-dropdown--open {
    z-index: 30;
}

.sticky-note__status-trigger {
    display: inline-flex;
    align-items: center;
    justify-content: space-between;
    gap: 6px;
    width: 100%;
    cursor: pointer;
    font-family: inherit;
    font-size: clamp(12px, 1.05cqi, 14px);
    font-weight: 800;
    letter-spacing: 0.03em;
    line-height: 1.25;
    padding: 6px 10px;
    border-radius: 999px;
    border: 1px solid transparent;
    background-clip: padding-box;
    box-shadow: 0 1px 2px rgb(15 23 42 / 7%);
}

.sticky-note__status-trigger-label {
    min-width: 0;
    text-align: left;
    line-height: 1.2;
    white-space: normal;
    max-width: 14rem;
}

.sticky-note__status-chevron {
    flex-shrink: 0;
    opacity: 0.88;
    transition: transform 0.2s ease;
}

.sticky-note__status-dropdown--open .sticky-note__status-chevron {
    transform: rotate(180deg);
}

.sticky-note__status-trigger--bon {
    background: linear-gradient(165deg, rgb(187 247 208), rgb(110 231 183));
    border-color: rgb(74 222 128 / 60%);
    color: rgb(6 78 59);
    box-shadow: 0 0 0 1px rgb(255 255 255 / 55%) inset, 0 2px 8px rgb(22 163 74 / 20%);
}

.sticky-note__status-trigger--vigilance {
    background: linear-gradient(165deg, rgb(251 146 60), rgb(234 88 12));
    border-color: rgb(194 65 12 / 58%);
    color: white;
    box-shadow: 0 0 0 1px rgb(255 255 255 / 32%) inset, 0 2px 8px rgb(234 88 12 / 35%);
}

.sticky-note__status-trigger--critique {
    background: linear-gradient(165deg, rgb(248 113 113), rgb(220 38 38));
    border-color: rgb(185 28 28 / 58%);
    color: white;
    box-shadow: 0 0 0 1px rgb(255 255 255 / 32%) inset, 0 2px 8px rgb(220 38 38 / 34%);
}

.sticky-note__status-trigger--cloture {
    background: linear-gradient(165deg, rgb(52 211 153), rgb(22 163 74));
    border-color: rgb(21 128 61 / 55%);
    color: white;
    box-shadow: 0 0 0 1px rgb(255 255 255 / 35%) inset, 0 2px 8px rgb(22 163 74 / 32%);
}

.sticky-note__status-trigger--archivee {
    background: linear-gradient(165deg, rgb(148 163 184), rgb(100 116 139));
    border-color: rgb(71 85 105 / 56%);
    color: white;
    box-shadow: 0 0 0 1px rgb(255 255 255 / 32%) inset, 0 2px 8px rgb(51 65 85 / 28%);
}

.sticky-note__status-menu {
    position: absolute;
    left: 0;
    top: calc(100% + 6px);
    min-width: 100%;
    width: max-content;
    max-width: min(280px, calc(100vw - 3rem));
    margin: 0;
    padding: 8px;
    list-style: none;
    border-radius: 9px;
    background: color-mix(in srgb, var(--sticky-surface-strong) 96%, white);
    border: 1px solid color-mix(in srgb, var(--sticky-edge) 80%, rgb(15 23 42 / 8%));
    box-shadow: 0 4px 6px rgb(15 23 42 / 6%), 0 14px 36px rgb(15 23 42 / 14%);
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.sticky-note__status-menu-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    width: 100%;
    margin: 0;
    padding: 8px 10px;
    border: 1px solid transparent;
    border-radius: 7px;
    cursor: pointer;
    font-family: inherit;
    font-size: clamp(12px, 1.05cqi, 14px);
    font-weight: 800;
    letter-spacing: 0.02em;
    text-align: left;
    background-clip: padding-box;
}

.sticky-note__status-menu-item-label {
    flex: 1;
    min-width: 0;
    line-height: 1.25;
    white-space: normal;
}

.sticky-note__status-menu-check {
    flex-shrink: 0;
    font-size: 14px;
    font-weight: 900;
    line-height: 1;
    opacity: 0.92;
}

.sticky-note__status-menu-item--bon {
    background: color-mix(in srgb, rgb(220 252 231) 92%, var(--sticky-bg));
    border-color: rgb(134 239 172 / 58%);
    color: rgb(6 78 59);
}

.sticky-note__status-menu-item--vigilance {
    background: color-mix(in srgb, rgb(255 237 213) 92%, var(--sticky-bg));
    border-color: rgb(251 146 60 / 55%);
    color: rgb(154 52 18);
}

.sticky-note__status-menu-item--critique {
    background: color-mix(in srgb, rgb(254 226 226) 92%, var(--sticky-bg));
    border-color: rgb(248 113 113 / 55%);
    color: rgb(127 29 29);
}

.sticky-note__status-menu-item--cloture {
    background: color-mix(in srgb, rgb(209 250 229) 90%, var(--sticky-bg));
    border-color: rgb(110 231 183 / 55%);
    color: rgb(6 78 59);
}

.sticky-note__status-menu-item--archivee {
    background: color-mix(in srgb, rgb(226 232 240) 92%, var(--sticky-bg));
    border-color: rgb(148 163 184 / 56%);
    color: rgb(51 65 85);
}

.sticky-note__range-wrap {
    display: flex;
    width: 100%;
    cursor: pointer;
}

.sticky-note__range {
    --p: 0;
    width: 100%;
    height: 20px;
    margin: 0;
    padding: 0;
    appearance: none;
    background: transparent;
    cursor: pointer;
    --range-fill: var(--sticky-progress);
    --range-track: var(--sticky-progress-track);
}

.sticky-note__range::-webkit-slider-runnable-track {
    cursor: pointer;
    height: 6px;
    border-radius: 999px;
    background: linear-gradient(
        90deg,
        var(--range-fill) 0%,
        var(--range-fill) calc(var(--p, 0) * 1%),
        var(--range-track) calc(var(--p, 0) * 1%),
        var(--range-track) 100%
    );
    box-shadow: inset 0 1px 2px rgb(15 23 42 / 6%);
}

/* Post-it création : piste claire façon barre de progression (StickyNoteNode lecture) */
.wb-st-c-shell .sticky-note__range::-webkit-slider-runnable-track {
    height: 10px;
    box-shadow:
        inset 0 1px 2px rgb(15 23 42 / 18%),
        0 1px 0 rgb(255 255 255 / 42%);
    border: 1px solid rgb(255 255 255 / 72%);
}

.wb-st-c-shell .sticky-note__range::-webkit-slider-thumb {
    margin-top: -3px;
}

.wb-st-c-shell .sticky-note__range::-moz-range-track {
    cursor: pointer;
    height: 10px;
    border-radius: 999px;
    background: var(--range-track);
    box-shadow:
        inset 0 1px 2px rgb(15 23 42 / 18%),
        0 1px 0 rgb(255 255 255 / 42%);
    border: 1px solid rgb(255 255 255 / 72%);
}

.wb-st-c-shell .sticky-note__range::-moz-range-progress {
    height: 10px;
    border-radius: 999px 0 0 999px;
    background: var(--range-fill);
}

.sticky-note__range::-webkit-slider-thumb {
    appearance: none;
    cursor: pointer;
    width: 16px;
    height: 16px;
    margin-top: -5px;
    border-radius: 50%;
    border: 2px solid rgb(255 255 255 / 95%);
    background: linear-gradient(160deg, color-mix(in srgb, var(--sticky-bg) 32%, white), var(--sticky-progress-deep));
    box-shadow: 0 0 0 1px color-mix(in srgb, var(--sticky-edge) 70%, transparent), 0 2px 6px rgb(15 23 42 / 18%);
}

.sticky-note__actions {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
    padding-top: 8px;
    border-top: 1px dashed color-mix(in srgb, var(--sticky-edge) 45%, transparent);
}

.sticky-note__action-btn {
    border: 1px solid var(--sticky-control-border);
    border-radius: 8px;
    padding: 10px 12px;
    font-size: clamp(14px, 1.2cqi, 16px);
    font-weight: 700;
    font-family: inherit;
    cursor: pointer;
    transition: transform 0.12s ease, filter 0.12s ease;
}

.sticky-note__action-btn:disabled {
    opacity: 0.5;
    pointer-events: none;
}

.sticky-note__action-btn--close {
    color: #14532d;
    background: color-mix(in srgb, rgb(187 247 208) 72%, white);
    border-color: color-mix(in srgb, rgb(22 163 74) 35%, var(--sticky-edge));
}

.sticky-note__action-btn--archive {
    color: #1f2937;
    background: color-mix(in srgb, rgb(229 231 235) 75%, white);
    border-color: color-mix(in srgb, rgb(107 114 128) 40%, var(--sticky-edge));
}

.wb-sticky-create-date-input {
    width: 100%;
    min-height: 38px;
    border-radius: 8px;
    border: 1px solid color-mix(in srgb, var(--sticky-edge) 55%, transparent);
    background: color-mix(in srgb, var(--sticky-bg) 6%, white);
    padding: 8px 10px;
    font-size: 14px;
    line-height: 1.35;
    color: var(--sticky-ink);
    font-family: inherit;
}
</style>
