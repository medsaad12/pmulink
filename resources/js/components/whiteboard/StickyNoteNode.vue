<script setup>
import { ref, computed, watch, nextTick, inject, unref } from 'vue'
import { useVueFlow } from '@vue-flow/core'
import { X, Pencil } from 'lucide-vue-next'
import {
  STICKY_TITLE_BASELINE_SCROLL_PX,
  STICKY_EDIT_LINE_BASELINE_SCROLL_PX,
  stickyDimensionsFromLists,
  normalizeProjectStatus,
  stickyNotePaperColorFromStatus,
  stickyNoteFaceGradientFromStatus,
} from '@/lib/whiteboardProjects.js'
const MIN_W = 200
const MIN_H = 168
const MAX_W = 720
const MAX_H = 560

const props = defineProps({
  id: { type: String, required: true },
  data: { type: Object, required: true },
  selected: { type: Boolean, default: false },
})

const {
  updateNodeData,
  updateNode,
  updateNodeInternals,
  findNode,
  getViewport,
  removeNodes,
} = useVueFlow()

const dim0 = stickyDimensionsFromLists([], [])

const syncStickyProjectMeta = inject('syncStickyProjectMeta', () => {})
const persistStickyLayout = inject('persistStickyLayout', () => {})
const stickyBoardRequestEdit = inject('stickyBoardRequestEdit', () => {})
/** Faux pendant la vue tableau : évite scrollHeight / largeur nuls sur le titre. */
const stickyBoardVisible = inject('stickyBoardVisible', null)

function patchProjectMeta(patch) {
  if (props.data.readOnly) return
  updateNodeData(props.id, patch)
  const pid = props.data.projectId
  if (pid) syncStickyProjectMeta(pid, patch)
}

const STATUS_LABELS = {
  bon: 'En bonne voie',
  vigilance: 'Sous vigilance',
  critique: 'Critique / À risque',
  cloture: 'Clôturé',
  archivee: 'Archivé',
}

const currentStatus = computed(() => normalizeProjectStatus(props.data.projectStatus))

const stickyPaperColor = computed(() => stickyNotePaperColorFromStatus(props.data.projectStatus))

const stickyFaceGradient = computed(() =>
  stickyNoteFaceGradientFromStatus(props.data.projectStatus),
)

const currentStatusLabel = computed(() => STATUS_LABELS[currentStatus.value] ?? 'Sous vigilance')

const titleFieldEl = ref(null)
const editFieldsRootEl = ref(null)

function autoResizeTitleField() {
  const el = titleFieldEl.value
  if (!(el instanceof HTMLTextAreaElement)) return
  el.style.height = 'auto'
  el.style.height = `${Math.max(STICKY_TITLE_BASELINE_SCROLL_PX, el.scrollHeight)}px`
}

/** Surplus de hauteur du titre au-delà d’une ligne (pour agrandir le post-it auto). */
function titleExtraScrollPx() {
  const el = titleFieldEl.value
  if (!(el instanceof HTMLTextAreaElement)) return 0
  return Math.max(0, Math.round(el.scrollHeight - STICKY_TITLE_BASELINE_SCROLL_PX))
}

function autoResizeEditLineField(el) {
  if (!(el instanceof HTMLTextAreaElement)) return
  el.style.height = 'auto'
  el.style.height = `${Math.max(STICKY_EDIT_LINE_BASELINE_SCROLL_PX, el.scrollHeight)}px`
}

/** Surplus de hauteur des champs liste en édition (retours visuels à la ligne). */
function editListWrapExtraPx() {
  if (!editing.value) return 0
  const root = editFieldsRootEl.value
  if (!(root instanceof HTMLElement)) return 0
  let sum = 0
  for (const el of root.querySelectorAll('.sticky-note__edit-input')) {
    if (!(el instanceof HTMLTextAreaElement)) continue
    autoResizeEditLineField(el)
    sum += Math.max(0, Math.round(el.scrollHeight - STICKY_EDIT_LINE_BASELINE_SCROLL_PX))
  }
  return sum
}

watch(
  () => props.data.projectName,
  () => nextTick(autoResizeTitleField),
  { immediate: true },
)

function setListView(mode) {
  updateNodeData(props.id, { stickyView: mode })
}

const viewMode = computed(() =>
  props.data.stickyView === 'commentaires' ? 'commentaires' : 'faits',
)

const lines = computed(() => {
  const raw =
    viewMode.value === 'commentaires'
      ? props.data.commentaires
      : props.data.faitsMarquants
  const arr = Array.isArray(raw) ? raw : []
  return arr.map((s) => String(s ?? '').trim()).filter(Boolean)
})

const editing = ref(false)
const draftFaits = ref([''])
const draftComments = ref([''])

function cloneLines(raw) {
  const arr = Array.isArray(raw) ? raw.map((s) => String(s ?? '')) : []
  return arr.length ? [...arr] : ['']
}

function startEdit() {
  if (props.data.readOnly) return
  const pid = props.data.projectId
  if (pid == null) return
  stickyBoardRequestEdit(String(pid))
}

function cancelDraft() {
  editing.value = false
}

function onEditLineInput(ev) {
  const el = ev.target
  if (el instanceof HTMLTextAreaElement) autoResizeEditLineField(el)
  void syncAutoStickyListDimensions()
}

function saveDraft() {
  const faits = draftFaits.value.map((s) => String(s).trim()).filter(Boolean)
  const comm = draftComments.value.map((s) => String(s).trim()).filter(Boolean)
  patchProjectMeta({
    faitsMarquants: faits,
    commentaires: comm,
  })
  editing.value = false
  nextTick(() => updateNodeInternals([props.id]))
}

function removeDraftLine(which, index) {
  const arr = which === 'commentaires' ? draftComments : draftFaits
  if (arr.value.length <= 1) {
    arr.value[0] = ''
    return
  }
  arr.value.splice(index, 1)
}

