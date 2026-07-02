<script setup>
import { computed, ref, watch, onUnmounted, nextTick } from 'vue'
import { ChevronDown, LayoutGrid, Table2 } from 'lucide-vue-next'
import {
  DEPARTMENTS,
  PROJECT_ITEMS,
  normalizeProjectStatus,
  stickyNotePaperColorFromStatus,
} from '../constants/projects'

const emit = defineEmits(['set-view'])
const departmentFilterId = ref(null)

/** Même liste que le post-it (StickyNoteNode). */
const STATUS_OPTIONS = [
  { value: 'bon', label: 'En bonne voie' },
  { value: 'vigilance', label: 'Sous vigilance' },
  { value: 'critique', label: 'Critique / À risque' },
  { value: 'cloture', label: 'Clôturé' },
  { value: 'archivee', label: 'Archivé' },
]

const statusDropdownOpenRowId = ref(null)
const statusDropdownRoots = new Map()

function setStatusDropdownRoot(rowId, el) {
  if (el) statusDropdownRoots.set(rowId, el)
  else statusDropdownRoots.delete(rowId)
}

function statusLabelForRow(row) {
  const s = normalizeProjectStatus(row.status)
  return STATUS_OPTIONS.find((o) => o.value === s)?.label ?? 'Sous vigilance'
}

function toggleStatusDropdown(rowId) {
  statusDropdownOpenRowId.value =
    statusDropdownOpenRowId.value === rowId ? null : rowId
}

function closeStatusDropdown() {
  statusDropdownOpenRowId.value = null
}

function onGlobalPointerDownClose(ev) {
  const id = statusDropdownOpenRowId.value
  if (id == null) return
  const root = statusDropdownRoots.get(id)
  if (!root || !root.contains(ev.target)) closeStatusDropdown()
}

function onGlobalKeydownEscape(ev) {
  if (ev.key === 'Escape') closeStatusDropdown()
}

function bindStatusDropdownDismiss() {
  document.addEventListener('pointerdown', onGlobalPointerDownClose, true)
  document.addEventListener('keydown', onGlobalKeydownEscape, true)
}

function unbindStatusDropdownDismiss() {
  document.removeEventListener('pointerdown', onGlobalPointerDownClose, true)
  document.removeEventListener('keydown', onGlobalKeydownEscape, true)
}

watch(statusDropdownOpenRowId, (open) => {
  unbindStatusDropdownDismiss()
  if (open != null) bindStatusDropdownDismiss()
})

onUnmounted(() => {
  if (statusDropdownOpenRowId.value != null) unbindStatusDropdownDismiss()
  statusDropdownRoots.clear()
})

const anyStatusMenuOpen = computed(() => statusDropdownOpenRowId.value != null)

const STATUS_OVERRIDES_LS_KEY = 'sticky-general-status-overrides-v1'

function loadStatusOverrides() {
  try {
    const raw = localStorage.getItem(STATUS_OVERRIDES_LS_KEY)
    if (!raw) return {}
    const o = JSON.parse(raw)
    if (!o || typeof o !== 'object') return {}
    const next = {}
    for (const [k, v] of Object.entries(o)) {
      if (v && typeof v === 'object' && typeof v.status === 'string') {
        next[k] = {
          status: normalizeProjectStatus(v.status),
          lastChangeAt:
            typeof v.lastChangeAt === 'string' ? v.lastChangeAt : undefined,
        }
      }
    }
    return next
  } catch {
    return {}
  }
}

function persistStatusOverrides(map) {
  try {
    localStorage.setItem(STATUS_OVERRIDES_LS_KEY, JSON.stringify(map))
  } catch {
    /* ignore */
  }
}

const statusOverrides = ref(loadStatusOverrides())

const MIN_ROWS_PER_DEPARTMENT = 10

function toIsoDate(date) {
  return date.toISOString().slice(0, 10)
}

function formatDate(isoDate) {
  if (!isoDate) return '—'
  return new Date(isoDate).toLocaleDateString('fr-FR')
}

function parseIsoDateLocal(iso) {
  if (!iso || typeof iso !== 'string') return null
  const ymd = iso.slice(0, 10)
  const parts = ymd.split('-')
  if (parts.length !== 3) return null
  const y = Number(parts[0])
  const m = Number(parts[1])
  const d = Number(parts[2])
  if (!Number.isFinite(y) || !Number.isFinite(m) || !Number.isFinite(d)) return null
  const dt = new Date(y, m - 1, d)
  return Number.isFinite(dt.getTime()) ? dt : null
}

/**
 * Date de fin pour la duree : cloture si presente (derniere date metier),
 * sinon dernier changement. Si les deux existent, on prend la plus recente.
 */
function endDateIsoForDuration(row) {
  const closed = row.closedAt
  const last = row.lastChangeAt
  if (closed && last) {
    const dc = parseIsoDateLocal(closed)
    const dl = parseIsoDateLocal(last)
    if (dc && dl) return dc.getTime() >= dl.getTime() ? closed : last
  }
  return closed || last
}

