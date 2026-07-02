<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import type { FaitMarquantView } from '@/types/faitMarquant';
import { Background } from '@vue-flow/background';
import { Controls } from '@vue-flow/controls';
import { VueFlow } from '@vue-flow/core';
import { computed, markRaw, onMounted, onUnmounted, provide, ref, watch } from 'vue';
import FlowDropListener from '@/components/whiteboard/FlowDropListener.vue';
import {
    flushStickyLayouts,
    loadStickyLayouts,
    makeNodeForFait,
    rowsToBodies,
    stickyDataPayload,
} from '@/lib/whiteboardStickyFait';
import StickyNoteNode from '@/components/whiteboard/StickyNoteNode.vue';
import '@vue-flow/core/dist/style.css';
import '@vue-flow/controls/dist/style.css';

const props = withDefaults(
    defineProps<{
        fullscreen?: boolean;
        faitsMarquants?: FaitMarquantView[];
        /** Faux quand le panneau tableau est affiché (évite mesures titre scrollHeight=0). */
        boardVisible?: boolean;
        /** Si défini, les post-its des faits non éditables restent en lecture seule (pas de sync API). */
        isFaitEditable?: (faitId: number) => boolean;
    }>(),
    { fullscreen: false, faitsMarquants: () => [], boardVisible: true },
);

const stickyBoardVisible = computed(() => props.boardVisible !== false);
provide('stickyBoardVisible', stickyBoardVisible);

const emit = defineEmits<{
    edit: [id: number];
    'board-project-ids': [ids: number[]];
}>();

provide('stickyBoardRequestEdit', (rawId: string) => {
    const s = rawId.startsWith('fait-') ? rawId.slice(5) : rawId;
    const num = Number.parseInt(s, 10);
    if (!Number.isNaN(num)) emit('edit', num);
});

const nodeTypes = {
    sticky: markRaw(StickyNoteNode),
} as any;

const nodes = ref<any[]>([]);
const flowMounted = ref(false);
const stickySyncTimeoutById = new Map<number, ReturnType<typeof setTimeout>>();

/** Inertia ne met pas à jour les props après un `fetch` vers sticky-sync ; la liste latérale lit `faitsMarquants`. */
let reloadFaitsMarquantsTimer: ReturnType<typeof setTimeout> | null = null;

function scheduleFaitsMarquantsPropsReload(): void {
    if (reloadFaitsMarquantsTimer !== null) {
        clearTimeout(reloadFaitsMarquantsTimer);
    }
    reloadFaitsMarquantsTimer = setTimeout(() => {
        reloadFaitsMarquantsTimer = null;
        router.reload({
            only: ['faitsMarquants'],
        });
    }, 200);
}

function getCsrfToken(): string | undefined {
    if (typeof document === 'undefined') return undefined;
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? undefined;
}

function persistStickyLayoutForProject(projectId: string | number, patch: Record<string, unknown>): void {
    const key = String(projectId);
    const prev = loadStickyLayouts();
    const cur = prev[key] || {};
    const next: { x: number; y: number; width?: number; height?: number } = { ...cur } as {
        x: number;
        y: number;
        width?: number;
        height?: number;
    };
    if (typeof patch.x === 'number' && Number.isFinite(patch.x)) next.x = Math.round(patch.x);
    if (typeof patch.y === 'number' && Number.isFinite(patch.y)) next.y = Math.round(patch.y);
    if (typeof patch.width === 'number' && patch.width >= 200) next.width = Math.round(patch.width);
    if (typeof patch.height === 'number' && patch.height >= 168) next.height = Math.round(patch.height);
    if (typeof next.x !== 'number' || typeof next.y !== 'number') return;
    flushStickyLayouts({ ...prev, [key]: next });
}

provide('persistStickyLayout', persistStickyLayoutForProject);

function syncNodesWithPropsAndLayouts(): void {
    const faits = props.faitsMarquants ?? [];
    const idSet = new Set(faits.map((x) => x.id));
    nodes.value = nodes.value.filter(
        (n) => n.type !== 'sticky' || idSet.has(Number(n.data?.projectId)),
    );

    const layouts = loadStickyLayouts();

    for (const n of nodes.value) {
        if (n.type !== 'sticky') continue;
        const pid = Number(n.data?.projectId);
        const f = faits.find((x) => x.id === pid);
        if (f) {
            const editable = props.isFaitEditable?.(f.id) ?? true;
            n.data = { ...n.data, ...stickyDataPayload(f), readOnly: !editable };
        }
    }

    const onBoard = new Set(
        nodes.value.filter((n) => n.type === 'sticky').map((n) => Number(n.data?.projectId)),
    );

    for (const f of faits) {
        if (onBoard.has(f.id)) continue;
        const s = layouts[String(f.id)];
        if (s && typeof s.x === 'number' && Number.isFinite(s.x) && typeof s.y === 'number' && Number.isFinite(s.y)) {
            const node = makeNodeForFait(f, { x: s.x, y: s.y }, s.width, s.height);
            const editable = props.isFaitEditable?.(f.id) ?? true;
            node.data = { ...(node.data as Record<string, unknown>), readOnly: !editable };
            nodes.value = [...nodes.value, node];
            onBoard.add(f.id);
        }
    }
}

watch(
    nodes,
    (list) => {
        emit(
            'board-project-ids',
            list
                .filter((n) => n.type === 'sticky' && n.data?.projectId != null)
                .map((n) => Number(n.data.projectId))
                .filter((id) => !Number.isNaN(id)),
        );
    },
    { deep: true, immediate: true },
);

watch(
    () => props.faitsMarquants,
    () => {
        if (!flowMounted.value) {
            return;
        }
        syncNodesWithPropsAndLayouts();
    },
    { deep: true },
);

