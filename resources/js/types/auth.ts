export type DepartmentSummary = {
    id: number;
    name: string;
    organization_id?: number;
};

export type UserMembership = {
    organization_id: number;
    organization_name?: string;
    role_id: number | null;
    role_name?: string | null;
};

export type User = {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    role_id?: number;
    memberships?: UserMembership[];
    departments?: DepartmentSummary[];
    organizations?: OrganizationSummary[];
    role?: { id: number; name: string } | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User | null;
    /** Dashboard access; set only in the database (is_sup). */
    is_sup: boolean;
    permission_keys: string[];
};

export type OrganizationSummary = {
    id: number;
    name: string;
};

export type Tenant = {
    current_id: number | null;
    current: OrganizationSummary | null;
    available: OrganizationSummary[];
};