/** Nombre de jours calendaires entre creation et fin (fin = cloture ou dernier changement). */
function durationDaysSinceCreation(row) {
  const start = parseIsoDateLocal(row.createdAt)
  const endIso = endDateIsoForDuration(row)
  const end = parseIsoDateLocal(endIso)
  if (!start || !end) return null
  const ms = end.getTime() - start.getTime()
  if (ms < 0) return 0
  return Math.round(ms / 86400000)
}

function formatDurationDays(row) {
  const n = durationDaysSinceCreation(row)
  if (n == null) return '—'
  if (n === 0) return '0 j'
  if (n === 1) return '1 jour'
  return `${n} jours`
}

function getVirtualStatus(baseStatus, rowIndex) {
  const normalized = normalizeProjectStatus(baseStatus)
  if (normalized === 'cloture' || normalized === 'archivee') return 'cloture'
  if (rowIndex % 9 === 0) return 'cloture'
  if (rowIndex % 4 === 0) return 'vigilance'
  if (rowIndex % 7 === 0) return 'critique'
  return normalized
}

function buildRows() {
  const now = new Date()
  const rowsByDepartment = new Map(DEPARTMENTS.map((d) => [d.id, []]))
  let rowIndex = 0

  for (const project of PROJECT_ITEMS) {
    const deptId = project.departmentId || 'dnt'
    if (!rowsByDepartment.has(deptId)) rowsByDepartment.set(deptId, [])
    const titles =
      Array.isArray(project.faitsMarquants) && project.faitsMarquants.length > 0
        ? project.faitsMarquants
        : [project.name]

    for (const title of titles) {
      const createdAt = new Date(now)
      createdAt.setDate(now.getDate() - (12 + rowIndex * 2))

      const lastChangeAt = new Date(createdAt)
      lastChangeAt.setDate(createdAt.getDate() + (1 + (rowIndex % 7)))

      const status = getVirtualStatus(project.projectStatus, rowIndex)

      let closedAt = null
      if (status === 'cloture') {
        closedAt = new Date(lastChangeAt)
        closedAt.setDate(lastChangeAt.getDate() + 1 + (rowIndex % 4))
      }

      rowsByDepartment.get(deptId).push({
        id: `${deptId}-${rowIndex}`,
        departmentId: deptId,
        title: String(title ?? '').trim() || project.name,
        status,
        createdAt: toIsoDate(createdAt),
        closedAt: closedAt ? toIsoDate(closedAt) : null,
        lastChangeAt: toIsoDate(lastChangeAt),
        prochainesEtapes: project.faitsMarquants ?? [],
        pointsVigilance: project.commentaires ?? [],
      })

      rowIndex += 1
    }
  }

  for (const dept of DEPARTMENTS) {
    const deptRows = rowsByDepartment.get(dept.id) ?? []
    if (deptRows.length >= MIN_ROWS_PER_DEPARTMENT) continue
    const sourceRows = deptRows.length > 0 ? deptRows : []
    if (sourceRows.length === 0) continue

    while (deptRows.length < MIN_ROWS_PER_DEPARTMENT) {
      const source = sourceRows[deptRows.length % sourceRows.length]
      const createdAt = new Date(source.createdAt)
      createdAt.setDate(createdAt.getDate() + 1)
      const lastChangeAt = new Date(source.lastChangeAt)
      lastChangeAt.setDate(lastChangeAt.getDate() + 1)

      deptRows.push({
        ...source,
        id: `${dept.id}-extra-${deptRows.length}`,
        title: `${source.title} (suivi)`,
        createdAt: toIsoDate(createdAt),
        lastChangeAt: toIsoDate(lastChangeAt),
      })
    }
  }

  return Array.from(rowsByDepartment.values()).flat()
}

function mergeStatusOverride(row) {
  const o = statusOverrides.value[row.id]
  if (!o) return row
  const status = normalizeProjectStatus(o.status)
  let closedAt = row.closedAt
  if (status === 'cloture') {
    closedAt = closedAt || o.lastChangeAt || toIsoDate(new Date())
  } else {
    closedAt = null
  }
  return {
    ...row,
    status,
    closedAt,
    lastChangeAt: o.lastChangeAt ?? row.lastChangeAt,
  }
}

const rows = computed(() => buildRows().map(mergeStatusOverride))

function setRowStatus(rowId, rawStatus) {
  const status = normalizeProjectStatus(rawStatus)
  const lastChangeAt = toIsoDate(new Date())
  const next = { ...statusOverrides.value, [rowId]: { status, lastChangeAt } }
  statusOverrides.value = next
  persistStatusOverrides(next)
  closeStatusDropdown()
}

function pickRowStatus(rowId, value) {
  setRowStatus(rowId, value)
}

/** Plus recent en premier (date de creation), puis titre. */
function sortRowsByCreatedAtDesc(deptRows) {
  return [...deptRows].sort((a, b) => {
    const ta = new Date(a.createdAt).getTime()
    const tb = new Date(b.createdAt).getTime()
    if (Number.isFinite(tb) && Number.isFinite(ta) && tb !== ta) return tb - ta
    if (!Number.isFinite(tb) && Number.isFinite(ta)) return -1
    if (Number.isFinite(tb) && !Number.isFinite(ta)) return 1
    return String(a.title).localeCompare(String(b.title), 'fr', { sensitivity: 'base' })
  })
}

