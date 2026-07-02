<script setup>
import { inject, watch, onUnmounted } from 'vue'
import { useVueFlow } from '@vue-flow/core'
import { stickyDimensionsFromLists } from '../constants/projects'

const {
  vueFlowRef,
  addNodes,
  updateNode,
  getNodes,
  viewportHelper,
  initialized,
} = useVueFlow()

const persistStickyLayout = inject('persistStickyLayout', () => {})

function newNodeId() {
  return typeof crypto !== 'undefined' && crypto.randomUUID
    ? `note-${crypto.randomUUID()}`
    : `note-${Date.now()}-${Math.random().toString(36).slice(2, 9)}`
}

let detach = () => {}

function bindPane() {
  detach()
  const root = vueFlowRef.value
  if (!root) return

  const onDragOver = (e) => {
    e.preventDefault()
    e.dataTransfer.dropEffect = 'copy'
  }

  const onDrop = (e) => {
    e.preventDefault()
    const raw =
      e.dataTransfer?.getData('application/x-sticky-project+json') ||
      e.dataTransfer?.getData('application/json')
    if (!raw) return

    let payload
    try {
      payload = JSON.parse(raw)
    } catch {
      return
    }

    const projectId = payload?.id
    const name = payload?.name
    const rawProgress = Number(payload?.progress)
    const progress =
      Number.isFinite(rawProgress) ? Math.min(100, Math.max(0, Math.round(rawProgress))) : 0
    const rawStatus = payload?.projectStatus
    const projectStatus = (() => {
      if (
        rawStatus === 'bon' ||
        rawStatus === 'vigilance' ||
        rawStatus === 'critique' ||
        rawStatus === 'cloture' ||
        rawStatus === 'archivee'
      )
        return rawStatus
      if (rawStatus === 'termine') return 'cloture'
      if (rawStatus === 'cloturé' || rawStatus === 'clôturé') return 'cloture'
      if (rawStatus === 'archive' || rawStatus === 'archivé' || rawStatus === 'archivée')
        return 'archivee'
      if (rawStatus === 'en-cours') return 'vigilance'
      if (rawStatus === 'a-faire') return 'critique'
      return 'vigilance'
    })()
    const asStrings = (v) =>
      Array.isArray(v)
        ? v.map((x) => (typeof x === 'string' ? x : String(x ?? '')))
        : []
    let faitsMarquants = asStrings(payload.faitsMarquants)
    let commentaires = asStrings(payload.commentaires)
    if (
      !faitsMarquants.length &&
      typeof payload.description === 'string' &&
      payload.description.trim()
    ) {
      faitsMarquants = [payload.description.trim()]
    }
    while (faitsMarquants.length < 5) faitsMarquants.push('')
    while (commentaires.length < 5) commentaires.push('')
    if (
      !projectId ||
      typeof projectId !== 'string' ||
      !name ||
      typeof name !== 'string'
    ) {
      return
    }

    const helper = viewportHelper.value
    if (!helper.viewportInitialized) return

    if (
      getNodes.value.some(
        (n) => n.type === 'sticky' && n.data?.projectId === projectId,
      )
    ) {
      return
    }

    const position = helper.screenToFlowCoordinate({
      x: e.clientX,
      y: e.clientY,
    })

    for (const n of getNodes.value) {
      if (n.selected) updateNode(n.id, { selected: false })
    }

    const { width: noteW, height: noteH } = stickyDimensionsFromLists(
      faitsMarquants,
      commentaires,
    )

    addNodes({
      id: newNodeId(),
      type: 'sticky',
      position,
      selected: true,
      width: noteW,
      height: noteH,
      data: {
        projectId,
        projectName: name,
        faitsMarquants,
        commentaires,
        stickyView: 'faits',
        userSizedNote: false,
        projectStatus,
        progress,
      },
    })

    persistStickyLayout(projectId, {
      x: Math.round(position.x),
      y: Math.round(position.y),
      width: Math.round(noteW),
      height: Math.round(noteH),
    })
  }

  root.addEventListener('dragover', onDragOver)
  root.addEventListener('drop', onDrop)

  detach = () => {
    root.removeEventListener('dragover', onDragOver)
    root.removeEventListener('drop', onDrop)
  }
}

watch(
  [vueFlowRef, initialized],
  () => {
    if (initialized.value && vueFlowRef.value) bindPane()
  },
  { immediate: true, flush: 'post' },
)

onUnmounted(() => detach())
</script>

<template>
  <!-- Logique interne : doit rester dans le sous-arbre VueFlow -->
  <span aria-hidden="true" class="flow-drop-listener" />
</template>

<style scoped>
.flow-drop-listener {
  display: none;
}
</style>
