<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    total: number;
    meteo1: number;
    meteo2: number;
    meteo3: number;
    /** Étire la carte sur toute la largeur de la colonne (rangée 4 cartes). */
    fill?: boolean;
}>();

function pct(n: number, total: number): number {
    if (total <= 0) {
        return 0;
    }

    return (100 * n) / total;
}

/** Aligné sur les couleurs statut (soleil = vert, nuageux = jaune, orage = rouge). */
const DONUT_SLICE_COLORS = {
    m1: '#16a34a',
    m2: '#eab308',
    m3: '#dc2626',
} as const;

/** SVG annular sector: angles in degrees, 0° = top, clockwise (screen coords, y down). */
function annularSector(
    cx: number,
    cy: number,
    rInner: number,
    rOuter: number,
    degStart: number,
    degEnd: number,
): string {
    const span = degEnd - degStart;

    if (span <= 0.0001) {
        return '';
    }

    /** Arc 0→360° is degenerate in SVG ; anneau complet en deux cercles (evenodd). */
    if (span >= 359.99) {
        return [
            `M ${cx} ${cy - rOuter}`,
            `A ${rOuter} ${rOuter} 0 1 1 ${cx} ${cy + rOuter}`,
            `A ${rOuter} ${rOuter} 0 1 1 ${cx} ${cy - rOuter}`,
            `M ${cx} ${cy - rInner}`,
            `A ${rInner} ${rInner} 0 1 0 ${cx} ${cy + rInner}`,
            `A ${rInner} ${rInner} 0 1 0 ${cx} ${cy - rInner}`,
            'Z',
        ].join(' ');
    }

    const rad = (d: number) => (Math.PI / 180) * d;
    const pt = (r: number, deg: number) => {
        const t = rad(deg);

        return {
            x: cx + r * Math.sin(t),
            y: cy - r * Math.cos(t),
        };
    };

    const o0 = pt(rOuter, degStart);
    const o1 = pt(rOuter, degEnd);
    const i1 = pt(rInner, degEnd);
    const i0 = pt(rInner, degStart);
    const large = degEnd - degStart > 180 ? 1 : 0;

    return `M ${o0.x} ${o0.y} A ${rOuter} ${rOuter} 0 ${large} 1 ${o1.x} ${o1.y} L ${i1.x} ${i1.y} A ${rInner} ${rInner} 0 ${large} 0 ${i0.x} ${i0.y} Z`;
}

const SVG_CX = 50;
const SVG_CY = 50;
const R_OUT = 47;
const R_IN = 30;

const svgSlices = computed(() => {
    const t = props.total;

    if (t <= 0) {
        return [];
    }

    const segs: { count: number; fill: string }[] = [
        { count: props.meteo1, fill: DONUT_SLICE_COLORS.m1 },
        { count: props.meteo2, fill: DONUT_SLICE_COLORS.m2 },
        { count: props.meteo3, fill: DONUT_SLICE_COLORS.m3 },
    ];

    let deg = 0;

    return segs
        .map((s) => {
            const span = (360 * s.count) / t;
            const start = deg;
            const end = deg + span;
            deg = end;
            const d = annularSector(SVG_CX, SVG_CY, R_IN, R_OUT, start, end);

            return { d, fill: s.fill, count: s.count };
        })
        .filter((s) => s.count > 0 && s.d.length > 0);
});

/** Anneau entièrement coloré (ex. 100 % soleil) — masquer la piste grise. */
const hideGreyTrack = computed(() => {
    const t = props.total;

    if (t <= 0) {
        return false;
    }

    return props.meteo1 === t || props.meteo2 === t || props.meteo3 === t;
});

const rows = computed(() => {
    const t = props.total;

    return [
        {
            key: 'm1' as const,
            meteoNum: 1 as const,
            sliceColor: DONUT_SLICE_COLORS.m1,
            aria: `En bonne voie, ${props.meteo1} fait${props.meteo1 === 1 ? '' : 's'} marquant${props.meteo1 === 1 ? '' : 's'}, ${Math.round(pct(props.meteo1, t))} pour cent`,
            count: props.meteo1,
            pct: Math.round(pct(props.meteo1, t)),
        },
        {
            key: 'm2' as const,
            meteoNum: 2 as const,
            sliceColor: DONUT_SLICE_COLORS.m2,
            aria: `Sous vigilance, ${props.meteo2} fait${props.meteo2 === 1 ? '' : 's'} marquant${props.meteo2 === 1 ? '' : 's'}, ${Math.round(pct(props.meteo2, t))} pour cent`,
            count: props.meteo2,
            pct: Math.round(pct(props.meteo2, t)),
        },
        {
            key: 'm3' as const,
            meteoNum: 3 as const,
            sliceColor: DONUT_SLICE_COLORS.m3,
            aria: `Critique ou à risque, ${props.meteo3} fait${props.meteo3 === 1 ? '' : 's'} marquant${props.meteo3 === 1 ? '' : 's'}, ${Math.round(pct(props.meteo3, t))} pour cent`,
            count: props.meteo3,
            pct: Math.round(pct(props.meteo3, t)),
        },
    ];
});

const ariaLabel = computed(() => {
    const t = props.total;

    if (t <= 0) {
        return 'Aucun sujet ouvert avec statut renseigné';
    }

    return rows.value.map((r) => r.aria).join('. ');
});
</script>

