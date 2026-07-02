import type { Node } from '@vue-flow/core';
import type { FaitMarquantView } from '@/types/faitMarquant';
import { stickyDimensionsFromLists } from '@/lib/whiteboardProjects.js';

export const WHITEBOARD_STICKY_LAYOUT_KEY = 'comexlink-whiteboard-sticky-layouts-v1';

export function rowsToBodies(rows: unknown): string[] {
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
                r.body ??
                r.commentaire ??
                r.prochaine_etape ??
                r.texte ??
                r.content;
            return typeof body === 'string' ? body.trim() : '';
        })
        .filter((s) => s.length > 0);
}

export function statusForSticky(
    rawName: string | undefined,
): 'bon' | 'vigilance' | 'critique' | 'cloture' | 'archivee' {
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

export function linesForFait(f: FaitMarquantView): { faitsLines: string[]; commentairesLines: string[] } {
    const rawEtapes =
        f.prochaines_etapes ??
        (f as unknown as Record<string, unknown>).fait_marquant_prochaine_etapes ??
        (f as unknown as Record<string, unknown>).fait_marquant_prochaine_etape ??
        [];
    const rawCommentaires =
        f.commentaires ??
        (f as unknown as Record<string, unknown>).fait_marquant_commentaires ??
        (f as unknown as Record<string, unknown>).fait_marquant_commentaire ??
        [];
    return {
        faitsLines: rowsToBodies(rawEtapes),
        commentairesLines: rowsToBodies(rawCommentaires),
    };
}

/** Données alignées sur le serveur uniquement (ne pas y mettre d’état UI pur : fusionné dans le nœud existant). */
export function stickyDataPayload(f: FaitMarquantView) {
    const { faitsLines, commentairesLines } = linesForFait(f);
    return {
        projectId: f.id,
        projectName: f.title,
        projectStatus: statusForSticky(f.fait_status?.name),
        fait_status_id: f.fait_status_id,
        deadline: f.deadline,
        faitsMarquants: faitsLines,
        commentaires: commentairesLines,
    };
}

export function makeNodeForFait(
    f: FaitMarquantView,
    position: { x: number; y: number },
    width?: number,
    height?: number,
): Node {
    const { faitsLines, commentairesLines } = linesForFait(f);
    const dims = stickyDimensionsFromLists(faitsLines, commentairesLines);
    return {
        id: `fait-${f.id}`,
        type: 'sticky',
        position,
        width: width && width >= 200 ? width : dims.width,
        height: height && height >= 168 ? height : dims.height,
        data: {
            ...stickyDataPayload(f),
            stickyView: 'faits' as const,
            userSizedNote: false,
        },
    };
}

export function loadStickyLayouts(): Record<
    string,
    { x: number; y: number; width?: number; height?: number }
> {
    if (typeof localStorage === 'undefined') return {};
    try {
        const raw = localStorage.getItem(WHITEBOARD_STICKY_LAYOUT_KEY);
        if (!raw) return {};
        const o = JSON.parse(raw) as unknown;
        return o && typeof o === 'object'
            ? (o as Record<string, { x: number; y: number; width?: number; height?: number }>)
            : {};
    } catch {
        return {};
    }
}

export function flushStickyLayouts(
    map: Record<string, { x: number; y: number; width?: number; height?: number }>,
): void {
    if (typeof localStorage === 'undefined') return;
    try {
        localStorage.setItem(WHITEBOARD_STICKY_LAYOUT_KEY, JSON.stringify(map));
    } catch {
        /* ignore */
    }
}