onMounted(() => {
    flowMounted.value = true;
    syncNodesWithPropsAndLayouts();
});

function layoutPatchFromStickyNode(n: any): Record<string, unknown> | null {
    if (n.type !== 'sticky' || n.data?.projectId == null) return null;
    const patch: Record<string, unknown> = { x: n.position.x, y: n.position.y };
    if (typeof n.width === 'number' && n.width > 0) patch.width = n.width;
    else if (n.dimensions?.width > 0) patch.width = n.dimensions.width;
    if (typeof n.height === 'number' && n.height > 0) patch.height = n.height;
    else if (n.dimensions?.height > 0) patch.height = n.dimensions.height;
    return patch;
}

function onStickyLayoutPersistFromDrag(evt: { nodes?: any[]; node?: any }) {
    const list = evt?.nodes?.length ? evt.nodes : evt?.node ? [evt.node] : [];
    for (const n of list) {
        const patch = layoutPatchFromStickyNode(n);
        const pid = n?.data?.projectId;
        if (patch && pid != null) persistStickyLayoutForProject(pid, patch);
    }
}

function syncStickyProjectMeta(projectId: number, patch: Record<string, unknown>): void {
    if (props.isFaitEditable?.(projectId) === false) {
        return;
    }

    const node = nodes.value.find(
        (n) => n.type === 'sticky' && Number(n.data?.projectId) === projectId,
    );
    if (!node || !node.data) {
        return;
    }
    node.data = { ...(node.data as Record<string, unknown>), ...patch };

    const existing = stickySyncTimeoutById.get(projectId);
    if (existing) {
        clearTimeout(existing);
    }

    stickySyncTimeoutById.set(
        projectId,
        setTimeout(() => {
            const latestNode = nodes.value.find(
                (n) => n.type === 'sticky' && Number(n.data?.projectId) === projectId,
            );
            const d = (latestNode?.data ?? {}) as Record<string, unknown>;
            const payload: Record<string, unknown> = {
                projectName: typeof d.projectName === 'string' ? d.projectName : '',
                fait_status_id:
                    typeof d.fait_status_id === 'number' && Number.isFinite(d.fait_status_id)
                        ? Math.round(d.fait_status_id)
                        : 1,
                faitsMarquants: rowsToBodies(d.faitsMarquants),
                commentaires: rowsToBodies(d.commentaires),
            };

            const wf = d.workflowAction;
            if (wf === 'cloture' || wf === 'archive') {
                payload.workflow_action = wf;
            }

            void fetch(`/faits-marquants/${projectId}/sticky-sync`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...(getCsrfToken() ? { 'X-CSRF-TOKEN': getCsrfToken()! } : {}),
                },
                credentials: 'same-origin',
                body: JSON.stringify(payload),
            })
                .then((res) => {
                    if (!res.ok) return;
                    scheduleFaitsMarquantsPropsReload();
                    const ln = nodes.value.find(
                        (n) => n.type === 'sticky' && Number(n.data?.projectId) === projectId,
                    );
                    if (!ln?.data || typeof ln.data !== 'object') return;
                    const data = ln.data as Record<string, unknown>;
                    if ('workflowAction' in data) {
                        const { workflowAction: _omit, ...rest } = data;
                        ln.data = { ...rest } as typeof ln.data;
                    }
                })
                .catch(() => {});
        }, 350),
    );
}

provide('syncStickyProjectMeta', syncStickyProjectMeta);

onUnmounted(() => {
    if (reloadFaitsMarquantsTimer !== null) {
        clearTimeout(reloadFaitsMarquantsTimer);
        reloadFaitsMarquantsTimer = null;
    }
});

const flowDropListenerRef = ref<InstanceType<typeof FlowDropListener> | null>(null);

defineExpose({
    showAllFaitsOnBoard: () => flowDropListenerRef.value?.showAllFaitsOnBoard(),
    focusFaitOnBoard: (id: number) => flowDropListenerRef.value?.focusFaitOnBoard(id),
    clearAllFaitsFromBoard: () => flowDropListenerRef.value?.clearAllFaitsFromBoard(),
});
</script>

<template>
    <div
        class="sticky-board relative isolate h-full w-full overflow-hidden bg-white"
        :class="
            fullscreen
                ? 'min-h-0 rounded-none border-0'
                : 'min-h-[22rem] rounded-lg border border-border bg-white dark:bg-background'
        "
    >
        <VueFlow
            v-if="flowMounted"
            id="sticky-board"
            v-model:nodes="nodes"
            :node-types="nodeTypes"
            :nodes-draggable="true"
            :nodes-connectable="false"
            :elements-selectable="true"
            :snap-to-grid="true"
            :snap-grid="[16, 16]"
            :pan-on-scroll="true"
            :zoom-on-scroll="true"
            :min-zoom="0.25"
            :max-zoom="1.75"
            fit-view-on-init
            :fit-view-on-init-options="{ padding: 0.2, maxZoom: 1 }"
            class="sticky-board-flow h-full w-full"
            @node-drag-stop="onStickyLayoutPersistFromDrag"
            @selection-drag-stop="onStickyLayoutPersistFromDrag"
        >
            <FlowDropListener
                ref="flowDropListenerRef"
                :faits-marquants="faitsMarquants ?? []"
            />
            <Background variant="dots" :gap="20" :size="1.2" pattern-color="#c7cad4" />
            <Controls
                class="border border-border bg-background/95 shadow-sm [&_button]:border-border [&_button]:bg-background"
            />
        </VueFlow>
    </div>
</template>

<style scoped>
.sticky-board-flow :deep(.vue-flow__pane) {
    cursor: grab;
}

.sticky-board-flow :deep(.vue-flow__pane:active) {
    cursor: grabbing;
}
</style>