<template>
    <div class="wb-all-meteo" :class="{ 'wb-all-meteo--fill': props.fill }" role="img" :aria-label="ariaLabel">
        <div v-if="total <= 0" class="wb-all-meteo__empty">Aucun sujet ouvert avec statut renseigné.</div>
        <div v-else class="wb-all-meteo__body">
            <div class="wb-all-meteo__chart" aria-hidden="true">
                <svg class="wb-all-meteo__svg" viewBox="0 0 100 100">
                    <circle
                        v-if="!hideGreyTrack"
                        class="wb-all-meteo__track"
                        :cx="SVG_CX"
                        :cy="SVG_CY"
                        :r="(R_OUT + R_IN) / 2"
                        fill="none"
                        :stroke-width="R_OUT - R_IN"
                    />
                    <path
                        v-for="(s, i) in svgSlices"
                        :key="i"
                        class="wb-all-meteo__slice"
                        fill-rule="evenodd"
                        :d="s.d"
                        :fill="s.fill"
                    />
                </svg>
            </div>
            <ul class="wb-all-meteo__list">
                <li
                    v-for="r in rows"
                    :key="r.key"
                    class="wb-all-meteo__key"
                    :aria-label="r.aria"
                    :title="r.aria"
                >
                    <img
                        :src="`/meteo/${r.meteoNum}.png`"
                        alt=""
                        class="wb-all-meteo__icon"
                    />
                    <span class="wb-all-meteo__pct">{{ r.pct }}%</span>
                    <span
                        class="wb-all-meteo__swatch"
                        :style="{ backgroundColor: r.sliceColor }"
                        aria-hidden="true"
                    />
                </li>
            </ul>
        </div>
    </div>
</template>

<style scoped>
.wb-all-meteo--fill {
    width: 100%;
    max-width: none;
}

.wb-all-meteo--fill .wb-all-meteo__body {
    width: 100%;
    padding-inline: 0.5rem;
}

.wb-all-meteo--fill .wb-all-meteo__chart {
    width: clamp(4.75rem, 100%, 7rem);
    height: clamp(4.75rem, 100%, 7rem);
}

.wb-all-meteo {
    display: flex;
    width: max-content;
    max-width: min(100vw - 2rem, 100%);
    flex-direction: column;
    align-items: center;
    border-radius: 0.5rem;
    padding-block: 0.65rem 0.75rem;
    padding-inline: 1.35rem;
    background: rgb(255 255 255);
    box-shadow: 0 1px 2px rgb(0 0 0 / 6%);
    border: 1px solid rgb(226 232 240);
}

.dark .wb-all-meteo {
    background: rgb(15 23 42);
    border-color: rgb(51 65 85 / 0.85);
    box-shadow: 0 1px 2px rgb(0 0 0 / 35%);
}

.wb-all-meteo__empty {
    margin: 0;
    font-size: 12px;
    color: rgb(100 116 139);
}

.dark .wb-all-meteo__empty {
    color: rgb(148 163 184);
}

.wb-all-meteo__body {
    display: flex;
    flex-direction: row;
    align-items: center;
    gap: 1.25rem;
    width: 100%;
}

.wb-all-meteo__list {
    display: flex;
    flex-direction: column;
    flex-wrap: nowrap;
    align-items: stretch;
    justify-content: center;
    gap: 0.55rem;
    margin: 0;
    padding-block: 0.15rem;
    padding-inline: 0.2rem 0.45rem;
    list-style: none;
}

.wb-all-meteo__key {
    display: flex;
    flex-direction: row;
    flex-shrink: 0;
    align-items: center;
    gap: 0.5rem;
    padding: 0.1rem 0;
    font-size: 12px;
    line-height: 1.25;
    font-weight: 600;
    white-space: nowrap;
}

.wb-all-meteo__swatch {
    width: 1.25rem;
    height: 0.32rem;
    flex-shrink: 0;
    border-radius: 9999px;
    box-shadow: inset 0 0 0 1px rgb(15 23 42 / 0.12);
}

.dark .wb-all-meteo__swatch {
    box-shadow: inset 0 0 0 1px rgb(255 255 255 / 0.18);
}

.wb-all-meteo__chart {
    flex-shrink: 0;
    width: 5.75rem;
    height: 5.75rem;
    margin-block: 0;
    margin-inline: 0.35rem 0.25rem;
    padding: 0;
    line-height: 0;
}

.wb-all-meteo__svg {
    display: block;
    width: 100%;
    height: 100%;
}

.wb-all-meteo__track {
    stroke: rgb(226 232 240);
}

.wb-all-meteo__slice {
    stroke: none;
}

.dark .wb-all-meteo__slice {
    stroke: rgb(15 23 42 / 0.65);
}

.dark .wb-all-meteo__track {
    stroke: rgb(51 65 85);
}

.wb-all-meteo__icon {
    width: 1.5rem;
    height: 1.5rem;
    flex-shrink: 0;
    object-fit: contain;
}

.wb-all-meteo__pct {
    flex-shrink: 0;
    font-variant-numeric: tabular-nums;
    color: rgb(51 65 85);
}

.dark .wb-all-meteo__pct {
    color: rgb(203 213 225);
}
</style>
