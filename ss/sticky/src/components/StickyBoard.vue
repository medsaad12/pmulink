<script setup>
import { ref, computed, markRaw, watch, nextTick, provide } from 'vue'
import { VueFlow } from '@vue-flow/core'
import { Controls } from '@vue-flow/controls'
import { Background } from '@vue-flow/background'
import '@vue-flow/core/dist/style.css'
import '@vue-flow/core/dist/theme-default.css'
import '@vue-flow/controls/dist/style.css'

import { LayoutGrid, Table2 } from 'lucide-vue-next'
import StickyNoteNode from './StickyNoteNode.vue'
import FlowDropListener from './FlowDropListener.vue'
import ProgressDonut from './ProgressDonut.vue'
import {
  DEPARTMENTS,
  PROJECT_ITEMS,
  DRAG_MIME,
  defaultEmptyLists,
  stickyDimensionsFromLists,
  stickyNotePaperColorFromStatus,
  stickyNoteFaceGradientFromStatus,
} from '../constants/projects'

function defaultProjects() {
  return PROJECT_ITEMS.map((p, index) => ({
    id: `preset-${index}-${String(p.name).replace(/\s+/g, '-')}`,
    name: p.name,
    color: p.color,
    departmentId: p.departmentId,
    faitsMarquants: [...p.faitsMarquants],
    commentaires: [...p.commentaires],
    projectStatus: p.projectStatus ?? 'bon',
    progress:
      typeof p.progress === 'number'
        ? Math.min(100, Math.max(0, Math.round(p.progress)))
        : 0,
  }))
}

const PROJECTS_LS_KEY = 'sticky-board-projects-v1'

function loadProjectsFromStorage() {
  try {
    const raw = localStorage.getItem(PROJECTS_LS_KEY)
    if (!raw) return null
    const parsed = JSON.parse(raw)
    if (!Array.isArray(parsed) || parsed.length === 0) return null
    const ok = parsed.every(
      (p) =>
        p &&
        typeof p === 'object' &&
        typeof p.id === 'string' &&
        typeof p.name === 'string',
    )
    if (!ok) return null
    return parsed
  } catch {
    return null
  }
}

const projects = ref(loadProjectsFromStorage() ?? defaultProjects())

/** `null` = afficher tous les départements dans la liste. */
const departmentFilterId = ref(null)

const visibleDepartments = computed(() => {
  const id = departmentFilterId.value
  if (id == null) return DEPARTMENTS
  return DEPARTMENTS.filter((d) => d.id === id)
})

function setDepartmentFilter(id) {
  departmentFilterId.value = id
  if (id == null) return

  nodes.value = nodes.value.filter((n) => {
    if (n.type !== 'sticky') return true
    const pid = n.data?.projectId
    if (!pid) return true
    const p = projects.value.find((x) => x.id === pid)
    const dept = p?.departmentId || 'dnt'
    return dept === id
  })

  for (const item of projectsInDepartment(id)) {
    if (boardProjectIds.value.has(item.id)) continue
    addStickyForProject(item)
  }
}

const modalOpen = ref(false)
const formName = ref('')
const formDepartmentId = ref('dnt')
const formError = ref('')
const modalTitleId = 'modal-add-project-title'
const modalFirstFieldRef = ref(null)

function openProjectModal() {
  formError.value = ''
  formDepartmentId.value = 'dnt'
  modalOpen.value = true
}

function closeProjectModal() {
  modalOpen.value = false
}

watch(modalOpen, (open, _prev, onCleanup) => {
  document.body.style.overflow = open ? 'hidden' : ''

  if (!open) return

  nextTick(() => {
    modalFirstFieldRef.value?.focus?.()
  })

  const onKeydown = (e) => {
    if (e.key === 'Escape') closeProjectModal()
  }
  window.addEventListener('keydown', onKeydown)

  onCleanup(() => {
    window.removeEventListener('keydown', onKeydown)
    document.body.style.overflow = ''
  })
})

function newProjectId() {
  return typeof crypto !== 'undefined' && crypto.randomUUID
    ? `proj-${crypto.randomUUID()}`
    : `proj-${Date.now()}-${Math.random().toString(36).slice(2, 9)}`
}

function addProjectFromForm() {
  formError.value = ''
  const name = formName.value.trim()
  if (!name) {
    formError.value = 'Indiquez un nom de fait marquant.'
    return
  }

  const dept = DEPARTMENTS.some((d) => d.id === formDepartmentId.value)
    ? formDepartmentId.value
    : 'dnt'

  projects.value = [
    ...projects.value,
    {
      id: newProjectId(),
      name,
      color: '#94a3b8',
      departmentId: dept,
      projectStatus: 'bon',
      progress: 0,
      ...defaultEmptyLists(),
    },
  ]

  formName.value = ''
  formDepartmentId.value = 'dnt'
  closeProjectModal()
}

const nodes = ref([])
const edges = ref([])

const STICKY_LAYOUT_LS_KEY = 'sticky-board-layouts-v1'

function loadStickyLayoutsFromStorage() {
  try {
    const raw = localStorage.getItem(STICKY_LAYOUT_LS_KEY)
    if (!raw) return {}
    const o = JSON.parse(raw)
    return o && typeof o === 'object' ? o : {}
  } catch {
    return {}
  }
}

const stickyLayoutByProjectId = ref(loadStickyLayoutsFromStorage())

function flushStickyLayoutsToStorage() {
  try {
    localStorage.setItem(
      STICKY_LAYOUT_LS_KEY,
      JSON.stringify(stickyLayoutByProjectId.value),
    )
  } catch {
    /* ignore quota / private mode */
  }
}

/** Remember flow position (and optional size) per projet for reopen / changement de département. */
function persistStickyLayoutForProject(projectId, patch) {
  if (!projectId || typeof projectId !== 'string' || !patch || typeof patch !== 'object')
    return
  const prev = stickyLayoutByProjectId.value[projectId] || {}
  const next = { ...prev }
  if (typeof patch.x === 'number' && Number.isFinite(patch.x)) next.x = Math.round(patch.x)
  if (typeof patch.y === 'number' && Number.isFinite(patch.y)) next.y = Math.round(patch.y)
  if (typeof patch.width === 'number' && patch.width >= 200) next.width = Math.round(patch.width)
  if (typeof patch.height === 'number' && patch.height >= 168)
    next.height = Math.round(patch.height)
  if (typeof next.x !== 'number' || typeof next.y !== 'number') return
  stickyLayoutByProjectId.value = {
    ...stickyLayoutByProjectId.value,
    [projectId]: next,
  }
  flushStickyLayoutsToStorage()
}