const departmentGroups = computed(() => {
  const visibleDeptIds =
    departmentFilterId.value == null
      ? DEPARTMENTS.map((d) => d.id)
      : [departmentFilterId.value]

  return DEPARTMENTS.filter((d) => visibleDeptIds.includes(d.id))
    .map((dept) => {
      const deptRows = rows.value.filter((r) => r.departmentId === dept.id)
      return {
        id: dept.id,
        label: dept.label,
        sortedRows: sortRowsByCreatedAtDesc(deptRows),
        totalCount: deptRows.length,
      }
    })
    .filter((g) => g.totalCount > 0)
})

const detailsPanelOpen = ref(false)
const detailsOpenForRowId = ref(null)
/** 'etapes' | 'vigilance' — même idée que le post-it (faits / commentaires). */
const detailsViewMode = ref('etapes')
const detailsTitleId = 'general-details-panel-title'
const detailsCloseBtnRef = ref(null)

/** Ligne courante depuis `rows` pour garder le statut a jour. */
const detailsRowLive = computed(() => {
  const id = detailsOpenForRowId.value
  if (!id) return null
  return rows.value.find((r) => r.id === id) ?? null
})

function listLinesFromField(arr) {
  if (!Array.isArray(arr)) return []
  return arr.map((s) => String(s ?? '').trim()).filter(Boolean)
}

const detailsEtapesLines = computed(() => {
  const row = detailsRowLive.value
  return listLinesFromField(row?.prochainesEtapes)
})
const detailsVigilanceLines = computed(() => {
  const row = detailsRowLive.value
  return listLinesFromField(row?.pointsVigilance)
})

const detailsDeptLabel = computed(() => {
  const row = detailsRowLive.value
  if (!row?.departmentId) return ''
  return DEPARTMENTS.find((d) => d.id === row.departmentId)?.label ?? row.departmentId
})

function openDetailsPanel(row) {
  closeStatusDropdown()
  detailsOpenForRowId.value = row.id
  detailsViewMode.value = 'etapes'
  detailsPanelOpen.value = true
  nextTick(() => detailsCloseBtnRef.value?.focus?.())
}

function closeDetailsPanel() {
  detailsPanelOpen.value = false
  detailsOpenForRowId.value = null
}

function setDetailsViewMode(mode) {
  detailsViewMode.value = mode === 'vigilance' ? 'vigilance' : 'etapes'
}

watch(detailsPanelOpen, (open, _prev, onCleanup) => {
  document.body.style.overflow = open ? 'hidden' : ''
  if (!open) return
  const onKey = (e) => {
    if (e.key === 'Escape') closeDetailsPanel()
  }
  document.addEventListener('keydown', onKey)
  onCleanup(() => {
    document.removeEventListener('keydown', onKey)
    document.body.style.overflow = ''
  })
})
</script>

