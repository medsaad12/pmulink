<script setup lang="ts">
import type { FaitMarquantView } from '@/types/faitMarquant';
import { DRAG_MIME, stickyDimensionsFromLists } from '@/lib/whiteboardProjects.js';
import { useVueFlow } from '@vue-flow/core';
import { inject, onUnmounted, watch } from 'vue';
import { flushStickyLayouts, loadStickyLayouts, makeNodeForFait } from '@/lib/whiteboardStickyFait';

const props = withDefaults(
    defineProps<{
        faitsMarquants?: FaitMarquantView[];
    }>(),
    { faitsMarquants: () => [] },
);

const {
    vueFlowRef,
    addNodes,
    updateNode,
    getNodes,
    setNodes,
    viewportHelper,
    initialized,
} = useVueFlow();

const persistStickyLayout = inject<
    (projectId: string | number, patch: Record<string, unknown>) => void
>('persistStickyLayout', () => {});

function normalizeProjectStatus(raw: unknown): string {
    const s = typeof raw === 'string' ? raw : '';
    if (
        s === 'bon' ||
        s === 'vigilance' ||
        s === 'critique' ||
        s === 'cloture' ||
        s === 'archivee'
    ) {
        return s;
    }
    if (s === 'termine') return 'cloture';
    return 'vigilance';
}

function asStringList(v: unknown): string[] {
    if (!Array.isArray(v)) return [];
    return v
        .map((x) => (typeof x === 'string' ? x : String(x ?? '')))
        .map((s) => s.trim())
        .filter((s) => s.length > 0);
}

let detach = () => {};

function bindPane() {
    detach();
    const root = vueFlowRef.value;
    if (!root) return;

    const onDragOver = (e: DragEvent) => {
        e.preventDefault();
        if (e.dataTransfer) e.dataTransfer.dropEffect = 'copy';
    };

    const onDrop = (e: DragEvent) => {
        e.preventDefault();
        const raw =
            e.dataTransfer?.getData(DRAG_MIME) || e.dataTransfer?.getData('application/json');
        if (!raw) return;

        let payload: Record<string, unknown>;
        try {
            payload = JSON.parse(raw) as Record<string, unknown>;
        } catch {
            return;
        }

        const projectIdRaw = payload.id;
        const projectId =
            typeof projectIdRaw === 'number'
                ? projectIdRaw
                : Number.parseInt(String(projectIdRaw), 10);
        if (!Number.isFinite(projectId)) return;

        const name = payload.name;
        if (typeof name !== 'string' || !name.trim()) return;

        const projectStatus = normalizeProjectStatus(payload.projectStatus);

        const faitsMarquants = asStringList(payload.faitsMarquants);
        const commentaires = asStringList(payload.commentaires);

        const fait_status_id =
            typeof payload.fait_status_id === 'number' && Number.isFinite(payload.fait_status_id)
                ? Math.round(payload.fait_status_id)
                : 1;
        const deadline =
            typeof payload.deadline === 'string' || payload.deadline === null
                ? (payload.deadline as string | null)
                : null;

        const helper = viewportHelper.value;
        if (!helper.viewportInitialized) return;

        if (
            getNodes.value.some(
                (n) => n.type === 'sticky' && Number(n.data?.projectId) === projectId,
            )
        ) {
            return;
        }

        const position = helper.screenToFlowCoordinate({
            x: e.clientX,
            y: e.clientY,
        });

        for (const n of getNodes.value) {
            if (n.selected) updateNode(n.id, { selected: false } as any);
        }

        const { width: noteW, height: noteH } = stickyDimensionsFromLists(
            faitsMarquants,
            commentaires,
        );

        const nodeId = `fait-${projectId}`;

        addNodes({
            id: nodeId,
            type: 'sticky',
            position,
            selected: true,
            width: noteW,
            height: noteH,
            data: {
                projectId,
                projectName: name.trim(),
                faitsMarquants,
                commentaires,
                stickyView: 'faits',
                userSizedNote: false,
                projectStatus,
                fait_status_id,
                deadline,
            },
        } as any);

        persistStickyLayout(projectId, {
            x: Math.round(position.x),
            y: Math.round(position.y),
            width: Math.round(noteW),
            height: Math.round(noteH),
        });
    };

    root.addEventListener('dragover', onDragOver);
    root.addEventListener('drop', onDrop);

    detach = () => {
        root.removeEventListener('dragover', onDragOver);
        root.removeEventListener('drop', onDrop);
    };
}