function layoutPatchFromStickyNode(n) {
  if (n.type !== 'sticky' || !n.data?.projectId) return null
  const patch = { x: n.position.x, y: n.position.y }
  if (typeof n.width === 'number' && n.width > 0) patch.width = n.width
  else if (n.dimensions?.width > 0) patch.width = n.dimensions.width
  if (typeof n.height === 'number' && n.height > 0) patch.height = n.height
  else if (n.dimensions?.height > 0) patch.height = n.dimensions.height
  return patch
}

function onStickyLayoutPersistFromDrag(evt) {
  const list =
    evt?.nodes?.length > 0 ? evt.nodes : evt?.node ? [evt.node] : []
  for (const n of list) {
    const patch = layoutPatchFromStickyNode(n)
    if (patch) persistStickyLayoutForProject(n.data.projectId, patch)
  }
}

function persistAllStickyLayoutsFromNodes() {
  for (const n of nodes.value) {
    const patch = layoutPatchFromStickyNode(n)
    if (patch) persistStickyLayoutForProject(n.data.projectId, patch)
  }
}

function saveBoard() {
  persistAllStickyLayoutsFromNodes()
  try {
    localStorage.setItem(PROJECTS_LS_KEY, JSON.stringify(projects.value))
  } catch {
    /* ignore quota / private mode */
  }
}

const hasStickyNodes = computed(() =>
  nodes.value.some((n) => n.type === 'sticky'),
)

function closeAllStickies() {
  nodes.value = nodes.value.filter((n) => n.type !== 'sticky')
  edges.value = []
}

function syncStickyProjectMeta(projectId, patch) {
  const idx = projects.value.findIndex((p) => p.id === projectId)
  if (idx === -1) return
  const cur = projects.value[idx]
  projects.value.splice(idx, 1, { ...cur, ...patch })
}

provide('syncStickyProjectMeta', syncStickyProjectMeta)
provide('persistStickyLayout', persistStickyLayoutForProject)

function projectsInDepartment(deptId) {
  return projects.value.filter((p) => (p.departmentId || 'dnt') === deptId)
}

function progressPercentFor(item) {
  const onBoard = nodes.value.find(
    (n) => n.type === 'sticky' && n.data?.projectId === item.id,
  )
  if (onBoard?.data && typeof onBoard.data.progress === 'number')
    return onBoard.data.progress
  if (typeof item.progress === 'number') return item.progress
  return 0
}

function toolboxPaperColor(item) {
  const onBoard = nodes.value.find(
    (n) => n.type === 'sticky' && n.data?.projectId === item.id,
  )
  const status = onBoard?.data?.projectStatus ?? item.projectStatus
  return stickyNotePaperColorFromStatus(status)
}

function toolboxPaperFace(item) {
  const onBoard = nodes.value.find(
    (n) => n.type === 'sticky' && n.data?.projectId === item.id,
  )
  const status = onBoard?.data?.projectStatus ?? item.projectStatus
  return stickyNoteFaceGradientFromStatus(status)
}

/** Projets qui ont déjà un post-it sur le tableau (un seul par projet). */
const boardProjectIds = computed(() => {
  const s = new Set()
  for (const n of nodes.value) {
    if (n.type === 'sticky' && n.data?.projectId)
      s.add(n.data.projectId)
  }
  return s
})

/** Projet mis en avant dans la liste quand son post-it est sélectionné sur le tableau. */
const selectedSidebarProjectId = computed(() => {
  const stickySel = nodes.value.filter(
    (n) => n.selected && n.type === 'sticky' && n.data?.projectId,
  )
  if (stickySel.length !== 1) return null
  return stickySel[0].data.projectId
})

function newStickyNodeId() {
  return typeof crypto !== 'undefined' && crypto.randomUUID
    ? `note-${crypto.randomUUID()}`
    : `note-${Date.now()}-${Math.random().toString(36).slice(2, 9)}`
}

function addStickyForProject(item) {
  nodes.value = nodes.value.map((n) => ({ ...n, selected: false }))

  const faits = Array.isArray(item.faitsMarquants) ? [...item.faitsMarquants] : []
  const comm = Array.isArray(item.commentaires) ? [...item.commentaires] : []
  const { width: noteW, height: noteH } = stickyDimensionsFromLists(faits, comm)

  const idx = nodes.value.length
  /** 3 colonnes, espacement large (~largeur post-it) + léger zigzag pour éviter l’empilement vertical serré. */
  const cols = 3
  const gapX = 340
  const gapY = Math.max(200, Math.min(360, Math.round(noteH + 64)))
  const col = idx % cols
  const row = Math.floor(idx / cols)
  const stagger = (row % 2) * 72
  const defaultPosition = {
    x: 44 + col * gapX + stagger,
    y: 52 + row * gapY,
  }

  const saved = stickyLayoutByProjectId.value[item.id]
  const position =
    saved &&
    typeof saved.x === 'number' &&
    Number.isFinite(saved.x) &&
    typeof saved.y === 'number' &&
    Number.isFinite(saved.y)
      ? { x: saved.x, y: saved.y }
      : defaultPosition
  const nodeWidth =
    saved && typeof saved.width === 'number' && saved.width >= 200 ? saved.width : noteW
  const nodeHeight =
    saved && typeof saved.height === 'number' && saved.height >= 168 ? saved.height : noteH

  nodes.value = [
    ...nodes.value,
    {
      id: newStickyNodeId(),
      type: 'sticky',
      position,
      selected: true,
      width: nodeWidth,
      height: nodeHeight,
      data: {
        projectId: item.id,
        projectName: item.name,
        faitsMarquants: faits,
        commentaires: comm,
        stickyView: 'faits',
        userSizedNote: false,
        projectStatus: item.projectStatus ?? 'bon',
        progress:
          typeof item.progress === 'number'
            ? Math.min(100, Math.max(0, Math.round(item.progress)))
            : 0,
      },
    },
  ]
}

