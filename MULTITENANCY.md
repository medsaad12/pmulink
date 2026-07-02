# Multi-Tenancy Update — Key Differences

## Concept

- Before: single company, one shared dataset.
- After: multiple **organizations** (tenants) in the **same database**, fully isolated.
- One deployment, one domain, one DB — no separate instances.

## How separation is managed

- Every tenant-owned table has an `organization_id` column.
- A global Eloquent scope auto-filters **every query** by the current organization.
- New rows are auto-stamped with the current `organization_id`.
- The current organization is resolved per request (from session / membership).
- `is_sup` (support/super-admin) bypasses the scope and can access all orgs.

## Identity & roles (before → after)

- Before: `users.role_id` → one global role per user.
- After: `organization_user` pivot → one **membership per (user, org)** with its own role.
- Same email/identity can belong to several orgs, with a different role in each.
- Roles are now **per-organization** (not shared).
- `is_sup` stays global (DB-only flag, the app support/super-admin).

## Access tiers

- `is_sup` → global support; backoffice, dashboard, all orgs.
- Org admin → admin **inside one org** via an RBAC role (e.g. the director).
- Normal / global user → scoped to one org.

## New capabilities

- Org **picker** at login for multi-org users.
- Org **switcher** in the sidebar.
- `is_sup` **backoffice** (`/admin/organizations`): create orgs, attach/detach members.

## Affected tables & columns

### New tables

- `organizations` → `id`, `name`, `slug`, `is_active`, timestamps, soft deletes.
- `organization_user` → `id`, `organization_id`, `user_id`, `role_id`, timestamps.

### Column added: `organization_id`

- `roles` (+ unique `organization_id` + `name`)
- `departments`
- `faits_marquants`
- `fait_marquant_drafts`
- `fait_marquant_prochaine_etape`
- `fait_marquant_commentaire`
- `fait_marquant_histories`
- `fait_marquant_draft_prochaine_etape`
- `fait_marquant_draft_commentaire`

### Column removed

- `users.role_id` → moved to `organization_user.role_id`.

### Unchanged (stay global)

- `fait_statuses`, `statuses`, `etape_statuses`
- `permissions`
- `users` (identity only; `is_sup` kept)

## Data migration

- Existing data backfilled into default Org #1.
- Each existing user got a membership in Org #1 with their previous role.