<template>
  <main class="general-view">
    <nav
      class="general-view__dept-filter"
      aria-label="Filtrer les faits marquants par departement"
    >
      <div class="general-view__dept-filter-stack">
        <div class="general-view__dept-filter-inner">
          <div
            class="general-view__view-switch"
            role="group"
            aria-label="Choisir l'affichage : cartes ou tableau"
          >
            <button
              type="button"
              class="general-view__view-switch-btn"
              aria-pressed="false"
              aria-label="Vue cartes — tableau de post-its"
              title="Vue cartes"
              @click="emit('set-view', 'board')"
            >
              <LayoutGrid :size="18" :stroke-width="2" aria-hidden="true" />
            </button>
            <button
              type="button"
              class="general-view__view-switch-btn general-view__view-switch-btn--active"
              aria-pressed="true"
              aria-label="Vue tableau — faits marquants"
              title="Vue tableau"
            >
              <Table2 :size="18" :stroke-width="2" aria-hidden="true" />
            </button>
          </div>
          <span class="general-view__dept-filter-sep" aria-hidden="true" />
          <button
            type="button"
            class="general-view__dept-filter-btn"
            :class="{ 'general-view__dept-filter-btn--active': departmentFilterId === null }"
            @click="departmentFilterId = null"
          >
            Tous
          </button>
          <button
            v-for="d in DEPARTMENTS"
            :key="'general-filter-' + d.id"
            type="button"
            class="general-view__dept-filter-btn"
            :class="{ 'general-view__dept-filter-btn--active': departmentFilterId === d.id }"
            @click="departmentFilterId = d.id"
          >
            {{ d.label }}
          </button>
        </div>
      </div>
    </nav>

    <div class="general-view__brand">
      <img
        src="/logopmo.png"
        alt="Sticky"
        class="general-view__logo"
        width="380"
        height="84"
        decoding="async"
      />
    </div>

    <header class="general-view__header">
      <div>
        <h1 class="general-view__title">Vue generale des faits marquants</h1>
        <p class="general-view__subtitle">
          Par departement, lignes triees par date de creation (plus recent en premier).
        </p>
      </div>
    </header>

    <section
      v-for="dept in departmentGroups"
      :key="dept.id"
      class="general-view__dept"
    >
      <h2 class="general-view__dept-title">
        {{ dept.label }}
        <span class="general-view__group-count">({{ dept.totalCount }})</span>
      </h2>

      <div
        class="general-view__table-wrap"
        :class="{ 'general-view__table-wrap--menu-open': anyStatusMenuOpen }"
      >
        <table class="general-view__table">
          <thead>
            <tr>
              <th>Fait marquant (titre)</th>
              <th>Statut</th>
              <th>Date creation</th>
              <th>Date cloture</th>
              <th>Dernier changement</th>
              <th
                class="general-view__th-duration"
                title="Jours entre la date de creation et la date de cloture (si cloture) ou le dernier changement — la plus recente des deux si les deux sont renseignees."
              >
                Duree (jours)
              </th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="row in dept.sortedRows"
              :key="row.id"
            >
              <td class="general-view__title-cell">{{ row.title }}</td>
              <td class="general-view__status-cell">
                <div
                  :ref="(el) => setStatusDropdownRoot(row.id, el)"
                  class="general-view__status-dropdown"
                  :class="{
                    'general-view__status-dropdown--open':
                      statusDropdownOpenRowId === row.id,
                  }"
                >
                  <button
                    type="button"
                    class="general-view__status-trigger"
                    :class="`general-view__status-trigger--${normalizeProjectStatus(row.status)}`"
                    :id="`general-status-trigger-${row.id}`"
                    aria-haspopup="menu"
                    :aria-expanded="statusDropdownOpenRowId === row.id"
                    :aria-controls="`general-status-menu-${row.id}`"
                    @click.stop="toggleStatusDropdown(row.id)"
                  >
                    <span class="general-view__status-trigger-label">{{
                      statusLabelForRow(row)
                    }}</span>
                    <ChevronDown
                      class="general-view__status-chevron"
                      :size="14"
                      :stroke-width="2.5"
                      aria-hidden="true"
                    />
                  </button>
                  <ul
                    v-show="statusDropdownOpenRowId === row.id"
                    :id="`general-status-menu-${row.id}`"
                    class="general-view__status-menu"
                    role="menu"
                    :aria-labelledby="`general-status-trigger-${row.id}`"
                  >
                    <li v-for="opt in STATUS_OPTIONS" :key="opt.value" role="none">
                      <button
                        type="button"
                        class="general-view__status-menu-item"
                        :class="`general-view__status-menu-item--${opt.value}`"
                        role="menuitem"
                        @click="pickRowStatus(row.id, opt.value)"
                      >
                        <span class="general-view__status-menu-item-label">{{
                          opt.label
                        }}</span>
                        <span
                          v-if="normalizeProjectStatus(row.status) === opt.value"
                          class="general-view__status-menu-check"
                          aria-hidden="true"
                        >✓</span>
                      </button>
                    </li>
                  </ul>
                </div>
              </td>
              <td>{{ formatDate(row.createdAt) }}</td>
              <td>{{ formatDate(row.closedAt) }}</td>
              <td>{{ formatDate(row.lastChangeAt) }}</td>
              <td class="general-view__duration-cell">{{ formatDurationDays(row) }}</td>
              <td>
                <button
                  type="button"
                  class="general-view__details-btn"
                  @click="openDetailsPanel(row)"
                >
                  Details
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <Teleport to="body">
      <div
        v-if="detailsPanelOpen"
        class="general-details"
        role="presentation"
      >
        <div
          class="general-details__backdrop"
          aria-hidden="true"
          @click="closeDetailsPanel"
        />
        <aside
          class="general-details__panel"
          role="dialog"
          aria-modal="true"
          :aria-labelledby="detailsTitleId"
        >
          <header class="general-details__head">
            <div class="general-details__head-text">
              <p v-if="detailsDeptLabel" class="general-details__dept">
                {{ detailsDeptLabel }}
              </p>
              <h2 :id="detailsTitleId" class="general-details__title">
                {{ detailsRowLive?.title }}
              </h2>
            </div>
            <button
              ref="detailsCloseBtnRef"
              type="button"
              class="general-details__close"
              aria-label="Fermer"
              @click="closeDetailsPanel"
            >
              ×
            </button>
          </header>

          <div v-if="detailsRowLive" class="general-details__meta">
            <div class="general-details__meta-block">
              <span class="general-details__meta-label">Statut</span>
              <span
                class="general-details__status-pill"
                :class="`general-details__status-pill--${normalizeProjectStatus(detailsRowLive.status)}`"
              >
                {{ statusLabelForRow(detailsRowLive) }}
              </span>
            </div>
          </div>

          <div
            class="general-details__switch"
            role="tablist"
            aria-label="Contenu du fait marquant"
          >
            <button
              type="button"
              class="general-details__switch-btn"
              role="tab"
              :aria-selected="detailsViewMode === 'etapes'"
              @click="setDetailsViewMode('etapes')"
            >
              Prochaines étapes
            </button>
            <button
              type="button"
              class="general-details__switch-btn"
              role="tab"
              :aria-selected="detailsViewMode === 'vigilance'"
              @click="setDetailsViewMode('vigilance')"
            >
              Points de vigilance
            </button>
          </div>

          <div class="general-details__body">
            <template v-if="detailsViewMode === 'etapes'">
              <ol
                v-if="detailsEtapesLines.length"
                class="general-details__list"
                aria-label="Prochaines étapes"
              >
                <li
                  v-for="(line, i) in detailsEtapesLines"
                  :key="'det-e-' + i"
                  class="general-details__list-item"
                >
                  {{ line }}
                </li>
              </ol>
              <p v-else class="general-details__empty">Aucune entrée.</p>
            </template>
            <template v-else>
              <ol
                v-if="detailsVigilanceLines.length"
                class="general-details__list"
                aria-label="Points de vigilance"
              >
                <li
                  v-for="(line, i) in detailsVigilanceLines"
                  :key="'det-v-' + i"
                  class="general-details__list-item"
                >
                  {{ line }}
                </li>
              </ol>
              <p v-else class="general-details__empty">Aucune entrée.</p>
            </template>
          </div>
        </aside>
      </div>
    </Teleport>
  </main>