const hasAnyNotOnBoard = computed(() => {
  for (const dept of visibleDepartments.value) {
    for (const item of projectsInDepartment(dept.id)) {
      if (!boardProjectIds.value.has(item.id)) return true
    }
  }
  return false
})

function addAllVisibleStickiesToBoard() {
  for (const dept of visibleDepartments.value) {
    for (const item of projectsInDepartment(dept.id)) {
      if (boardProjectIds.value.has(item.id)) continue
      addStickyForProject(item)
    }
  }
}

function onProjectChipClick(item) {
  if (boardProjectIds.value.has(item.id)) {
    nodes.value = nodes.value.map((n) => ({
      ...n,
      selected:
        n.type === 'sticky' && n.data?.projectId === item.id ? true : false,
    }))
    return
  }
  addStickyForProject(item)
}

function onToolboxDragStart(event, item) {
  if (boardProjectIds.value.has(item.id)) {
    event.preventDefault()
    return
  }
  const payload = JSON.stringify({
    id: item.id,
    name: item.name,
    faitsMarquants: item.faitsMarquants ?? [],
    commentaires: item.commentaires ?? [],
    projectStatus: item.projectStatus ?? 'bon',
    progress:
      typeof item.progress === 'number'
        ? Math.min(100, Math.max(0, Math.round(item.progress)))
        : 0,
  })
  event.dataTransfer?.setData(DRAG_MIME, payload)
  event.dataTransfer?.setData('application/json', payload)
  event.dataTransfer.effectAllowed = 'copy'
}

const nodeTypes = {
  sticky: markRaw(StickyNoteNode),
}

const emit = defineEmits(['set-view'])

const toolboxOpen = ref(false)

function toggleToolbox() {
  toolboxOpen.value = !toolboxOpen.value
}

const METEO_LS_KEY = 'sticky-department-meteo-v1'

const METEO_LABELS = {
  soleil: 'Soleil — Bonne situation',
  brouillard: 'Nuageux — Situation un peu difficile',
  difficile: 'Orage — Situation difficile',
}

/** Du plus favorable (gauche) au plus difficile (droite). Valeurs internes inchangées. */
const METEO_OPTIONS = [
  { value: 'soleil', icon: '/sun.png', tone: 'sun' },
  { value: 'brouillard', icon: '/cloud.png', tone: 'cloud' },
  { value: 'difficile', icon: '/storm.png', tone: 'storm' },
]

function normalizeMeteo(raw) {
  if (raw === 'soleil' || raw === 'brouillard' || raw === 'difficile') return raw
  return 'brouillard'
}

function loadDepartmentMeteoMap() {
  const defaults = Object.fromEntries(
    DEPARTMENTS.map((d) => [d.id, normalizeMeteo(d.meteo)]),
  )
  try {
    const stored = localStorage.getItem(METEO_LS_KEY)
    if (!stored) return defaults
    const parsed = JSON.parse(stored)
    if (!parsed || typeof parsed !== 'object') return defaults
    const next = { ...defaults }
    for (const id of Object.keys(next)) {
      if (id in parsed) next[id] = normalizeMeteo(parsed[id])
    }
    return next
  } catch {
    return defaults
  }
}

function persistDepartmentMeteoMap(map) {
  try {
    localStorage.setItem(METEO_LS_KEY, JSON.stringify(map))
  } catch {
    /* ignore */
  }
}

const departmentMeteoById = ref(loadDepartmentMeteoMap())

function meteoForDept(deptId) {
  if (!deptId) return 'brouillard'
  return normalizeMeteo(departmentMeteoById.value[deptId])
}

const selectedDepartmentLabel = computed(() => {
  const id = departmentFilterId.value
  if (!id) return ''
  return DEPARTMENTS.find((d) => d.id === id)?.label ?? id
})

/** Lundi → dimanche de la semaine calendaire courante (lundi = début). */
function startOfWeekMonday(ref = new Date()) {
  const d = new Date(ref.getFullYear(), ref.getMonth(), ref.getDate())
  const dow = d.getDay()
  const delta = dow === 0 ? -6 : 1 - dow
  d.setDate(d.getDate() + delta)
  return d
}

const currentWeekRangeLabel = computed(() => {
  const monday = startOfWeekMonday()
  const sunday = new Date(monday)
  sunday.setDate(monday.getDate() + 6)
  const dateOpts = { day: 'numeric', month: 'long', year: 'numeric' }
  const mon = monday.toLocaleDateString('fr-FR', dateOpts)
  const sun = sunday.toLocaleDateString('fr-FR', dateOpts)
  return `Semaine : Lundi (${mon}) / Dimanche (${sun})`
})

function setDepartmentMeteo(deptId, value) {
  if (!deptId) return
  const v = normalizeMeteo(value)
  departmentMeteoById.value = { ...departmentMeteoById.value, [deptId]: v }
  persistDepartmentMeteoMap(departmentMeteoById.value)
}

watch(toolboxOpen, (open, _prev, onCleanup) => {
  if (!open) return

  const onKeydown = (e) => {
    if (e.key !== 'Escape') return
    if (!modalOpen.value) toolboxOpen.value = false
  }
  window.addEventListener('keydown', onKeydown)
  onCleanup(() => window.removeEventListener('keydown', onKeydown))
})
</script>