function stickyBoardIsActiveForLayout() {
  if (stickyBoardVisible == null) return true
  return unref(stickyBoardVisible) !== false
}

async function syncAutoStickyListDimensions() {
  if (!stickyBoardIsActiveForLayout()) return

  await nextTick()
  const el0 = titleFieldEl.value
  if (el0 instanceof HTMLElement && el0.offsetWidth < 8) {
    await new Promise((r) => {
      requestAnimationFrame(r)
    })
    await nextTick()
  }

  autoResizeTitleField()
  await nextTick()
  if (props.data.userSizedNote && !editing.value) return

  const node = findNode(props.id)
  if (!node) return

  const titleExtra = titleExtraScrollPx()
  const faitsLines = editing.value ? draftFaits.value : props.data.faitsMarquants
  const commLines = editing.value ? draftComments.value : props.data.commentaires
  const editWrapExtra = editing.value ? editListWrapExtraPx() : 0
  const { width, height } = stickyDimensionsFromLists(
    faitsLines,
    commLines,
    titleExtra,
    editWrapExtra,
  )
  const curW = typeof node.width === 'number' && node.width > 0 ? node.width : width
  const curH = typeof node.height === 'number' && node.height > 0 ? node.height : height
  if (props.data.userSizedNote) {
    if (!editing.value) return
    const nextHeight = Math.max(curH, height)
    if (Math.abs(curH - nextHeight) < 1) return
    updateNode(props.id, { height: nextHeight })
    nextTick(() => {
      updateNodeInternals([props.id])
      const n2 = findNode(props.id)
      const pid = props.data.projectId
      if (n2 && pid) {
        const { width: ew, height: eh } = pixelSize(n2)
        persistStickyLayout(pid, {
          x: n2.position.x,
          y: n2.position.y,
          width: ew,
          height: eh,
        })
      }
    })
    return
  }
  if (Math.abs(curW - width) < 1 && Math.abs(curH - height) < 1) return
  updateNode(props.id, { width, height })
  nextTick(() => {
    updateNodeInternals([props.id])
    const n2 = findNode(props.id)
    const pid = props.data.projectId
    if (n2 && pid) {
      const { width: ew, height: eh } = pixelSize(n2)
      persistStickyLayout(pid, {
        x: n2.position.x,
        y: n2.position.y,
        width: ew,
        height: eh,
      })
    }
  })
}

watch(
  () => [
    props.data.faitsMarquants,
    props.data.commentaires,
    props.data.userSizedNote,
    props.data.projectName,
    props.data.stickyView,
  ],
  () => {
    void syncAutoStickyListDimensions()
  },
  { deep: true, immediate: true },
)

watch(editing, () => {
  void syncAutoStickyListDimensions()
})

watch(
  draftFaits,
  () => {
    if (editing.value) void syncAutoStickyListDimensions()
  },
  { deep: true },
)

watch(
  draftComments,
  () => {
    if (editing.value) void syncAutoStickyListDimensions()
  },
  { deep: true },
)

watch(
  () => (stickyBoardVisible == null ? true : unref(stickyBoardVisible)),
  (active) => {
    if (active) {
      void nextTick(() => {
        void syncAutoStickyListDimensions()
      })
    }
  },
)

function pixelSize(node) {
  const w =
    typeof node.width === 'number' && node.width > 0
      ? node.width
      : node.dimensions.width > 0
        ? node.dimensions.width
        : dim0.width
  const h =
    typeof node.height === 'number' && node.height > 0
      ? node.height
      : node.dimensions.height > 0
        ? node.dimensions.height
        : dim0.height
  return { width: w, height: h }
}

function onResizePointerDown(e) {
  const el = e.currentTarget
  if (!(el instanceof HTMLElement)) return

  e.stopPropagation()
  e.preventDefault()

  const node = findNode(props.id)
  if (!node) return

  const zoom = getViewport().zoom || 1
  const { width: startW, height: startH } = pixelSize(node)
  const startX = e.clientX
  const startY = e.clientY

  el.setPointerCapture(e.pointerId)

  function onMove(ev) {
    const dw = (ev.clientX - startX) / zoom
    const dh = (ev.clientY - startY) / zoom
    const nw = Math.min(MAX_W, Math.max(MIN_W, Math.round(startW + dw)))
    const nh = Math.min(MAX_H, Math.max(MIN_H, Math.round(startH + dh)))
    updateNode(props.id, { width: nw, height: nh })
    updateNodeInternals([props.id])
  }

  function onUp(ev) {
    el.releasePointerCapture(ev.pointerId)
    window.removeEventListener('pointermove', onMove)
    window.removeEventListener('pointerup', onUp)
    window.removeEventListener('pointercancel', onUp)
    const n = findNode(props.id)
    if (!n) return
    const { width: endW, height: endH } = pixelSize(n)
    if (Math.round(endW) !== Math.round(startW) || Math.round(endH) !== Math.round(startH)) {
      updateNodeData(props.id, { userSizedNote: true })
    }
    const pid = props.data.projectId
    if (pid && persistStickyLayout) {
      persistStickyLayout(pid, {
        x: n.position.x,
        y: n.position.y,
        width: endW,
        height: endH,
      })
    }
  }

  window.addEventListener('pointermove', onMove)
  window.addEventListener('pointerup', onUp)
  window.addEventListener('pointercancel', onUp)
}

function onCloseClick(e) {
  e.preventDefault()
  e.stopPropagation()
  removeNodes([props.id])
}
</script>