</template>

<style scoped>
.general-view {
  min-height: 100vh;
  min-height: 100dvh;
  padding: 112px 24px 24px;
  background: #f4f5f8;
  color: #0f172a;
}

.general-view__dept-filter {
  position: fixed;
  top: 16px;
  left: 0;
  right: 0;
  z-index: 20;
  display: flex;
  justify-content: center;
  padding: 0 16px;
  box-sizing: border-box;
  pointer-events: none;
}

.general-view__dept-filter-stack {
  width: 100%;
  max-width: min(920px, calc(100vw - 32px));
  pointer-events: auto;
}

.general-view__dept-filter-inner {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  align-items: center;
  gap: 8px;
}

.general-view__dept-filter-sep {
  width: 1px;
  height: 22px;
  margin: 0 2px 0 4px;
  background: #e2e8f0;
  flex-shrink: 0;
}

.general-view__view-switch {
  display: inline-flex;
  flex-shrink: 0;
  gap: 2px;
  padding: 3px;
  border-radius: 999px;
  border: 1px solid #e2e8f0;
  background: rgb(255 255 255 / 92%);
  box-shadow: 0 1px 3px rgb(15 23 42 / 6%);
}

.general-view__view-switch-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 38px;
  height: 32px;
  margin: 0;
  padding: 0;
  border: none;
  border-radius: 999px;
  background: transparent;
  color: #64748b;
  cursor: pointer;
  transition:
    background 0.15s ease,
    color 0.15s ease,
    box-shadow 0.15s ease,
    transform 0.1s ease;
}

.general-view__view-switch-btn:hover:not(.general-view__view-switch-btn--active) {
  color: #4338ca;
  background: #f1f5f9;
}

.general-view__view-switch-btn:active:not(.general-view__view-switch-btn--active) {
  transform: scale(0.96);
}

.general-view__view-switch-btn:focus {
  outline: none;
}

.general-view__view-switch-btn:focus-visible {
  outline: 2px solid #a5b4fc;
  outline-offset: 1px;
}

.general-view__view-switch-btn--active {
  background: linear-gradient(165deg, rgb(129 140 248), rgb(79 70 229));
  color: #ffffff;
  box-shadow:
    0 0 0 1px rgb(255 255 255 / 22%) inset,
    0 2px 8px rgb(79 70 229 / 35%);
  cursor: default;
  pointer-events: none;
}

.general-view__dept-filter-btn {
  font-family: inherit;
  font-size: 0.76rem;
  font-weight: 600;
  letter-spacing: 0.02em;
  padding: 8px 14px;
  border-radius: 999px;
  border: 1px solid #e2e8f0;
  background: rgb(255 255 255 / 92%);
  color: #475569;
  cursor: pointer;
  box-shadow: 0 1px 3px rgb(15 23 42 / 6%);
}

.general-view__dept-filter-btn:hover {
  border-color: #c7d2fe;
  color: #4338ca;
  background: #f1f5f9;
}

.general-view__dept-filter-btn--active {
  border-color: #818cf8;
  background: linear-gradient(165deg, rgb(129 140 248), rgb(79 70 229));
  color: #ffffff;
}

.general-view__brand {
  position: fixed;
  top: 16px;
  left: 18px;
  z-index: 21;
  pointer-events: none;
}

.general-view__logo {
  display: block;
  height: 62px;
  width: auto;
  max-width: min(300px, 45vw);
  object-fit: contain;
  object-position: left center;
}

.general-view__header {
  margin-bottom: 20px;
}

.general-view__title {
  margin: 0;
  font-size: 1.4rem;
  font-weight: 700;
}

.general-view__subtitle {
  margin: 6px 0 0;
  color: #64748b;
  font-size: 0.9rem;
}

.general-view__dept {
  margin-bottom: 28px;
}

.general-view__dept-title {
  margin: 0 0 14px;
  font-size: 1.1rem;
  font-weight: 800;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: #1e293b;
}

.general-view__group-count {
  color: #64748b;
  font-weight: 600;
}

.general-view__table-wrap {
  overflow-x: auto;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  background: #fff;
}

.general-view__table-wrap--menu-open {
  overflow: visible;
  z-index: 5;
}

.general-view__table {
  width: 100%;
  border-collapse: collapse;
  min-width: 1280px;
}

.general-view__table th,
.general-view__table td {
  padding: 10px 12px;
  border-bottom: 1px solid #f1f5f9;
  text-align: left;
  vertical-align: top;
  font-size: 0.84rem;
}

.general-view__table th {
  background: #f8fafc;
  color: #475569;
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  font-weight: 700;
}

