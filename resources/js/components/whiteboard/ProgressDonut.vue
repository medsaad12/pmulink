<script setup>
import { computed } from 'vue'

const props = defineProps({
  percent: { type: Number, default: 0 },
  /** Outer diameter in px */
  size: { type: Number, default: 32 },
  stroke: { type: Number, default: 3.5 },
  /** Ring progress color; empty uses `var(--sticky-progress)` from an ancestor */
  arcColor: { type: String, default: '' },
})

const pct = computed(() =>
  Math.min(100, Math.max(0, Math.round(Number(props.percent) || 0))),
)

const vb = computed(() => props.size)
const half = computed(() => props.size / 2)
const r = computed(() => half.value - props.stroke / 2 - 0.5)
const circumference = computed(() => 2 * Math.PI * r.value)
const dashOffset = computed(() => {
  const p = pct.value / 100
  return circumference.value * (1 - p)
})

/** Filled disk inside the ring so the center reads as a solid dark “dial”. */
const rFace = computed(() => Math.max(0.5, r.value - props.stroke / 2 - 0.35))

const labelPx = computed(() => Math.max(10, Math.round(props.size * 0.31)))

const rotate = computed(() => `rotate(-90 ${half.value} ${half.value})`)

const arcStrokeAttrs = computed(() =>
  props.arcColor ? { stroke: props.arcColor } : {},
)
</script>

<template>
  <div
    class="progress-donut"
    :style="{ width: `${size}px`, height: `${size}px` }"
    role="img"
    :aria-label="`${pct} pour cent`"
  >
    <svg
      class="progress-donut__svg"
      :viewBox="`0 0 ${vb} ${vb}`"
      aria-hidden="true"
    >
      <circle class="progress-donut__face" :cx="half" :cy="half" :r="rFace" />
      <circle
        class="progress-donut__track"
        :cx="half"
        :cy="half"
        :r="r"
        fill="none"
        :stroke-width="stroke"
      />
      <circle
        class="progress-donut__arc"
        :cx="half"
        :cy="half"
        :r="r"
        fill="none"
        :stroke-width="stroke"
        v-bind="arcStrokeAttrs"
        :stroke-dasharray="`${circumference} ${circumference}`"
        :stroke-dashoffset="dashOffset"
        stroke-linecap="round"
        :transform="rotate"
      />
    </svg>
    <span class="progress-donut__label" :style="{ fontSize: `${labelPx}px` }">{{
      pct
    }}</span>
  </div>
</template>

<style scoped>
.progress-donut {
  position: relative;
  flex-shrink: 0;
  display: grid;
  place-items: center;
}

.progress-donut__svg {
  width: 100%;
  height: 100%;
  display: block;
}

.progress-donut__face {
  fill: var(
    --sticky-donut-face,
    color-mix(in srgb, var(--sticky-progress-deep, #0f172a) 88%, var(--sticky-progress, #334155))
  );
}

.progress-donut__track {
  stroke: var(
    --sticky-donut-track,
    color-mix(in srgb, var(--sticky-progress, #334155) 42%, #020617)
  );
  opacity: 0.92;
}

.progress-donut__arc {
  stroke: var(
    --sticky-donut-arc,
    color-mix(in srgb, #ffffff 90%, var(--sticky-bg, #fefce8))
  );
  transition: stroke-dashoffset 0.35s cubic-bezier(0.22, 1, 0.36, 1);
}

.progress-donut__label {
  position: absolute;
  font-weight: 800;
  font-variant-numeric: tabular-nums;
  color: var(
    --sticky-donut-label,
    color-mix(in srgb, #ffffff 94%, var(--sticky-bg, white))
  );
  line-height: 1;
  pointer-events: none;
  letter-spacing: -0.04em;
  text-shadow:
    0 1px 2px rgb(0 0 0 / 42%),
    0 0 1px rgb(0 0 0 / 35%);
}
</style>