<template>
  <div
    class="sticky-board"
    :class="{ 'sticky-board--toolbox-open': toolboxOpen }"
  >
    <button
      type="button"
      class="sticky-board__save-btn"
      aria-label="Enregistrer les faits marquants et la disposition"
      title="Enregistrer les faits marquants et la disposition"
      @click="saveBoard"
    >
      Enregistrer
    </button>

    <nav
      class="sticky-board__dept-filter"
      aria-label="Filtrer les faits marquants par département"
    >
      <div class="sticky-board__dept-filter-stack">
        <div class="sticky-board__dept-filter-inner">
          <div
            class="sticky-board__view-switch"
            role="group"
            aria-label="Choisir l'affichage : cartes ou tableau"
          >
            <button
              type="button"
              class="sticky-board__view-switch-btn sticky-board__view-switch-btn--active"
              aria-pressed="true"
              aria-label="Vue cartes — tableau de post-its"
              title="Vue cartes"
            >
              <LayoutGrid :size="18" :stroke-width="2" aria-hidden="true" />
            </button>
            <button
              type="button"
              class="sticky-board__view-switch-btn"
              aria-pressed="false"
              aria-label="Vue tableau — faits marquants"
              title="Vue tableau"
              @click="emit('set-view', 'general')"
            >
              <Table2 :size="18" :stroke-width="2" aria-hidden="true" />
            </button>
          </div>
          <span class="sticky-board__dept-filter-sep" aria-hidden="true" />
          <button
            type="button"
            class="sticky-board__dept-filter-btn"
            :class="{
              'sticky-board__dept-filter-btn--active': departmentFilterId === null,
            }"
            :aria-pressed="departmentFilterId === null"
            @click="setDepartmentFilter(null)"
          >
            Tous
          </button>
          <button
            v-for="d in DEPARTMENTS"
            :key="'filter-' + d.id"
            type="button"
            class="sticky-board__dept-filter-btn"
            :class="{
              'sticky-board__dept-filter-btn--active': departmentFilterId === d.id,
            }"
            :aria-pressed="departmentFilterId === d.id"
            @click="setDepartmentFilter(d.id)"
          >
            {{ d.label }}
          </button>
        </div>
      </div>
    </nav>

    <div class="sticky-board__brand">
      <img
        src="/logopmo.png"
        alt="Sticky"
        class="sticky-board__logo"
        width="380"
        height="84"
        decoding="async"
      />
      <p class="sticky-board__week-range">{{ currentWeekRangeLabel }}</p>

      <div
        v-if="departmentFilterId"
        class="sticky-board__weather"
        role="group"
        :aria-label="`État du département ${selectedDepartmentLabel}`"
      >
        <p class="sticky-board__weather-caption">
          État du département
          <span class="sticky-board__weather-dept">{{ selectedDepartmentLabel }}</span>
        </p>
        <div
          class="sticky-board__weather-options"
          role="radiogroup"
          :aria-label="`Choisir l'état du département ${selectedDepartmentLabel}`"
        >
          <button
            v-for="opt in METEO_OPTIONS"
            :key="opt.value"
            type="button"
            class="sticky-board__weather-btn"
            :class="[
              `sticky-board__weather-btn--${opt.tone}`,
              {
                'sticky-board__weather-btn--active':
                  meteoForDept(departmentFilterId) === opt.value,
              },
            ]"
            role="radio"
            :aria-checked="meteoForDept(departmentFilterId) === opt.value"
            :aria-label="METEO_LABELS[opt.value]"
            :title="METEO_LABELS[opt.value]"
            @click="setDepartmentMeteo(departmentFilterId, opt.value)"
          >
            <img
              :src="opt.icon"
              :alt="METEO_LABELS[opt.value]"
              class="sticky-board__weather-icon"
              draggable="false"
              decoding="async"
            />
          </button>
        </div>
      </div>
    </div>

    <div class="sticky-board__canvas-wrap">
      <VueFlow
        v-model:nodes="nodes"
        v-model:edges="edges"
        class="sticky-board__flow"
        :node-types="nodeTypes"
        :nodes-connectable="false"
        :snap-to-grid="true"
        :snap-grid="[16, 16]"
        :min-zoom="0.25"
        :max-zoom="1.75"
        :default-viewport="{ x: 0, y: 0, zoom: 1 }"
        delete-key-code="Delete"
        @node-drag-stop="onStickyLayoutPersistFromDrag"
        @selection-drag-stop="onStickyLayoutPersistFromDrag"
      >
        <FlowDropListener />
        <Background variant="dots" :gap="20" :size="1.2" color="#c7cad4" />
        <Controls position="bottom-left" />
      </VueFlow>
    </div>

    <Transition name="toolbox-drawer">
      <aside
        v-if="toolboxOpen"
        id="sticky-toolbar-panel"
        class="sticky-board__toolbar"
        aria-label="Faits marquants"
      >
        <header class="sticky-board__toolbar-head">
          <h1 class="sticky-board__title">Faits marquants</h1>
          <p class="sticky-board__hint">
            Glissez un fait marquant sur le tableau ou cliquez dessus pour l’ouvrir
            (un seul post-it par fait marquant). Fait marquant déjà sur le tableau&nbsp;:
            clic pour le sélectionner.
          </p>
        </header>

        <button
          type="button"
          class="sticky-board__cta-dashed"
          @click="openProjectModal"
        >
          Ajouter un fait marquant
        </button>

        <p class="sticky-board__list-label">Faits marquants disponibles</p>
        <div class="sticky-board__list-actions">
          <button
            type="button"
            class="sticky-board__list-action-btn"
            :disabled="!hasStickyNodes"
            aria-label="Retirer tous les post-its du tableau"
            title="Retirer tous les post-its du tableau"
            @click="closeAllStickies"
          >
            Réduire tous
          </button>
          <button
            type="button"
            class="sticky-board__list-action-btn sticky-board__list-action-btn--primary"
            :disabled="!hasAnyNotOnBoard"
            aria-label="Ajouter sur le tableau tous les faits marquants visibles"
            title="Ajouter sur le tableau tous les faits marquants visibles"
            @click="addAllVisibleStickiesToBoard"
          >
            Afficher tous
          </button>
        </div>
        <div class="sticky-board__dept-groups">
          <section
            v-for="dept in visibleDepartments"
            :key="dept.id"
            class="sticky-board__dept"
          >
            <h2 class="sticky-board__dept-title">
              <span class="sticky-board__dept-name">{{ dept.label }}</span>
              <span
                v-if="dept.id === 'dnt'"
                class="sticky-board__dept-hint"
                title="Direction des nouvelles technologies"
              >Direction des nouvelles technologies</span>
            </h2>
            <ul class="sticky-board__list">
              <li v-for="item in projectsInDepartment(dept.id)" :key="item.id">
                <button
                  type="button"
                  class="sticky-board__chip"
                  :class="{
                    'sticky-board__chip--on-board':
                      boardProjectIds.has(item.id),
                    'sticky-board__chip--active':
                      selectedSidebarProjectId === item.id,
                  }"
                  :draggable="!boardProjectIds.has(item.id)"
                  @click="onProjectChipClick(item)"
                  @dragstart="onToolboxDragStart($event, item)"
                >
                  <span
                    class="sticky-board__swatch"
                    :style="{ background: toolboxPaperFace(item) }"
                    aria-hidden="true"
                  />
                  <span class="sticky-board__chip-text">
                    <span class="sticky-board__chip-name">{{ item.name }}</span>
                    <span
                      class="sticky-board__chip-meter"
                      :style="{ '--sticky-bg': toolboxPaperColor(item) }"
                    >
                      <ProgressDonut
                        class="sticky-board__chip-donut"
                        :percent="progressPercentFor(item)"
                        :size="30"
                        :stroke="3.25"
                      />
                    </span>
                  </span>
                </button>
              </li>
            </ul>
          </section>
        </div>
      </aside>
    </Transition>

    <button
      type="button"
      class="sticky-board__fab"
      :class="{ 'sticky-board__fab--panel-open': toolboxOpen }"
      aria-label="Boîte à outils"
      :aria-expanded="toolboxOpen"
      aria-controls="sticky-toolbar-panel"
      @click="toggleToolbox"
    >
      <svg
        xmlns="http://www.w3.org/2000/svg"
        width="26"
        height="26"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="2"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
      >
        <path d="M12 6v8l3-3 3 3V6" />
        <path
          d="M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2z"
        />
      </svg>
    </button>

    <Teleport to="body">
      <div
        v-if="modalOpen"
        class="sticky-board__modal-root"
        role="presentation"
      >
        <div
          class="sticky-board__modal-backdrop"
          aria-hidden="true"
          @click="closeProjectModal"
        />
        <div
          class="sticky-board__modal-dialog"
          role="dialog"
          aria-modal="true"
          :aria-labelledby="modalTitleId"
        >
          <div class="sticky-board__modal-header">
            <h2 :id="modalTitleId" class="sticky-board__modal-title">
              Nouveau fait marquant
            </h2>
            <button
              type="button"
              class="sticky-board__modal-close"
              aria-label="Fermer"
              @click="closeProjectModal"
            >
              ×
            </button>
          </div>

          <div class="sticky-board__modal-body">
            <div class="sticky-board__field">
              <label class="sticky-board__label" for="modal-project-name">
                Nom
              </label>
              <input
                id="modal-project-name"
                ref="modalFirstFieldRef"
                v-model="formName"
                type="text"
                class="sticky-board__input"
                placeholder="Ex. Point clé métier"
                maxlength="120"
                autocomplete="off"
              />
            </div>
            <div class="sticky-board__field">
              <label class="sticky-board__label" for="modal-project-dept">
                Département
              </label>
              <select
                id="modal-project-dept"
                v-model="formDepartmentId"
                class="sticky-board__select"
              >
                <option
                  v-for="d in DEPARTMENTS"
                  :key="d.id"
                  :value="d.id"
                >
                  {{ d.label }}
                </option>
              </select>
            </div>
            <p v-if="formError" class="sticky-board__form-error" role="alert">
              {{ formError }}
            </p>
          </div>

          <div class="sticky-board__modal-footer">
            <button
              type="button"
              class="sticky-board__modal-btn-secondary"
              @click="closeProjectModal"
            >
              Annuler
            </button>
            <button
              type="button"
              class="sticky-board__modal-btn-primary"
              @click="addProjectFromForm"
            >
              Ajouter à la liste
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<style scoped>
.sticky-board {
  position: relative;
  width: 100vw;
  height: 100vh;
  height: 100dvh;
  overflow: hidden;
  font-family: 'Poppins', system-ui, sans-serif;
  background: #f4f5f8;
}