.general-view__th-duration {
  max-width: 8.5rem;
  line-height: 1.25;
  cursor: help;
}

.general-view__duration-cell {
  text-align: center;
  font-weight: 600;
  font-variant-numeric: tabular-nums;
  color: #334155;
  white-space: nowrap;
}

.general-view__title-cell {
  min-width: 290px;
  font-weight: 600;
}

.general-view__status-cell {
  min-width: 200px;
  position: relative;
  overflow: visible;
  vertical-align: middle;
}

/* Meme pattern que StickyNoteNode (declencheur pill + menu), tailles adaptees au tableau. */
.general-view__status-dropdown {
  position: relative;
  z-index: 4;
  width: max-content;
  max-width: 100%;
}

.general-view__status-dropdown--open {
  z-index: 50;
}

.general-view__status-trigger {
  display: inline-flex;
  align-items: center;
  justify-content: space-between;
  gap: 6px;
  min-width: 10.5rem;
  max-width: 15rem;
  width: 100%;
  cursor: pointer;
  font-family: inherit;
  font-size: 0.72rem;
  font-weight: 800;
  letter-spacing: 0.02em;
  line-height: 1.2;
  padding: 6px 10px 6px 12px;
  border-radius: 999px;
  border: 1px solid transparent;
  background-clip: padding-box;
  box-shadow: 0 1px 3px rgb(15 23 42 / 8%);
  transition:
    background 0.14s ease,
    border-color 0.14s ease,
    color 0.14s ease,
    box-shadow 0.14s ease,
    transform 0.12s ease;
}

.general-view__status-trigger-label {
  min-width: 0;
  text-align: left;
  white-space: normal;
  line-height: 1.25;
}

.general-view__status-chevron {
  flex-shrink: 0;
  opacity: 0.88;
  transition: transform 0.2s ease;
}

.general-view__status-dropdown--open .general-view__status-chevron {
  transform: rotate(180deg);
}

.general-view__status-trigger:hover {
  transform: translateY(-0.5px);
}

.general-view__status-trigger:active {
  transform: translateY(0);
}

.general-view__status-trigger:focus {
  outline: none;
}

.general-view__status-trigger:focus-visible {
  outline: 2px solid #a5b4fc;
  outline-offset: 2px;
}

.general-view__status-trigger--bon {
  background: linear-gradient(165deg, rgb(187 247 208), rgb(110 231 183));
  border-color: rgb(74 222 128 / 60%);
  color: rgb(6 78 59);
  box-shadow:
    0 0 0 1px rgb(255 255 255 / 55%) inset,
    0 2px 8px rgb(22 163 74 / 20%);
}

.general-view__status-trigger--vigilance {
  background: linear-gradient(165deg, rgb(251 146 60), rgb(234 88 12));
  border-color: rgb(194 65 12 / 58%);
  color: white;
  box-shadow:
    0 0 0 1px rgb(255 255 255 / 32%) inset,
    0 2px 8px rgb(234 88 12 / 35%);
}

.general-view__status-trigger--critique {
  background: linear-gradient(165deg, rgb(248 113 113), rgb(220 38 38));
  border-color: rgb(185 28 28 / 58%);
  color: white;
  box-shadow:
    0 0 0 1px rgb(255 255 255 / 32%) inset,
    0 2px 8px rgb(220 38 38 / 34%);
}

.general-view__status-trigger--cloture {
  background: linear-gradient(165deg, rgb(52 211 153), rgb(22 163 74));
  border-color: rgb(21 128 61 / 55%);
  color: white;
  box-shadow:
    0 0 0 1px rgb(255 255 255 / 35%) inset,
    0 2px 8px rgb(22 163 74 / 32%);
}

.general-view__status-trigger--archivee {
  background: linear-gradient(165deg, rgb(148 163 184), rgb(100 116 139));
  border-color: rgb(71 85 105 / 56%);
  color: white;
  box-shadow:
    0 0 0 1px rgb(255 255 255 / 32%) inset,
    0 2px 8px rgb(51 65 85 / 28%);
}

.general-view__status-menu {
  position: absolute;
  left: 0;
  top: calc(100% + 6px);
  min-width: 100%;
  width: max-content;
  max-width: min(260px, 92vw);
  margin: 0;
  padding: 6px;
  list-style: none;
  border-radius: 10px;
  background: #ffffff;
  border: 1px solid rgb(226 232 240 / 95%);
  box-shadow:
    0 4px 6px rgb(15 23 42 / 6%),
    0 14px 36px rgb(15 23 42 / 14%);
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.general-view__status-menu-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  width: 100%;
  margin: 0;
  padding: 7px 9px;
  border: 1px solid transparent;
  border-radius: 8px;
  cursor: pointer;
  font-family: inherit;
  font-size: 0.72rem;
  font-weight: 800;
  letter-spacing: 0.02em;
  text-align: left;
  background-clip: padding-box;
  transition:
    background 0.12s ease,
    border-color 0.12s ease,
    transform 0.1s ease;
}

.general-view__status-menu-item:hover {
  transform: translateY(-0.5px);
}

.general-view__status-menu-item:focus {
  outline: none;
}

.general-view__status-menu-item:focus-visible {
  outline: 2px solid #a5b4fc;
  outline-offset: 0;
}