<template>
  <div
    class="sticky-note-shell"
    :style="{ '--sticky-bg': stickyPaperColor, '--sticky-face': stickyFaceGradient }"
  >
    <button
      type="button"
      class="sticky-note__close nodrag"
      aria-label="Retirer du tableau"
      title="Retirer du tableau"
      @pointerdown.stop
      @click.stop="onCloseClick"
    >
      <span class="sticky-note__close-ring" aria-hidden="true" />
      <X class="sticky-note__close-icon" :size="10" :stroke-width="2" aria-hidden="true" />
    </button>
    <div
      class="sticky-note"
      :class="{
        'sticky-note--selected': selected,
        'sticky-note--empty': !editing && !lines.length,
        'sticky-note--editing': editing,
      }"
    >
      <div class="sticky-note__accent">
        <span class="sticky-note__drag" title="Glisser pour déplacer">⋮⋮</span>
      </div>
      <div class="sticky-note__body">
        <div class="sticky-note__title-row">
          <textarea
            ref="titleFieldEl"
            class="sticky-note__title"
            rows="1"
            readonly
            spellcheck="false"
            :value="data.projectName || ''"
            placeholder="Sujet"
            aria-label="Sujet"
            :class="{ 'pointer-events-none opacity-90': data.readOnly }"
            @click="startEdit"
          />
          <button
            v-if="!data.readOnly"
            type="button"
            class="sticky-note__quick-edit"
            title="Modifier"
            aria-label="Modifier"
            @click="startEdit"
          >
            <Pencil :size="13" :stroke-width="2.25" aria-hidden="true" />
          </button>
        </div>
        <div class="sticky-note__toolbar nodrag">
          <div class="sticky-note__switch" role="tablist" aria-label="Contenu du post-it">
            <button
              type="button"
              class="sticky-note__switch-btn"
              role="tab"
              :aria-selected="viewMode === 'faits'"
              @click="setListView('faits')"
            >
              Prochaines étapes
            </button>
            <button
              type="button"
              class="sticky-note__switch-btn"
              role="tab"
              :aria-selected="viewMode === 'commentaires'"
              @click="setListView('commentaires')"
            >
              Commentaires
            </button>
          </div>
        </div>

        <template v-if="!editing">
          <ol
            v-if="lines.length"
            class="sticky-note__list nodrag nowheel"
            :aria-label="
              viewMode === 'faits' ? 'Prochaines étapes' : 'Commentaires'
            "
          >
            <li v-for="(line, i) in lines" :key="i" class="sticky-note__list-item">
              {{ line }}
            </li>
          </ol>
          <p v-else class="sticky-note__empty nodrag">Aucune entrée.</p>
        </template>

        <div v-else ref="editFieldsRootEl" class="sticky-note__edit nodrag">
          <ol
            v-if="viewMode === 'faits'"
            class="sticky-note__edit-list nowheel"
            aria-label="Édition prochaines étapes"
          >
            <li v-for="(_line, i) in draftFaits" :key="'f-' + i" class="sticky-note__edit-row">
              <span class="sticky-note__edit-num" aria-hidden="true">{{ i + 1 }}.</span>
              <textarea
                v-model="draftFaits[i]"
                class="sticky-note__edit-input"
                rows="1"
                spellcheck="false"
                :placeholder="'Étape ' + (i + 1)"
                @input="onEditLineInput"
              />
              <button
                type="button"
                class="sticky-note__edit-remove"
                title="Supprimer la ligne"
                aria-label="Supprimer la ligne"
                @click="removeDraftLine('faits', i)"
              >
                ×
              </button>
            </li>
          </ol>
          <ol v-else class="sticky-note__edit-list nowheel" aria-label="Édition commentaires">
            <li v-for="(_line, i) in draftComments" :key="'c-' + i" class="sticky-note__edit-row">
              <span class="sticky-note__edit-num" aria-hidden="true">{{ i + 1 }}.</span>
              <textarea
                v-model="draftComments[i]"
                class="sticky-note__edit-input"
                rows="1"
                spellcheck="false"
                :placeholder="'Commentaire ' + (i + 1)"
                @input="onEditLineInput"
              />
              <button
                type="button"
                class="sticky-note__edit-remove"
                title="Supprimer la ligne"
                aria-label="Supprimer la ligne"
                @click="removeDraftLine('commentaires', i)"
              >
                ×
              </button>
            </li>
          </ol>
        </div>

        <div class="sticky-note__footer nodrag nowheel">

          <div class="sticky-note__metrics">
            <div class="sticky-note__metric sticky-note__metric--statut">
              <span class="sticky-note__metric-label">Statut</span>
              <div
                class="sticky-note__status-pill"
                :class="`sticky-note__status-pill--${currentStatus}`"
                role="status"
                :aria-label="`Statut ${currentStatusLabel}`"
              >
                <span class="sticky-note__status-pill-label">{{ currentStatusLabel }}</span>
              </div>
            </div>
          </div>

        </div>
      </div>
      <button
        type="button"
        class="sticky-note__resize nodrag"
        aria-label="Redimensionner le post-it"
        title="Redimensionner"
        tabindex="-1"
        @pointerdown="onResizePointerDown"
      />
    </div>
  </div>
</template>