watch(
    [vueFlowRef, initialized],
    () => {
        if (initialized.value && vueFlowRef.value) bindPane();
    },
    { immediate: true, flush: 'post' },
);

onUnmounted(() => detach());

const COLS = 3;
const COL_W = 288;
const ROW_H = 224;
const ORIGIN_X = 40;
const ORIGIN_Y = 48;

function showAllFaitsOnBoard(): void {
    const faits = props.faitsMarquants ?? [];
    const helper = viewportHelper.value;
    const existing = new Set(
        getNodes.value
            .filter((n) => n.type === 'sticky' && n.data?.projectId != null)
            .map((n) => Number(n.data?.projectId)),
    );

    let addIndex = 0;
    for (const f of faits) {
        if (existing.has(f.id)) {
            continue;
        }

        const col = addIndex % COLS;
        const row = Math.floor(addIndex / COLS);
        addIndex++;

        let pos = {
            x: ORIGIN_X + col * COL_W,
            y: ORIGIN_Y + row * ROW_H,
        };

        if (helper?.viewportInitialized && typeof window !== 'undefined') {
            const screenX = Math.min(
                window.innerWidth - 80,
                Math.max(80, 80 + col * Math.min(160, (window.innerWidth - 160) / Math.max(1, COLS - 1))),
            );
            const screenY = 100 + row * 90;
            const flow = helper.screenToFlowCoordinate({ x: screenX, y: screenY });
            pos = { x: Math.round(flow.x), y: Math.round(flow.y) };
        }

        const node = makeNodeForFait(f, pos);
        addNodes(node);
        persistStickyLayout(f.id, {
            x: pos.x,
            y: pos.y,
            width: typeof node.width === 'number' ? Math.round(node.width) : undefined,
            height: typeof node.height === 'number' ? Math.round(node.height) : undefined,
        });
    }
}

function focusFaitOnBoard(faitId: number): void {
    const helper = viewportHelper.value;

    const existing = getNodes.value.find(
        (n) => n.type === 'sticky' && Number(n.data?.projectId) === faitId,
    );
    if (existing) {
        if (helper?.viewportInitialized) {
            void helper.fitView({
                nodes: [existing.id],
                duration: 200,
                padding: 0.2,
                maxZoom: 1.35,
            });
        }
        return;
    }

    const fait = (props.faitsMarquants ?? []).find((f) => f.id === faitId);
    if (!fait) {
        return;
    }

    let pos = { x: ORIGIN_X, y: ORIGIN_Y };
    const layouts = loadStickyLayouts();
    const saved = layouts[String(faitId)];
    if (
        saved &&
        typeof saved.x === 'number' &&
        Number.isFinite(saved.x) &&
        typeof saved.y === 'number' &&
        Number.isFinite(saved.y)
    ) {
        pos = { x: Math.round(saved.x), y: Math.round(saved.y) };
    } else if (helper?.viewportInitialized && typeof window !== 'undefined') {
        const flow = helper.screenToFlowCoordinate({
            x: Math.max(120, Math.round(window.innerWidth * 0.55)),
            y: 180,
        });
        pos = { x: Math.round(flow.x), y: Math.round(flow.y) };
    }

    const node = makeNodeForFait(fait, pos);
    addNodes(node);

    persistStickyLayout(fait.id, {
        x: pos.x,
        y: pos.y,
        width: typeof node.width === 'number' ? Math.round(node.width) : undefined,
        height: typeof node.height === 'number' ? Math.round(node.height) : undefined,
    });
}

function clearAllFaitsFromBoard(): void {
    const stickies = getNodes.value.filter((n) => n.type === 'sticky');
    if (stickies.length === 0) {
        return;
    }

    const layouts = loadStickyLayouts();
    for (const n of stickies) {
        const id = n.data?.projectId;
        if (id != null) {
            delete layouts[String(id)];
        }
    }
    flushStickyLayouts(layouts);

    setNodes(getNodes.value.filter((n) => n.type !== 'sticky'));
}

defineExpose({
    showAllFaitsOnBoard,
    focusFaitOnBoard,
    clearAllFaitsFromBoard,
});
</script>

<template>
    <span aria-hidden="true" class="hidden" />
</template>