.general-view__status-menu-item-label {
  flex: 1;
  min-width: 0;
  line-height: 1.3;
  white-space: normal;
}

.general-view__status-menu-check {
  flex-shrink: 0;
  font-size: 12px;
  font-weight: 900;
  line-height: 1;
  opacity: 0.92;
}

.general-view__status-menu-item--bon {
  background: rgb(220 252 231 / 95%);
  border-color: rgb(134 239 172 / 58%);
  color: rgb(6 78 59);
}

.general-view__status-menu-item--bon:hover {
  background: linear-gradient(165deg, rgb(187 247 208), rgb(134 239 172));
  border-color: rgb(74 222 128 / 55%);
  color: rgb(6 78 59);
}

.general-view__status-menu-item--vigilance {
  background: rgb(255 237 213 / 95%);
  border-color: rgb(251 146 60 / 55%);
  color: rgb(154 52 18);
}

.general-view__status-menu-item--vigilance:hover {
  background: linear-gradient(165deg, rgb(253 186 116), rgb(249 115 22));
  border-color: rgb(234 88 12 / 55%);
  color: rgb(67 20 7);
}

.general-view__status-menu-item--critique {
  background: rgb(254 226 226 / 95%);
  border-color: rgb(248 113 113 / 55%);
  color: rgb(127 29 29);
}

.general-view__status-menu-item--critique:hover {
  background: linear-gradient(165deg, rgb(252 165 165), rgb(239 68 68));
  border-color: rgb(220 38 38 / 55%);
  color: rgb(69 10 10);
}

.general-view__status-menu-item--cloture {
  background: rgb(209 250 229 / 92%);
  border-color: rgb(110 231 183 / 55%);
  color: rgb(6 78 59);
}

.general-view__status-menu-item--cloture:hover {
  background: linear-gradient(165deg, rgb(110 231 183), rgb(52 211 153));
  border-color: rgb(16 185 129 / 55%);
  color: rgb(6 78 59);
}

.general-view__status-menu-item--archivee {
  background: rgb(226 232 240 / 92%);
  border-color: rgb(148 163 184 / 56%);
  color: rgb(51 65 85);
}

.general-view__status-menu-item--archivee:hover {
  background: linear-gradient(165deg, rgb(203 213 225), rgb(148 163 184));
  border-color: rgb(100 116 139 / 58%);
  color: rgb(30 41 59);
}

.general-view__progress-cell {
  font-weight: 700;
  color: #1e293b;
}

.general-view__details-btn {
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  background: #fff;
  color: #1e293b;
  padding: 6px 10px;
  font-size: 0.78rem;
  font-weight: 600;
  font-family: inherit;
  cursor: pointer;
}

.general-view__details-btn:hover {
  border-color: #94a3b8;
  background: #f8fafc;
}

/* Panneau Details — ~40 % largeur, même interrupteur que le post-it */
.general-details {
  position: fixed;
  inset: 0;
  z-index: 10060;
  pointer-events: none;
}

.general-details__backdrop {
  position: absolute;
  inset: 0;
  background: rgb(15 23 42 / 38%);
  backdrop-filter: blur(3px);
  -webkit-backdrop-filter: blur(3px);
  pointer-events: auto;
}

.general-details__panel {
  position: absolute;
  top: 0;
  right: 0;
  width: 40%;
  min-width: 300px;
  max-width: 720px;
  height: 100%;
  max-height: 100dvh;
  box-sizing: border-box;
  display: flex;
  flex-direction: column;
  background: #ffffff;
  border-left: 1px solid #e2e8f0;
  box-shadow: -12px 0 48px rgb(15 23 42 / 14%);
  pointer-events: auto;
  font-family: 'Poppins', system-ui, sans-serif;
  animation: general-details-panel-in 0.34s cubic-bezier(0.22, 1, 0.36, 1);
}

@keyframes general-details-panel-in {
  from {
    transform: translateX(100%);
    opacity: 0.94;
  }
  to {
    transform: translateX(0);
    opacity: 1;
  }
}

@media (max-width: 540px) {
  .general-details__panel {
    width: 100%;
    max-width: none;
    min-width: 0;
  }
}

.general-details__head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  padding: 20px 18px 16px;
  border-bottom: 1px solid #eef0f4;
  flex-shrink: 0;
}

.general-details__head-text {
  min-width: 0;
}

.general-details__dept {
  margin: 0 0 6px;
  font-size: 0.7rem;
  font-weight: 800;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: #64748b;
}

.general-details__title {
  margin: 0;
  font-size: 1.05rem;
  font-weight: 700;
  line-height: 1.35;
  color: #0f172a;
}

.general-details__meta {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  gap: 18px 28px;
  padding: 0 18px 18px;
  border-bottom: 1px solid #eef0f4;
  flex-shrink: 0;
}

.general-details__meta-block {
  display: flex;
  flex-direction: column;
  gap: 8px;
  min-width: 0;
}

.general-details__meta-block--progress {
  flex: 1;
  min-width: 160px;
}

.general-details__meta-label {
  font-size: 0.65rem;
  font-weight: 800;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: #64748b;
}

.general-details__status-pill {
  display: inline-flex;
  align-items: center;
  padding: 7px 14px;
  border-radius: 999px;
  font-size: 0.8rem;
  font-weight: 700;
  border: 1px solid transparent;
  width: fit-content;
  max-width: 100%;
  line-height: 1.25;
}

