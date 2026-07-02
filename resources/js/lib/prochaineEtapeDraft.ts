import type {
    EtapeStatusOption,
    FaitMarquantPivotRow,
    ProchaineEtapeInput,
} from '@/types/faitMarquant';

export function defaultProchaineEtapeInput(
    responsableActionId: number,
    defaultEtapeStatusId: number,
): ProchaineEtapeInput {
    return {
        body: '',
        responsable_action_id: responsableActionId,
        deadline: null,
        etape_status_id: defaultEtapeStatusId,
    };
}

export function prochaineEtapeFromPivotRow(
    row: FaitMarquantPivotRow,
    fallbackResponsableId: number,
    defaultEtapeStatusId: number,
): ProchaineEtapeInput {
    return {
        body: row.body,
        responsable_action_id: row.responsable_action_id ?? fallbackResponsableId,
        deadline: row.deadline ? row.deadline.slice(0, 10) : null,
        etape_status_id: row.etape_status_id ?? defaultEtapeStatusId,
    };
}

export function prochaineEtapesFromSaved(
    rows: FaitMarquantPivotRow[] | undefined,
    fallbackResponsableId: number,
    defaultEtapeStatusId: number,
): ProchaineEtapeInput[] {
    const mapped = (rows ?? [])
        .map((row) => prochaineEtapeFromPivotRow(row, fallbackResponsableId, defaultEtapeStatusId))
        .filter((row) => row.body.trim() !== '');

    if (mapped.length === 0) {
        return [defaultProchaineEtapeInput(fallbackResponsableId, defaultEtapeStatusId)];
    }

    return mapped;
}

export function prochaineEtapesForSubmit(etapes: ProchaineEtapeInput[]): ProchaineEtapeInput[] {
    return etapes
        .map((row) => ({
            ...row,
            body: row.body.trim(),
            deadline: row.deadline === '' ? null : row.deadline,
        }))
        .filter((row) => row.body !== '');
}

export function prochaineEtapeDisplaySummary(
    etape: ProchaineEtapeInput,
    responsibles: { id: number; name: string }[],
    etapeStatuses: EtapeStatusOption[],
): string {
    const parts = [etape.body.trim()];

    const responsable = responsibles.find((u) => u.id === etape.responsable_action_id);
    if (responsable) {
        parts.push(`@${responsable.name}`);
    }

    if (etape.deadline) {
        parts.push(etape.deadline.slice(0, 10));
    }

    const status = etapeStatuses.find((s) => s.id === etape.etape_status_id);
    if (status) {
        parts.push(status.name);
    }

    return parts.filter((p) => p !== '').join(' · ');
}
