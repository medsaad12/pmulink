export type FaitStatusOption = { id: number; name: string; color?: string };
export type EtapeStatusOption = { id: number; name: string; color?: string };
export type WorkflowStatusOption = { id: number; name: string; color?: string };

export type FaitMarquantPivotUser = { id: number; name: string };

export type ProchaineEtapeInput = {
    body: string;
    responsable_action_id: number;
    deadline: string | null;
    etape_status_id: number;
};

export type FaitMarquantPivotRow = {
    id: number;
    sort_order?: number;
    user_id?: number | null;
    body: string;
    responsable_action_id?: number | null;
    deadline?: string | null;
    etape_status_id?: number | null;
    created_at?: string | null;
    user?: FaitMarquantPivotUser | null;
    responsable_action?: FaitMarquantPivotUser | null;
    etape_status?: EtapeStatusOption | null;
};

export type DepartmentOption = { id: number; name: string };

export type ActionResponsibleOption = {
    id: number;
    name: string;
};

/** Ligne pivot dans la chronologie hebdomadaire (prochaines étapes / commentaires). */
export type FaitMarquantWeeklyPivotRow = {
    id: number;
    sequence_number?: number | null;
    body: string;
    responsable_action_id?: number | null;
    deadline?: string | null;
    etape_status_id?: number | null;
    created_at?: string | null;
    user?: FaitMarquantPivotUser | null;
    responsable_action?: FaitMarquantPivotUser | null;
    etape_status?: EtapeStatusOption | null;
};

export type FaitMarquantWeeklySnapshot = {
    created_at?: string | null;
    title: string;
    fait_status_id: number;
    status_id: number;
    deadline: string | null;
    department_id: number;
    responsable_action_id: number;
    responsable_action?: FaitMarquantPivotUser | null;
    fait_status?: FaitStatusOption | null;
    workflow_status?: WorkflowStatusOption | null;
    department?: DepartmentOption | null;
    changed_by?: FaitMarquantPivotUser | null;
};

export type FaitMarquantWeeklyTimelineWeek = {
    week_start: string;
    week_end: string;
    week_label: string;
    snapshot: FaitMarquantWeeklySnapshot | null;
    prochaines_etapes: FaitMarquantWeeklyPivotRow[];
    commentaires: FaitMarquantWeeklyPivotRow[];
};

export type FaitMarquantHistoryEntry = {
    created_at?: string | null;
    title: string;
    fait_status_id: number;
    status_id: number;
    deadline: string | null;
    department_id: number;
    responsable_action_id: number;
    responsable_action?: FaitMarquantPivotUser | null;
    fait_status?: FaitStatusOption | null;
    workflow_status?: WorkflowStatusOption | null;
    department?: DepartmentOption | null;
    changed_by?: FaitMarquantPivotUser | null;
};

export type FaitMarquantView = {
    id: number;
    title: string;
    fait_status_id: number;
    status_id: number;
    deadline: string | null;
    department_id: number;
    created_by: number;
    responsable_action_id: number;
    /** Responsable de l'action (utilisateur du département). */
    responsable_action?: FaitMarquantPivotUser | null;
    /** Auteur du fait (table `users`), si chargé côté API. */
    creator?: FaitMarquantPivotUser | null;
    created_at?: string;
    updated_at?: string;
    submitted_at?: string | null;
    has_unsubmitted_draft?: boolean;
    department?: DepartmentOption | null;
    fait_status?: FaitStatusOption | null;
    workflow_status?: WorkflowStatusOption | null;
    prochaines_etapes?: FaitMarquantPivotRow[];
    commentaires?: FaitMarquantPivotRow[];
    /** Historique des états publiés (snapshot complet à chaque changement). */
    fait_marquant_history?: FaitMarquantHistoryEntry[];
};