.general-details__status-pill--bon {
  background: linear-gradient(165deg, rgb(187 247 208), rgb(110 231 183));
  border-color: rgb(74 222 128 / 60%);
  color: rgb(6 78 59);
  box-shadow:
    0 0 0 1px rgb(255 255 255 / 55%) inset,
    0 2px 8px rgb(22 163 74 / 18%);
}

.general-details__status-pill--vigilance {
  background: linear-gradient(165deg, rgb(251 146 60), rgb(234 88 12));
  border-color: rgb(194 65 12 / 58%);
  color: white;
  box-shadow:
    0 0 0 1px rgb(255 255 255 / 32%) inset,
    0 2px 8px rgb(234 88 12 / 32%);
}

.general-details__status-pill--critique {
  background: linear-gradient(165deg, rgb(248 113 113), rgb(220 38 38));
  border-color: rgb(185 28 28 / 58%);
  color: white;
  box-shadow:
    0 0 0 1px rgb(255 255 255 / 32%) inset,
    0 2px 8px rgb(220 38 38 / 30%);
}

.general-details__status-pill--cloture {
  background: linear-gradient(165deg, rgb(52 211 153), rgb(22 163 74));
  border-color: rgb(21 128 61 / 55%);
  color: white;
  box-shadow:
    0 0 0 1px rgb(255 255 255 / 35%) inset,
    0 2px 8px rgb(22 163 74 / 28%);
}

.general-details__status-pill--archivee {
  background: linear-gradient(165deg, rgb(148 163 184), rgb(100 116 139));
  border-color: rgb(71 85 105 / 56%);
  color: white;
  box-shadow:
    0 0 0 1px rgb(255 255 255 / 32%) inset,
    0 2px 8px rgb(51 65 85 / 26%);
}

.general-details__progress-row {
  display: flex;
  align-items: center;
  gap: 12px;
}

.general-details__meter {
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

.general-details__donut {
  flex-shrink: 0;
}

.general-details__progress-pct {
  font-size: 1.15rem;
  font-weight: 800;
  color: #1e293b;
  font-variant-numeric: tabular-nums;
  letter-spacing: -0.02em;
}

.general-details__close {
  flex-shrink: 0;
  width: 40px;
  height: 40px;
  margin: 0;
  padding: 0;
  border: none;
  border-radius: 10px;
  background: transparent;
  font-size: 1.5rem;
  line-height: 1;
  color: #64748b;
  cursor: pointer;
  transition:
    background 0.15s ease,
    color 0.15s ease;
}

.general-details__close:hover {
  background: #f1f5f9;
  color: #0f172a;
}

.general-details__close:focus {
  outline: none;
}

.general-details__close:focus-visible {
  outline: 2px solid #a5b4fc;
  outline-offset: 2px;
}

.general-details__switch {
  display: flex;
  flex-shrink: 0;
  gap: 1px;
  margin: 14px 18px 0;
  padding: 3px;
  border-radius: 10px;
  background: #f1f5f9;
  border: 1px solid #e2e8f0;
}

.general-details__switch-btn {
  flex: 1;
  margin: 0;
  border: 1px solid transparent;
  padding: 10px 8px;
  font-family: inherit;
  font-size: 0.78rem;
  font-weight: 600;
  letter-spacing: 0.01em;
  border-radius: 8px;
  cursor: pointer;
  color: #64748b;
  background: transparent;
  line-height: 1.2;
  transition:
    background 0.16s ease,
    color 0.16s ease,
    border-color 0.16s ease,
    box-shadow 0.16s ease;
}

.general-details__switch-btn:hover {
  color: #334155;
  background: rgb(255 255 255 / 70%);
}

.general-details__switch-btn[aria-selected='true'] {
  background: #ffffff;
  color: #0f172a;
  border-color: #e2e8f0;
  box-shadow:
    0 1px 3px rgb(15 23 42 / 8%),
    inset 0 1px 0 rgb(255 255 255 / 80%);
}

.general-details__switch-btn:focus {
  outline: none;
}

.general-details__switch-btn:focus-visible {
  outline: 2px solid #a5b4fc;
  outline-offset: 1px;
}

.general-details__body {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  padding: 16px 18px 24px;
  margin-top: 12px;
}

.general-details__list {
  list-style: decimal;
  list-style-position: outside;
  margin: 0;
  padding: 0 0 0 1.35rem;
  display: flex;
  flex-direction: column;
  gap: 0;
}

.general-details__list-item {
  margin: 0;
  padding: 10px 0 10px 0.35rem;
  font-size: 0.88rem;
  line-height: 1.5;
  color: #1e293b;
  border-bottom: 1px solid #f1f5f9;
}

.general-details__list-item:last-child {
  border-bottom: none;
}

.general-details__list-item::marker {
  font-weight: 700;
  color: #6366f1;
}

.general-details__empty {
  margin: 0;
  padding: 24px 16px;
  text-align: center;
  font-size: 0.9rem;
  font-style: italic;
  color: #94a3b8;
  border: 1px dashed #e2e8f0;
  border-radius: 12px;
  background: #fafbfc;
}
</style>