.sticky-board__dept-filter {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  z-index: 10041;
  display: flex;
  justify-content: center;
  padding: calc(18px + env(safe-area-inset-top, 0px)) 20px 10px;
  box-sizing: border-box;
  pointer-events: none;
}

.sticky-board__dept-filter-stack {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  max-width: min(920px, calc(100vw - 32px));
  pointer-events: auto;
}

.sticky-board__dept-filter-inner {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  align-items: center;
  gap: 8px;
  width: 100%;
}

.sticky-board__dept-filter-btn {
  font-family: inherit;
  font-size: 0.78rem;
  font-weight: 600;
  letter-spacing: 0.02em;
  padding: 8px 14px;
  border-radius: 999px;
  border: 1px solid #e2e8f0;
  background: rgb(255 255 255 / 92%);
  color: #475569;
  cursor: pointer;
  box-shadow: 0 1px 3px rgb(15 23 42 / 6%);
  transition:
    background 0.15s ease,
    border-color 0.15s ease,
    color 0.15s ease,
    box-shadow 0.15s ease,
    transform 0.1s ease;
}

.sticky-board__dept-filter-btn:active {
  transform: scale(0.97);
}

.sticky-board__dept-filter-btn:focus {
  outline: none;
}

.sticky-board__dept-filter-btn:focus-visible {
  outline: 2px solid #a5b4fc;
  outline-offset: 2px;
}

.sticky-board__dept-filter-btn--active {
  border-color: #818cf8;
  background: linear-gradient(165deg, rgb(129 140 248), rgb(79 70 229));
  color: #ffffff;
  box-shadow:
    0 0 0 1px rgb(255 255 255 / 22%) inset,
    0 4px 14px rgb(79 70 229 / 35%);
}

.sticky-board__dept-filter-btn:hover:not(.sticky-board__dept-filter-btn--active) {
  border-color: #c7d2fe;
  color: #4338ca;
  background: #f1f5f9;
}

.sticky-board__dept-filter-btn--active:hover {
  border-color: #6366f1;
  color: #ffffff;
  background: linear-gradient(165deg, rgb(147 155 252), rgb(67 56 202));
  box-shadow:
    0 0 0 1px rgb(255 255 255 / 26%) inset,
    0 4px 18px rgb(79 70 229 / 42%);
}

.sticky-board__dept-filter-sep {
  width: 1px;
  height: 22px;
  margin: 0 2px 0 6px;
  background: #e2e8f0;
  flex-shrink: 0;
}