<style scoped>
.sticky-note-shell {
  --sticky-ink: color-mix(in srgb, var(--sticky-bg) 24%, #0f172a);
  --sticky-ink-soft: color-mix(in srgb, var(--sticky-bg) 42%, #475569);
  --sticky-surface: color-mix(in srgb, var(--sticky-bg) 30%, white);
  --sticky-surface-strong: color-mix(in srgb, var(--sticky-bg) 52%, white);
  --sticky-edge: color-mix(in srgb, var(--sticky-bg) 58%, rgb(15 23 42 / 16%));
  --sticky-marker: color-mix(in srgb, var(--sticky-bg) 36%, #334155);
  --sticky-scroll-track: color-mix(in srgb, var(--sticky-bg) 16%, white);
  --sticky-scroll-thumb: color-mix(in srgb, var(--sticky-bg) 58%, #64748b);
  --sticky-progress: color-mix(in srgb, var(--sticky-bg) 50%, #1e293b);
  --sticky-progress-deep: color-mix(in srgb, var(--sticky-bg) 64%, #0f172a);
  --sticky-progress-track: color-mix(in srgb, var(--sticky-bg) 26%, rgb(15 23 42 / 12%));
  --sticky-progress-label: color-mix(in srgb, var(--sticky-bg) 30%, #334155);
  --sticky-donut-face: color-mix(in srgb, var(--sticky-progress-deep) 82%, var(--sticky-progress));
  --sticky-donut-track: color-mix(in srgb, var(--sticky-progress) 40%, #020617);
  --sticky-donut-arc: color-mix(in srgb, #ffffff 88%, var(--sticky-bg));
  --sticky-donut-label: color-mix(in srgb, #ffffff 93%, var(--sticky-bg));
  --sticky-panel: color-mix(in srgb, var(--sticky-bg) 14%, white);
  --sticky-control-border: color-mix(in srgb, var(--sticky-edge) 72%, transparent);
  /* High-contrast labels/readout for tinted post-it backgrounds */
  --sticky-metric-cap: rgb(255 255 255 / 96%);
  --sticky-metric-readout: rgb(255 255 255 / 98%);

  position: relative;
  width: 100%;
  height: 100%;
  overflow: visible;
}

.sticky-note {
  position: relative;
  width: 100%;
  height: 100%;
  display: flex;
  container-type: inline-size;
  cursor: grab;
  border-radius: 12px;
  box-shadow:
    0 5px 10px -3px rgb(15 23 42 / 14%),
    0 14px 28px -10px rgb(15 23 42 / 22%);
  font-family: 'Poppins', system-ui, sans-serif;
  overflow: hidden;
  border: 1px solid var(--sticky-edge);
  background: var(--sticky-face);
  background-color: var(--sticky-bg);
}

.sticky-note--status-menu-open {
  overflow: visible;
  z-index: 25;
}

.sticky-note__close {
  position: absolute;
  top: -6px;
  right: -6px;
  z-index: 4;
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
  background: linear-gradient(
    155deg,
    rgb(255 255 255 / 94%),
    color-mix(in srgb, var(--sticky-bg) 22%, white)
  );
  box-shadow:
    inset 0 1px 0 rgb(255 255 255 / 85%),
    0 0 0 1px color-mix(in srgb, var(--sticky-edge) 55%, rgb(255 255 255 / 40%)),
    0 2px 4px rgb(15 23 42 / 8%),
    0 6px 14px rgb(15 23 42 / 12%);
  backdrop-filter: blur(8px);
  -webkit-backdrop-filter: blur(8px);
  transition:
    color 0.2s ease,
    box-shadow 0.2s ease,
    transform 0.14s ease,
    filter 0.2s ease;
}

.sticky-note__close-ring {
  position: absolute;
  inset: 1px;
  border-radius: inherit;
  border: 1px solid rgb(255 255 255 / 55%);
  pointer-events: none;
  opacity: 0.85;
}

.sticky-note__close-icon {
  position: relative;
  z-index: 1;
  display: block;
}

.sticky-note__close:hover {
  color: #dc2626;
  filter: saturate(1.05);
  box-shadow:
    inset 0 1px 0 rgb(255 255 255 / 92%),
    0 0 0 1px color-mix(in srgb, #fecaca 55%, var(--sticky-edge)),
    0 2px 6px rgb(220 38 38 / 14%),
    0 8px 18px rgb(15 23 42 / 14%);
}

.sticky-note__close:active {
  transform: scale(0.94);
  filter: saturate(1.1);
}

.sticky-note__close:focus-visible {
  outline: 2px solid color-mix(in srgb, var(--sticky-bg) 22%, #6366f1);
  outline-offset: 2px;
}

.sticky-note--selected {
  outline: 2px solid color-mix(in srgb, var(--sticky-bg) 28%, #6366f1);
  outline-offset: 2px;
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

.sticky-note__body {
  flex: 1;
  padding: 10px 10px 10px 9px;
  display: flex;
  flex-direction: column;
  gap: 8px;
  min-width: 0;
  min-height: 0;
  overflow: hidden;
}

.sticky-note__sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}

.sticky-note__title-row {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  flex-shrink: 0;
  min-width: 0;
  padding-right: 4px;
}

.sticky-note__quick-edit {
  flex-shrink: 0;
  width: 24px;
  height: 24px;
  padding: 0;
  display: grid;
  place-items: center;
  border-radius: 7px;
  border: 1px solid var(--sticky-control-border);
  background: color-mix(in srgb, var(--sticky-bg) 16%, white);
  color: var(--sticky-ink-soft);
  cursor: pointer;
  transition: background 0.15s ease, color 0.15s ease, border-color 0.15s ease;
}

.sticky-note__quick-edit:hover {
  color: var(--sticky-ink);
  background: color-mix(in srgb, var(--sticky-bg) 28%, white);
}

.sticky-note__title {
  border: 1px solid var(--sticky-control-border);
  background: var(--sticky-panel);
  border-radius: 8px;
  padding: 5px 7px;
  line-height: 1.35;
  font-family: inherit;
  font-size: clamp(10px, calc(7px + 1.2cqi), 12.5px);
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

.sticky-note__title-row .sticky-note__title {
  width: auto;
}

.sticky-note__title-donut {
  flex-shrink: 0;
}

.sticky-note__accent-donut {
  flex-shrink: 0;
}

.sticky-note__title::placeholder {
  color: color-mix(in srgb, var(--sticky-ink) 42%, transparent);
}

.sticky-note__title:focus {
  outline: 2px solid color-mix(in srgb, var(--sticky-bg) 30%, #6366f1);
  outline-offset: 0;
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

.sticky-note__edit-toggle {
  flex-shrink: 0;
  width: 24px;
  height: 24px;
  padding: 0;
  display: grid;
  place-items: center;
  border-radius: 7px;
  border: 1px solid var(--sticky-control-border);
  background: color-mix(in srgb, var(--sticky-bg) 16%, white);
  color: var(--sticky-ink-soft);
  font-size: 9.5px;
  font-weight: 600;
  font-family: inherit;
  cursor: pointer;
  transition:
    background 0.15s ease,
    color 0.15s ease,
    border-color 0.15s ease;
}

.sticky-note__edit-toggle:hover {
  color: var(--sticky-ink);
  background: color-mix(in srgb, var(--sticky-bg) 28%, white);
}

.sticky-note__edit-actions-top {
  display: flex;
  flex-shrink: 0;
  align-items: center;
  gap: 4px;
}

.sticky-note__icon-btn {
  width: 24px;
  height: 24px;
  padding: 0;
  border-radius: 6px;
  border: 1px solid var(--sticky-control-border);
  display: grid;
  place-items: center;
  cursor: pointer;
  transition: transform 0.12s ease, filter 0.12s ease, background 0.12s ease;
}

.sticky-note__icon-btn:hover {
  transform: translateY(-0.5px);
}

.sticky-note__icon-btn:active {
  transform: translateY(0);
}

.sticky-note__icon-btn--save {
  color: #14532d;
  background: color-mix(in srgb, rgb(187 247 208) 74%, white);
  border-color: color-mix(in srgb, rgb(22 163 74) 34%, var(--sticky-edge));
}

.sticky-note__icon-btn--cancel {
  color: #475569;
  background: color-mix(in srgb, rgb(226 232 240) 82%, white);
  border-color: color-mix(in srgb, rgb(100 116 139) 35%, var(--sticky-edge));
}

.sticky-note__switch-btn {
  flex: 1;
  margin: 0;
  border: 1px solid transparent;
  padding: 4px 4px;
  font-size: clamp(7.6px, calc(6px + 0.78cqi), 8.75px);
  font-weight: 600;
  letter-spacing: 0.01em;
  border-radius: 6px;
  cursor: pointer;
  font-family: inherit;
  color: var(--sticky-ink-soft);
  background: transparent;
  line-height: 1.1;
  transition:
    background 0.16s ease,
    color 0.16s ease,
    border-color 0.16s ease,
    box-shadow 0.16s ease;
}

.sticky-note__switch-btn:hover {
  color: var(--sticky-ink);
  background: color-mix(in srgb, var(--sticky-bg) 14%, white);
}

.sticky-note__switch-btn[aria-selected='true'] {
  background: color-mix(in srgb, var(--sticky-bg) 68%, white);
  color: var(--sticky-ink);
  border-color: color-mix(in srgb, var(--sticky-edge) 85%, transparent);
  box-shadow:
    0 1px 3px rgb(15 23 42 / 10%),
    inset 0 1px 0 rgb(255 255 255 / 35%);
}

.sticky-note__switch-btn:focus-visible {
  outline: 2px solid color-mix(in srgb, var(--sticky-bg) 30%, #6366f1);
  outline-offset: 1px;
}

.sticky-note__edit {
  flex: 1 1 auto;
  display: flex;
  flex-direction: column;
  gap: 6px;
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
  grid-template-columns: 1.1rem 1fr 22px;
  gap: 6px;
  align-items: start;
  padding: 6px 8px;
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
  font-size: clamp(8.5px, calc(7px + 0.62cqi), 10px);
  font-weight: 700;
  color: var(--sticky-marker);
  text-align: right;
  line-height: 1.35;
  padding-top: 6px;
}

.sticky-note__edit-input {
  width: 100%;
  min-width: 0;
  box-sizing: border-box;
  border: 1px solid color-mix(in srgb, var(--sticky-edge) 55%, transparent);
  border-radius: 6px;
  padding: 5px 7px;
  font-family: inherit;
  font-size: clamp(9px, calc(7px + 0.95cqi), 11px);
  line-height: 1.35;
  color: var(--sticky-ink);
  background: color-mix(in srgb, var(--sticky-bg) 6%, white);
  resize: none;
  overflow-x: hidden;
  overflow-y: hidden;
  overflow-wrap: anywhere;
  word-break: break-word;
  min-height: calc(1.35em + 12px);
}

.sticky-note__edit-input::placeholder {
  color: color-mix(in srgb, var(--sticky-ink) 35%, transparent);
}

.sticky-note__edit-input:focus {
  outline: none;
  border-color: color-mix(in srgb, var(--sticky-bg) 35%, #6366f1);
  box-shadow: 0 0 0 2px color-mix(in srgb, var(--sticky-bg) 25%, rgb(99 102 241 / 22%));
}

.sticky-note__edit-remove {
  width: 22px;
  height: 22px;
  padding: 0;
  border: none;
  border-radius: 6px;
  background: transparent;
  color: var(--sticky-ink-soft);
  font-size: 1rem;
  line-height: 1;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-top: 4px;
}

.sticky-note__edit-remove:hover {
  color: #b91c1c;
  background: color-mix(in srgb, #fecaca 35%, transparent);
}

.sticky-note__list {
  --sticky-row-line: color-mix(in srgb, var(--sticky-edge) 75%, transparent);

  list-style: decimal;
  list-style-position: outside;
  margin: 0;
  padding: 0 0 0 1.5rem;
  border-radius: 8px;
  border: 1px solid color-mix(in srgb, var(--sticky-edge) 66%, transparent);
  background: color-mix(in srgb, var(--sticky-bg) 14%, white);
  flex: 1 1 auto;
  min-height: 0;
  overflow-x: hidden;
  overflow-y: auto;
}

.sticky-note__list-item {
  margin: 0;
  padding: 7px 9px 7px 0.3rem;
  font-size: clamp(9px, calc(7px + 0.9cqi), 10.5px);
  line-height: 1.4;
  color: var(--sticky-ink);
  white-space: pre-line;
  overflow-wrap: anywhere;
  word-break: break-word;
  border-bottom: 1px solid var(--sticky-row-line);
  background: color-mix(in srgb, var(--sticky-bg) 8%, white);
}

.sticky-note__list-item:nth-child(even) {
  background: color-mix(in srgb, var(--sticky-bg) 16%, white);
}

.sticky-note__list-item:last-child {
  border-bottom: none;
}

.sticky-note__list-item::marker {
  font-size: 0.95em;
  color: var(--sticky-marker);
  font-weight: 700;
}

.sticky-note__empty {
  margin: 0;
  flex: 1 1 auto;
  min-height: 2.5rem;
  font-size: clamp(9.5px, calc(7px + 1.03cqi), 11.25px);
  color: var(--sticky-ink-soft);
  font-style: italic;
  display: flex;
  align-items: center;
  justify-content: center;
  text-align: center;
  padding: 10px 8px;
  border-radius: 10px;
  border: 1px dashed color-mix(in srgb, var(--sticky-edge) 66%, transparent);
  background: color-mix(in srgb, var(--sticky-bg) 16%, white);
  box-sizing: border-box;
}

.sticky-note--empty .sticky-note__switch {
  margin-bottom: 2px;
}

.sticky-note__resize {
  position: absolute;
  right: 2px;
  bottom: 2px;
  width: 20px;
  height: 20px;
  padding: 0;
  margin: 0;
  border: none;
  cursor: nwse-resize;
  background: transparent;
  border-radius: 0 0 8px 0;
  z-index: 2;
  touch-action: none;
}

.sticky-note__resize::after {
  content: '';
  position: absolute;
  right: 4px;
  bottom: 4px;
  width: 10px;
  height: 10px;
  border-right: 2px solid color-mix(in srgb, var(--sticky-marker) 75%, rgb(15 23 42 / 25%));
  border-bottom: 2px solid color-mix(in srgb, var(--sticky-marker) 75%, rgb(15 23 42 / 25%));
}

.sticky-note__resize:hover::after,
.sticky-note--selected .sticky-note__resize::after {
  border-color: color-mix(in srgb, var(--sticky-marker) 55%, #6366f1);
}

/* Footer */
/*
 * Two-block structure:
 *   1. .sticky-note__metrics - Statut (read-only)
 *   2. .sticky-note__actions - ouvrir le formulaire d'édition
 *
 * The two blocks are visually separated by a dashed rule to communicate
 * "different intent": metrics are editable values, actions move the card
 * to a new lifecycle state.
 */

.sticky-note__footer {
  display: flex;
  flex-direction: column;
  gap: 6px;
  flex-shrink: 0;
  margin-top: 0;
  padding-top: 6px;
  border-top: 1px solid color-mix(in srgb, var(--sticky-edge) 50%, transparent);
}

.sticky-note__metrics {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  gap: 6px 8px;
}

.sticky-note__metric--statut      { grid-area: statut; }

/* Stat-block layout: micro-label on top, value below */
.sticky-note__metric {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}

.sticky-note__metric-label {
  font-size: clamp(5px, calc(4px + 0.46cqi), 5.6px);
  font-weight: 800;
  letter-spacing: 0.09em;
  text-transform: uppercase;
  color: var(--sticky-metric-cap);
  line-height: 1;
  text-shadow: 0 1px 1px rgb(2 6 23 / 38%);
}

/* Avancement header: label on the left, percentage readout on the right */
.sticky-note__metric-head {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 6px;
  min-width: 0;
}

.sticky-note__metric-readout {
  font-size: clamp(6px, calc(4px + 0.8cqi), 7.4px);
  font-weight: 800;
  letter-spacing: 0.01em;
  font-variant-numeric: tabular-nums;
  color: var(--sticky-metric-readout);
  line-height: 1;
  text-shadow: 0 1px 1px rgb(2 6 23 / 42%);
}

.sticky-note__status-pill {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  min-height: 22px;
  border-radius: 999px;
  border: 1px solid transparent;
  box-shadow: 0 1px 2px rgb(15 23 42 / 7%);
  padding: 2px 7px;
}

.sticky-note__status-pill-label {
  min-width: 0;
  text-align: center;
  line-height: 1.2;
  white-space: normal;
  font-size: clamp(5.4px, calc(4px + 0.72cqi), 6.4px);
  font-weight: 800;
  letter-spacing: 0.03em;
}

.sticky-note__status-pill--bon {
  background: linear-gradient(165deg, rgb(187 247 208), rgb(110 231 183));
  border-color: rgb(74 222 128 / 60%);
  color: rgb(6 78 59);
}

.sticky-note__status-pill--vigilance {
  background: linear-gradient(165deg, rgb(251 146 60), rgb(234 88 12));
  border-color: rgb(194 65 12 / 58%);
  color: white;
}

.sticky-note__status-pill--critique {
  background: linear-gradient(165deg, rgb(248 113 113), rgb(220 38 38));
  border-color: rgb(185 28 28 / 58%);
  color: white;
}

.sticky-note__status-pill--cloture {
  background: linear-gradient(165deg, rgb(52 211 153), rgb(22 163 74));
  border-color: rgb(21 128 61 / 55%);
  color: white;
}

.sticky-note__status-pill--archivee {
  background: linear-gradient(165deg, rgb(148 163 184), rgb(100 116 139));
  border-color: rgb(71 85 105 / 56%);
  color: white;
}

.sticky-note__progress {
  width: 100%;
  height: 10px;
  border-radius: 999px;
  background: rgb(255 255 255 / 92%);
  border: 1px solid rgb(255 255 255 / 75%);
  box-shadow:
    inset 0 1px 2px rgb(15 23 42 / 18%),
    0 1px 0 rgb(255 255 255 / 42%);
  overflow: hidden;
}

.sticky-note__progress-fill {
  display: block;
  height: 100%;
  border-radius: inherit;
  background: linear-gradient(
    90deg,
    color-mix(in srgb, var(--sticky-bg) 52%, white) 0%,
    var(--sticky-progress) 50%,
    var(--sticky-progress-deep) 100%
  );
  box-shadow: inset 0 1px 0 rgb(255 255 255 / 35%);
  transition: width 0.2s ease;
}

/* Actions block: separated by dashed rule from metrics */
.sticky-note__actions {
  display: grid;
  grid-template-columns: 1fr;
  gap: 4px;
  padding-top: 5px;
  border-top: 1px dashed color-mix(in srgb, var(--sticky-edge) 45%, transparent);
}

.sticky-note__action-btn {
  border: 1px solid var(--sticky-control-border);
  border-radius: 5px;
  padding: 3px 5px;
  font-size: clamp(6px, calc(4.5px + 0.78cqi), 7px);
  font-weight: 700;
  font-family: inherit;
  cursor: pointer;
  transition: transform 0.12s ease, filter 0.12s ease;
}

.sticky-note__action-btn:hover {
  transform: translateY(-0.5px);
  filter: brightness(0.97);
}

.sticky-note__action-btn:active {
  transform: translateY(0);
}

.sticky-note__action-btn--edit {
  color: #1f2937;
  background: color-mix(in srgb, rgb(229 231 235) 75%, white);
  border-color: color-mix(in srgb, rgb(107 114 128) 40%, var(--sticky-edge));
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
  gap: 3px;
  width: 100%;
  cursor: pointer;
  font-family: inherit;
  font-size: clamp(5.4px, calc(4px + 0.72cqi), 6.4px);
  font-weight: 800;
  letter-spacing: 0.03em;
  line-height: 1.15;
  padding: 2px 4px 2px 6px;
  border-radius: 999px;
  border: 1px solid transparent;
  background-clip: padding-box;
  box-shadow: 0 1px 2px rgb(15 23 42 / 7%);
  transition:
    background 0.14s ease,
    border-color 0.14s ease,
    color 0.14s ease,
    box-shadow 0.14s ease,
    transform 0.12s ease;
}

.sticky-note__status-trigger-label {
  min-width: 0;
  text-align: left;
  line-height: 1.2;
  white-space: normal;
  max-width: 5.8rem;
}

.sticky-note__status-chevron {
  flex-shrink: 0;
  opacity: 0.88;
  transition: transform 0.2s ease;
}

.sticky-note__status-dropdown--open .sticky-note__status-chevron {
  transform: rotate(180deg);
}

.sticky-note__status-trigger:hover {
  transform: translateY(-0.5px);
}

.sticky-note__status-trigger:active {
  transform: translateY(0);
}

.sticky-note__status-trigger:focus {
  outline: none;
}

.sticky-note__status-trigger:focus-visible {
  outline: 2px solid color-mix(in srgb, var(--sticky-bg) 40%, var(--sticky-ink));
  outline-offset: 2px;
}

.sticky-note__status-trigger--bon {
  background: linear-gradient(165deg, rgb(187 247 208), rgb(110 231 183));
  border-color: rgb(74 222 128 / 60%);
  color: rgb(6 78 59);
  box-shadow:
    0 0 0 1px rgb(255 255 255 / 55%) inset,
    0 2px 8px rgb(22 163 74 / 20%);
}

.sticky-note__status-trigger--vigilance {
  background: linear-gradient(165deg, rgb(251 146 60), rgb(234 88 12));
  border-color: rgb(194 65 12 / 58%);
  color: white;
  box-shadow:
    0 0 0 1px rgb(255 255 255 / 32%) inset,
    0 2px 8px rgb(234 88 12 / 35%);
}

.sticky-note__status-trigger--critique {
  background: linear-gradient(165deg, rgb(248 113 113), rgb(220 38 38));
  border-color: rgb(185 28 28 / 58%);
  color: white;
  box-shadow:
    0 0 0 1px rgb(255 255 255 / 32%) inset,
    0 2px 8px rgb(220 38 38 / 34%);
}

.sticky-note__status-trigger--cloture {
  background: linear-gradient(165deg, rgb(52 211 153), rgb(22 163 74));
  border-color: rgb(21 128 61 / 55%);
  color: white;
  box-shadow:
    0 0 0 1px rgb(255 255 255 / 35%) inset,
    0 2px 8px rgb(22 163 74 / 32%);
}

.sticky-note__status-trigger--archivee {
  background: linear-gradient(165deg, rgb(148 163 184), rgb(100 116 139));
  border-color: rgb(71 85 105 / 56%);
  color: white;
  box-shadow:
    0 0 0 1px rgb(255 255 255 / 32%) inset,
    0 2px 8px rgb(51 65 85 / 28%);
}

.sticky-note__status-trigger--bon .sticky-note__status-chevron,
.sticky-note__status-trigger--vigilance .sticky-note__status-chevron,
.sticky-note__status-trigger--critique .sticky-note__status-chevron,
.sticky-note__status-trigger--cloture .sticky-note__status-chevron,
.sticky-note__status-trigger--archivee .sticky-note__status-chevron {
  opacity: 0.95;
}

.sticky-note__status-menu {
  position: absolute;
  left: 0;
  top: calc(100% + 6px);
  min-width: 100%;
  width: max-content;
  max-width: min(220px, 100vw);
  margin: 0;
  padding: 5px;
  list-style: none;
  border-radius: 9px;
  background: color-mix(in srgb, var(--sticky-surface-strong) 96%, white);
  border: 1px solid color-mix(in srgb, var(--sticky-edge) 80%, rgb(15 23 42 / 8%));
  box-shadow:
    0 4px 6px rgb(15 23 42 / 6%),
    0 14px 36px rgb(15 23 42 / 14%);
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.sticky-note__status-menu-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  width: 100%;
  margin: 0;
  padding: 5px 7px;
  border: 1px solid transparent;
  border-radius: 7px;
  cursor: pointer;
  font-family: inherit;
  font-size: clamp(6px, calc(4.5px + 0.78cqi), 7px);
  font-weight: 800;
  letter-spacing: 0.02em;
  text-align: left;
  background-clip: padding-box;
  transition:
    background 0.12s ease,
    border-color 0.12s ease,
    transform 0.1s ease;
}

.sticky-note__status-menu-item:hover {
  transform: translateY(-0.5px);
}

.sticky-note__status-menu-item:focus {
  outline: none;
}

.sticky-note__status-menu-item:focus-visible {
  outline: 2px solid color-mix(in srgb, var(--sticky-bg) 35%, var(--sticky-ink));
  outline-offset: 0;
}

.sticky-note__status-menu-item-label {
  flex: 1;
  min-width: 0;
  line-height: 1.25;
  white-space: normal;
}

.sticky-note__status-menu-check {
  flex-shrink: 0;
  font-size: 11px;
  font-weight: 900;
  line-height: 1;
  opacity: 0.92;
}

.sticky-note__status-menu-item--bon {
  background: color-mix(in srgb, rgb(220 252 231) 92%, var(--sticky-bg));
  border-color: rgb(134 239 172 / 58%);
  color: rgb(6 78 59);
}

.sticky-note__status-menu-item--bon:hover {
  background: linear-gradient(165deg, rgb(187 247 208), rgb(134 239 172));
  border-color: rgb(74 222 128 / 55%);
  color: rgb(6 78 59);
}

.sticky-note__status-menu-item--vigilance {
  background: color-mix(in srgb, rgb(255 237 213) 92%, var(--sticky-bg));
  border-color: rgb(251 146 60 / 55%);
  color: rgb(154 52 18);
}

.sticky-note__status-menu-item--vigilance:hover {
  background: linear-gradient(165deg, rgb(253 186 116), rgb(249 115 22));
  border-color: rgb(234 88 12 / 55%);
  color: rgb(67 20 7);
}

.sticky-note__status-menu-item--critique {
  background: color-mix(in srgb, rgb(254 226 226) 92%, var(--sticky-bg));
  border-color: rgb(248 113 113 / 55%);
  color: rgb(127 29 29);
}

.sticky-note__status-menu-item--critique:hover {
  background: linear-gradient(165deg, rgb(252 165 165), rgb(239 68 68));
  border-color: rgb(220 38 38 / 55%);
  color: rgb(69 10 10);
}

.sticky-note__status-menu-item--cloture {
  background: color-mix(in srgb, rgb(209 250 229) 90%, var(--sticky-bg));
  border-color: rgb(110 231 183 / 55%);
  color: rgb(6 78 59);
}

.sticky-note__status-menu-item--cloture:hover {
  background: linear-gradient(165deg, rgb(110 231 183), rgb(52 211 153));
  border-color: rgb(16 185 129 / 55%);
  color: rgb(6 78 59);
}

.sticky-note__status-menu-item--archivee {
  background: color-mix(in srgb, rgb(226 232 240) 92%, var(--sticky-bg));
  border-color: rgb(148 163 184 / 56%);
  color: rgb(51 65 85);
}

.sticky-note__status-menu-item--archivee:hover {
  background: linear-gradient(165deg, rgb(203 213 225), rgb(148 163 184));
  border-color: rgb(100 116 139 / 58%);
  color: rgb(30 41 59);
}

.sticky-note__progress-readonly {
  width: 100%;
  min-width: 0;
  height: 4px;
  border-radius: 999px;
  background: var(--sticky-progress-track);
  box-shadow: inset 0 1px 2px rgb(15 23 42 / 6%);
  overflow: hidden;
}

.sticky-note__progress-readonly-fill {
  height: 100%;
  border-radius: 999px;
  background: var(--sticky-progress);
  transition: width 0.15s ease;
}

.sticky-note__range-wrap {
  display: flex;
  flex-direction: column;
  align-items: stretch;
  gap: 4px;
  width: 100%;
  min-width: 0;
  cursor: pointer;
}

.sticky-note__range-label {
  font-size: 8.5px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: #000000;
  padding-left: 1px;
}

.sticky-note__range {
  --p: 0;
  width: 100%;
  height: 16px;
  margin: 0;
  padding: 0;
  -webkit-appearance: none;
  appearance: none;
  background: transparent;
  cursor: pointer;
  --range-fill: var(--sticky-progress);
  --range-track: var(--sticky-progress-track);
}

.sticky-note__range:focus {
  outline: none;
}

.sticky-note__range:focus-visible {
  outline: 2px solid color-mix(in srgb, var(--sticky-bg) 35%, var(--sticky-ink));
  outline-offset: 2px;
  border-radius: 6px;
}

.sticky-note__range::-webkit-slider-runnable-track {
  cursor: pointer;
  height: 4px;
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

.sticky-note__range::-moz-range-track {
  cursor: pointer;
  height: 4px;
  border-radius: 999px;
  background: var(--range-track);
  box-shadow: inset 0 1px 2px rgb(15 23 42 / 6%);
}

.sticky-note__range::-moz-range-progress {
  height: 4px;
  border-radius: 999px 0 0 999px;
  background: var(--range-fill);
}

.sticky-note__range::-webkit-slider-thumb {
  -webkit-appearance: none;
  appearance: none;
  cursor: pointer;
  width: 12px;
  height: 12px;
  margin-top: -4px;
  border-radius: 50%;
  border: 2px solid rgb(255 255 255 / 95%);
  background: linear-gradient(
    160deg,
    color-mix(in srgb, var(--sticky-bg) 32%, white),
    var(--sticky-progress-deep)
  );
  box-shadow:
    0 0 0 1px color-mix(in srgb, var(--sticky-edge) 70%, transparent),
    0 2px 6px rgb(15 23 42 / 18%);
  transition: transform 0.12s ease;
}

.sticky-note__range::-webkit-slider-thumb:hover {
  transform: scale(1.06);
}

.sticky-note__range::-webkit-slider-thumb:active {
  transform: scale(0.95);
}

.sticky-note__range::-moz-range-thumb {
  cursor: pointer;
  width: 12px;
  height: 12px;
  border-radius: 50%;
  border: 2px solid rgb(255 255 255 / 95%);
  background: linear-gradient(
    160deg,
    color-mix(in srgb, var(--sticky-bg) 32%, white),
    var(--sticky-progress-deep)
  );
  box-shadow:
    0 0 0 1px color-mix(in srgb, var(--sticky-edge) 70%, transparent),
    0 2px 6px rgb(15 23 42 / 18%);
}
</style>