.sticky-board__view-switch {
  display: inline-flex;
  flex-shrink: 0;
  gap: 2px;
  padding: 3px;
  border-radius: 999px;
  border: 1px solid #e2e8f0;
  background: rgb(255 255 255 / 92%);
  box-shadow: 0 1px 3px rgb(15 23 42 / 6%);
}

.sticky-board__view-switch-btn {
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

.sticky-board__view-switch-btn:hover:not(.sticky-board__view-switch-btn--active) {
  color: #4338ca;
  background: #f1f5f9;
}

.sticky-board__view-switch-btn:active:not(.sticky-board__view-switch-btn--active) {
  transform: scale(0.96);
}

.sticky-board__view-switch-btn:focus {
  outline: none;
}

.sticky-board__view-switch-btn:focus-visible {
  outline: 2px solid #a5b4fc;
  outline-offset: 1px;
}

.sticky-board__view-switch-btn--active {
  background: linear-gradient(165deg, rgb(129 140 248), rgb(79 70 229));
  color: #ffffff;
  box-shadow:
    0 0 0 1px rgb(255 255 255 / 22%) inset,
    0 2px 8px rgb(79 70 229 / 35%);
  cursor: default;
  pointer-events: none;
}

.sticky-board__brand {
  position: fixed;
  top: calc(14px + env(safe-area-inset-top, 0px));
  left: calc(14px + env(safe-area-inset-left, 0px));
  z-index: 10041;
  pointer-events: none;
}

.sticky-board__logo {
  display: block;
  height: 72px;
  width: auto;
  max-width: min(340px, 55vw);
  object-fit: contain;
  object-position: left center;
  filter: drop-shadow(0 1px 2px rgb(15 23 42 / 10%));
  line-height: 0;
}

.sticky-board__week-range {
  margin: 8px 0 0;
  max-width: min(340px, 55vw);
  font-family: inherit;
  font-size: 0.72rem;
  font-weight: 600;
  letter-spacing: 0.02em;
  line-height: 1.35;
  color: #475569;
  text-wrap: balance;
}

.sticky-board__weather {
  margin-top: 9px;
  max-width: min(240px, 44vw);
  pointer-events: auto;
  display: flex;
  flex-direction: column;
  gap: 5px;
  padding: 8px 9px 9px;
  border-radius: 10px;
  background: rgb(255 255 255 / 92%);
  border: 1px solid #e2e8f0;
  box-shadow: 0 3px 10px rgb(15 23 42 / 6%);
  backdrop-filter: blur(6px);
  -webkit-backdrop-filter: blur(6px);
}

.sticky-board__weather-caption {
  margin: 0;
  font-family: inherit;
  font-size: 0.58rem;
  font-weight: 700;
  letter-spacing: 0.055em;
  text-transform: uppercase;
  color: #64748b;
  line-height: 1.3;
}

.sticky-board__weather-dept {
  color: #1e293b;
  font-weight: 800;
  letter-spacing: 0.04em;
}

.sticky-board__weather-options {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 6px;
}

.sticky-board__weather-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  height: 40px;
  padding: 5px;
  margin: 0;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  background: #f8fafc;
  cursor: pointer;
  transition:
    transform 0.12s ease,
    border-color 0.16s ease,
    background 0.16s ease,
    box-shadow 0.18s ease,
    filter 0.18s ease;
}

.sticky-board__weather-btn:hover {
  transform: translateY(-0.75px);
  border-color: #cbd5e1;
  background: #ffffff;
  box-shadow: 0 3px 9px rgb(15 23 42 / 8%);
}

.sticky-board__weather-btn:active {
  transform: translateY(0) scale(0.97);
}

.sticky-board__weather-btn:focus {
  outline: none;
}

.sticky-board__weather-btn:focus-visible {
  outline: 2px solid #a5b4fc;
  outline-offset: 1px;
}

.sticky-board__weather-icon {
  display: block;
  width: 26px;
  height: 26px;
  object-fit: contain;
  pointer-events: none;
  filter: grayscale(0.55) saturate(0.7);
  opacity: 0.6;
  transition:
    filter 0.2s ease,
    opacity 0.2s ease,
    transform 0.2s ease;
}

.sticky-board__weather-btn--active {
  transform: translateY(-0.75px);
}

.sticky-board__weather-btn--active .sticky-board__weather-icon {
  filter: drop-shadow(0 1px 2px rgb(15 23 42 / 25%));
  opacity: 1;
  transform: scale(1.07);
}

.sticky-board__weather-btn--active.sticky-board__weather-btn--sun {
  border-color: #b45309;
  background: linear-gradient(160deg, #fbbf24 0%, #f59e0b 60%, #d97706 100%);
  box-shadow:
    inset 0 1px 0 rgb(255 255 255 / 50%),
    0 0 0 1.5px rgb(245 158 11 / 35%),
    0 4px 12px rgb(217 119 6 / 45%);
}

.sticky-board__weather-btn--active.sticky-board__weather-btn--cloud {
  border-color: #1d4ed8;
  background: linear-gradient(160deg, #60a5fa 0%, #3b82f6 55%, #2563eb 100%);
  box-shadow:
    inset 0 1px 0 rgb(255 255 255 / 45%),
    0 0 0 1.5px rgb(59 130 246 / 35%),
    0 4px 12px rgb(37 99 235 / 45%);
}

.sticky-board__weather-btn--active.sticky-board__weather-btn--storm {
  border-color: #0f172a;
  background: linear-gradient(160deg, #475569 0%, #334155 55%, #1e293b 100%);
  box-shadow:
    inset 0 1px 0 rgb(255 255 255 / 22%),
    0 0 0 1.5px rgb(51 65 85 / 45%),
    0 4px 12px rgb(15 23 42 / 50%);
}

.sticky-board__toolbar {
  position: fixed;
  top: 0;
  right: 0;
  bottom: 0;
  width: min(292px, 92vw);
  flex-shrink: 0;
  background: #ffffff;
  border-left: 1px solid #e2e5eb;
  padding: 20px 16px;
  box-sizing: border-box;
  display: flex;
  flex-direction: column;
  gap: 14px;
  box-shadow: -8px 0 40px rgb(15 23 42 / 12%);
  z-index: 10042;
  overflow-y: auto;
  pointer-events: auto;
}

.toolbox-drawer-enter-active,
.toolbox-drawer-leave-active {
  transition:
    transform 0.44s cubic-bezier(0.22, 1.28, 0.36, 1),
    opacity 0.38s cubic-bezier(0.22, 1, 0.36, 1);
}

.toolbox-drawer-enter-from,
.toolbox-drawer-leave-to {
  transform: translate3d(104%, 36px, 0) scale(0.86);
  opacity: 0;
  transform-origin: 100% 100%;
}

.toolbox-drawer-enter-to,
.toolbox-drawer-leave-from {
  transform: translate3d(0, 0, 0) scale(1);
  opacity: 1;
  transform-origin: 100% 100%;
}

.sticky-board__fab {
  position: fixed;
  z-index: 10043;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 56px;
  height: 56px;
  padding: 0;
  border: none;
  border-radius: 999px;
  cursor: pointer;
  color: #fff;
  background: linear-gradient(145deg, #6366f1 0%, #4f46e5 45%, #4338ca 100%);
  box-shadow:
    0 10px 28px rgb(79 70 229 / 48%),
    0 3px 10px rgb(15 23 42 / 18%),
    inset 0 1px 0 rgb(255 255 255 / 22%);
  bottom: calc(22px + env(safe-area-inset-bottom, 0px));
  right: calc(22px + env(safe-area-inset-right, 0px));
  transition:
    transform 0.38s cubic-bezier(0.22, 1.28, 0.36, 1),
    box-shadow 0.25s ease,
    right 0.38s cubic-bezier(0.22, 1.28, 0.36, 1),
    bottom 0.25s ease;
}

.sticky-board__fab:hover {
  transform: scale(1.06);
  box-shadow:
    0 14px 36px rgb(79 70 229 / 52%),
    0 4px 12px rgb(15 23 42 / 22%),
    inset 0 1px 0 rgb(255 255 255 / 25%);
}

.sticky-board__fab:active {
  transform: scale(0.94);
}

.sticky-board__fab--panel-open {
  right: calc(min(292px, 92vw) + 18px + env(safe-area-inset-right, 0px));
  transform: scale(1);
  box-shadow:
    0 8px 22px rgb(79 70 229 / 38%),
    0 2px 8px rgb(15 23 42 / 14%),
    inset 0 1px 0 rgb(255 255 255 / 20%);
}

.sticky-board__fab--panel-open:hover {
  transform: scale(1.05);
}

.sticky-board__toolbar-head {
  margin: 0;
}

.sticky-board__save-btn {
  position: fixed;
  top: calc(16px + env(safe-area-inset-top, 0px));
  right: calc(18px + env(safe-area-inset-right, 0px));
  z-index: 10041;
  font-family: inherit;
  font-size: 0.76rem;
  font-weight: 600;
  letter-spacing: 0.02em;
  padding: 8px 14px;
  border-radius: 999px;
  border: 1px solid #c7d2fe;
  background: linear-gradient(165deg, rgb(238 242 255), rgb(224 231 255));
  color: #4338ca;
  cursor: pointer;
  box-shadow:
    0 1px 3px rgb(15 23 42 / 8%),
    0 1px 2px rgb(15 23 42 / 6%);
  transition:
    background 0.15s ease,
    border-color 0.15s ease,
    transform 0.1s ease,
    right 0.38s cubic-bezier(0.22, 1.28, 0.36, 1);
}

.sticky-board--toolbox-open .sticky-board__save-btn {
  right: calc(min(292px, 92vw) + 16px + env(safe-area-inset-right, 0px));
}

.sticky-board__save-btn:hover {
  background: linear-gradient(165deg, rgb(224 231 255), rgb(199 210 254));
  border-color: #a5b4fc;
}

.sticky-board__save-btn:active {
  transform: scale(0.97);
}

.sticky-board__save-btn:focus {
  outline: none;
}

.sticky-board__save-btn:focus-visible {
  outline: 2px solid #818cf8;
  outline-offset: 2px;
}

.sticky-board__title {
  margin: 0 0 8px;
  font-size: 1.15rem;
  font-weight: 600;
  color: #0f172a;
}

.sticky-board__hint {
  margin: 0;
  font-size: 0.82rem;
  line-height: 1.45;
  color: #64748b;
}

.sticky-board__cta-dashed {
  width: 100%;
  padding: 14px 16px;
  border: 2px dashed #c7cad4;
  border-radius: 12px;
  background: rgb(99 102 241 / 4%);
  font-family: inherit;
  font-size: 0.88rem;
  font-weight: 600;
  color: #4f46e5;
  cursor: pointer;
  transition:
    border-color 0.15s ease,
    background 0.15s ease,
    color 0.15s ease,
    transform 0.1s ease;
}

.sticky-board__cta-dashed:hover {
  border-color: #818cf8;
  background: rgb(99 102 241 / 9%);
  color: #4338ca;
}

.sticky-board__cta-dashed:active {
  transform: scale(0.99);
}

.sticky-board__field {
  margin-bottom: 12px;
}

.sticky-board__field:last-of-type {
  margin-bottom: 0;
}

.sticky-board__label {
  display: block;
  font-size: 0.78rem;
  font-weight: 500;
  color: #64748b;
  margin-bottom: 6px;
}

.sticky-board__input,
.sticky-board__textarea {
  width: 100%;
  box-sizing: border-box;
  border: 1px solid #e2e5eb;
  border-radius: 8px;
  padding: 8px 10px;
  font-family: inherit;
  font-size: 0.88rem;
  color: #1e293b;
  background: #fafbfc;
}

.sticky-board__input:focus,
.sticky-board__textarea:focus,
.sticky-board__select:focus {
  outline: 2px solid #c7d2fe;
  outline-offset: 0;
  border-color: #a5b4fc;
}

.sticky-board__select {
  width: 100%;
  box-sizing: border-box;
  border: 1px solid #e2e5eb;
  border-radius: 8px;
  padding: 8px 10px;
  font-family: inherit;
  font-size: 0.88rem;
  font-weight: 500;
  color: #1e293b;
  background: #fafbfc;
  cursor: pointer;
}

.sticky-board__textarea {
  resize: vertical;
  min-height: 62px;
  line-height: 1.45;
}

.sticky-board__form-error {
  margin: 8px 0 0;
  font-size: 0.78rem;
  color: #dc2626;
}

.sticky-board__list-label {
  margin: 0;
  font-size: 0.78rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: #94a3b8;
}

.sticky-board__list-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin: 6px 0 12px;
}

.sticky-board__list-action-btn {
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

.sticky-board__list-action-btn:hover:not(:disabled) {
  background: #f1f5f9;
  border-color: #cbd5e1;
}

.sticky-board__list-action-btn--primary {
  border-color: #c7d2fe;
  background: #eef2ff;
  color: #4338ca;
}

.sticky-board__list-action-btn--primary:hover:not(:disabled) {
  background: #e0e7ff;
  border-color: #a5b4fc;
}

.sticky-board__list-action-btn:focus {
  outline: none;
}

.sticky-board__list-action-btn:focus-visible {
  outline: 2px solid #818cf8;
  outline-offset: 2px;
}

.sticky-board__list-action-btn:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

.sticky-board__dept-groups {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.sticky-board__dept {
  margin: 0;
}

.sticky-board__dept-title {
  display: flex;
  flex-direction: column;
  gap: 2px;
  margin: 0 0 8px 2px;
  padding: 0;
  font: inherit;
}

.sticky-board__dept-name {
  font-size: 0.72rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.07em;
  color: #475569;
}

.sticky-board__dept-hint {
  font-size: 0.68rem;
  font-weight: 500;
  letter-spacing: 0.01em;
  color: #94a3b8;
  line-height: 1.3;
}

.sticky-board__list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.sticky-board__chip {
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

.sticky-board__chip--on-board {
  cursor: pointer;
  background: rgb(99 102 241 / 6%);
  border-color: #c7d2fe;
}

.sticky-board__chip--active {
  border-color: #6366f1;
  box-shadow:
    0 0 0 2px rgb(99 102 241 / 22%),
    0 4px 14px rgb(99 102 241 / 14%);
  background: rgb(99 102 241 / 10%);
}

.sticky-board__chip:hover {
  border-color: #c7d2fe;
  box-shadow: 0 4px 14px rgb(99 102 241 / 12%);
}

.sticky-board__chip--active:hover {
  border-color: #4f46e5;
  box-shadow:
    0 0 0 2px rgb(99 102 241 / 28%),
    0 4px 14px rgb(99 102 241 / 18%);
}

.sticky-board__chip:active {
  cursor: grabbing;
  transform: scale(0.98);
}

.sticky-board__chip--on-board:active {
  cursor: pointer;
  transform: scale(0.99);
}

.sticky-board__swatch {
  width: 14px;
  height: 14px;
  border-radius: 4px;
  border: 1px solid rgb(15 23 42 / 12%);
  flex-shrink: 0;
}

.sticky-board__chip-text {
  display: flex;
  flex-direction: row;
  align-items: center;
  gap: 10px;
  min-width: 0;
  flex: 1;
  align-self: stretch;
}

.sticky-board__chip-name {
  font-weight: 600;
  font-size: 0.9rem;
  min-width: 0;
  flex: 1;
  text-align: left;
}

.sticky-board__chip-meter {
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

.sticky-board__chip-donut {
  flex-shrink: 0;
}

.sticky-board__canvas-wrap {
  position: relative;
  width: 100%;
  height: 100%;
  min-width: 0;
}

.sticky-board__flow {
  width: 100%;
  height: 100%;
  background: #eef0f4;
}

.sticky-board__flow :deep(.vue-flow__background) {
  background-color: #eef0f4;
}

.sticky-board__modal-root {
  position: fixed;
  inset: 0;
  z-index: 10050;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px;
  box-sizing: border-box;
}

.sticky-board__modal-backdrop {
  position: absolute;
  inset: 0;
  background: rgb(15 23 42 / 45%);
  backdrop-filter: blur(3px);
}

.sticky-board__modal-dialog {
  position: relative;
  width: 100%;
  max-width: 440px;
  max-height: min(90vh, 640px);
  overflow: hidden;
  display: flex;
  flex-direction: column;
  border-radius: 16px;
  background: #ffffff;
  box-shadow:
    0 24px 48px -12px rgb(15 23 42 / 35%),
    0 0 0 1px rgb(15 23 42 / 8%);
  font-family: 'Poppins', system-ui, sans-serif;
}

.sticky-board__modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 18px 20px 12px;
  border-bottom: 1px solid #eef0f4;
}

.sticky-board__modal-title {
  margin: 0;
  font-size: 1.05rem;
  font-weight: 600;
  color: #0f172a;
}

.sticky-board__modal-close {
  flex-shrink: 0;
  width: 36px;
  height: 36px;
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

.sticky-board__modal-close:hover {
  background: #f1f5f9;
  color: #0f172a;
}

.sticky-board__modal-body {
  padding: 16px 20px 8px;
  overflow-y: auto;
}

.sticky-board__modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  flex-wrap: wrap;
  padding: 14px 20px 18px;
  border-top: 1px solid #eef0f4;
  background: #fafbfc;
}

.sticky-board__modal-btn-secondary {
  padding: 10px 16px;
  border-radius: 10px;
  border: 1px solid #e2e5eb;
  background: #fff;
  font-family: inherit;
  font-size: 0.88rem;
  font-weight: 500;
  color: #475569;
  cursor: pointer;
}

.sticky-board__modal-btn-secondary:hover {
  border-color: #cbd5e1;
  background: #f8fafc;
}

.sticky-board__modal-btn-primary {
  padding: 10px 18px;
  border: none;
  border-radius: 10px;
  font-family: inherit;
  font-size: 0.88rem;
  font-weight: 600;
  color: #fff;
  background: linear-gradient(135deg, #6366f1, #4f46e5);
  cursor: pointer;
  transition:
    filter 0.15s ease,
    transform 0.1s ease;
}

.sticky-board__modal-btn-primary:hover {
  filter: brightness(1.06);
}

.sticky-board__modal-btn-primary:active {
  transform: scale(0.99);
}
</style>
