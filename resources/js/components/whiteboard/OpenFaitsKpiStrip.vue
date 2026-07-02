<script setup lang="ts">
export type WhiteboardKpiItem = {
    key: string;
    label: string;
    count: number;
    slug: string;
    /** Icône météo au-dessus du libellé (ex. `/meteo/1.png`). */
    iconSrc?: string;
    style?: Record<string, string>;
    hexBadge?: boolean;
};

const props = defineProps<{
    items: WhiteboardKpiItem[];
    /** Moins haut / serré pour la rangée à côté du donut. */
    compact?: boolean;
}>();

</script>

<template>
    <div
        class="wb-open-faits-kpi"
        :class="{ 'wb-open-faits-kpi--compact': props.compact }"
        role="group"
        aria-label="Indicateurs statut du fait"
    >
        <template v-for="item in props.items" :key="item.key">
            <article
                class="wb-open-faits-kpi__card"
                :class="[
                    item.hexBadge
                        ? 'wb-open-faits-kpi__card--hex'
                        : `wb-open-faits-kpi__card--${item.slug}`,
                ]"
                :style="item.style"
                :aria-label="`${item.label}, ${item.count}`"
            >
                <span class="wb-open-faits-kpi__value tabular-nums">{{ item.count }}</span>
                <div v-if="item.iconSrc" class="wb-open-faits-kpi__label-block">
                    <img
                        :src="item.iconSrc"
                        alt=""
                        class="wb-open-faits-kpi__meteo-icon"
                        width="32"
                        height="32"
                        decoding="async"
                    />
                    <span class="wb-open-faits-kpi__label">{{ item.label }}</span>
                </div>
                <span v-else class="wb-open-faits-kpi__label">{{ item.label }}</span>
            </article>
        </template>
    </div>
</template>

<style scoped>
.wb-open-faits-kpi {
    display: grid;
    width: 100%;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    align-items: stretch;
    gap: 0.5rem;
}

.wb-open-faits-kpi__card {
    display: flex;
    min-width: 0;
    min-height: 3.25rem;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.35rem;
    border-radius: 0.625rem;
    border: 1px solid transparent;
    padding: 0.65rem 0.75rem;
    background: rgb(255 255 255);
    box-shadow: 0 1px 2px rgb(0 0 0 / 6%);
    text-align: center;
}

.dark .wb-open-faits-kpi__card {
    box-shadow: 0 1px 2px rgb(0 0 0 / 35%);
}

.wb-open-faits-kpi__card--bon {
    background-color: rgb(220 252 231);
    color: rgb(22 101 52);
    border-color: rgb(167 243 208);
}

.wb-open-faits-kpi__card--vigilance {
    background-color: rgb(255 237 213);
    color: rgb(154 52 18);
    border-color: rgb(253 186 116);
}

.wb-open-faits-kpi__card--critique {
    background-color: rgb(254 226 226);
    color: rgb(127 29 29);
    border-color: rgb(252 165 165);
}

.wb-open-faits-kpi__card--ouvert {
    background-color: rgb(238 242 255);
    color: rgb(67 56 202);
    border-color: rgb(199 210 254);
}

.wb-open-faits-kpi__card--cloture {
    background-color: rgb(209 250 229);
    color: rgb(6 78 59);
    border-color: rgb(110 231 183);
}

.wb-open-faits-kpi__card--archivee {
    background-color: rgb(241 245 249);
    color: rgb(51 65 85);
    border-color: rgb(203 213 225);
}

.wb-open-faits-kpi__card--m1 {
    background-color: rgb(254 252 232);
    color: rgb(113 63 18);
    border-color: rgb(253 224 71);
}

.wb-open-faits-kpi__card--m2 {
    background-color: rgb(255 247 237);
    color: rgb(124 45 18);
    border-color: rgb(251 191 36);
}

.wb-open-faits-kpi__card--m3 {
    background-color: rgb(232 245 240);
    color: rgb(26 77 58);
    border-color: rgb(134 191 165);
}

.wb-open-faits-kpi__card--hex {
    border-color: rgb(15 23 42 / 14%);
}

.dark .wb-open-faits-kpi__card--bon {
    background-color: rgb(20 83 45 / 35%);
    color: rgb(187 247 208);
    border-color: rgb(34 197 94 / 35%);
}

.dark .wb-open-faits-kpi__card--vigilance {
    background-color: rgb(154 52 18 / 28%);
    color: rgb(254 215 170);
    border-color: rgb(251 146 60 / 35%);
}

.dark .wb-open-faits-kpi__card--critique {
    background-color: rgb(127 29 29 / 35%);
    color: rgb(254 202 202);
    border-color: rgb(248 113 113 / 35%);
}

.dark .wb-open-faits-kpi__card--ouvert {
    background-color: rgb(67 56 202 / 28%);
    color: rgb(199 210 254);
    border-color: rgb(99 102 241 / 35%);
}

.dark .wb-open-faits-kpi__card--cloture {
    background-color: rgb(6 78 59 / 35%);
    color: rgb(167 243 208);
    border-color: rgb(52 211 153 / 35%);
}

.dark .wb-open-faits-kpi__card--archivee {
    background-color: rgb(30 41 59);
    color: rgb(226 232 240);
    border-color: rgb(71 85 105);
}

.dark .wb-open-faits-kpi__card--m1 {
    background-color: rgb(113 63 18 / 28%);
    color: rgb(254 240 138);
    border-color: rgb(250 204 21 / 35%);
}

.dark .wb-open-faits-kpi__card--m2 {
    background-color: rgb(124 45 18 / 28%);
    color: rgb(254 215 170);
    border-color: rgb(251 146 60 / 35%);
}

.dark .wb-open-faits-kpi__card--m3 {
    background-color: rgb(26 77 58 / 28%);
    color: rgb(187 220 205);
    border-color: rgb(74 155 122 / 35%);
}

.dark .wb-open-faits-kpi__card--hex {
    border-color: rgb(148 163 184 / 35%);
}

.wb-open-faits-kpi--compact {
    gap: 0.3rem;
}

.wb-open-faits-kpi--compact .wb-open-faits-kpi__card {
    min-height: 2.75rem;
    padding: 0.4rem 0.45rem;
    gap: 0.2rem;
}

.wb-open-faits-kpi--compact .wb-open-faits-kpi__value {
    font-size: clamp(1.1rem, 1.5vw, 1.45rem);
}

.wb-open-faits-kpi--compact .wb-open-faits-kpi__label {
    font-size: clamp(0.55rem, 0.95vw, 0.68rem);
}

.wb-open-faits-kpi--compact .wb-open-faits-kpi__meteo-icon {
    width: clamp(1.35rem, 1.8vw, 1.75rem);
    height: clamp(1.35rem, 1.8vw, 1.75rem);
}

.wb-open-faits-kpi__value {
    font-size: clamp(1.35rem, 2.2vw, 1.75rem);
    font-weight: 800;
    line-height: 1.1;
    letter-spacing: -0.02em;
}

.wb-open-faits-kpi__label-block {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.2rem;
    min-width: 0;
}

.wb-open-faits-kpi__label {
    font-size: clamp(0.65rem, 1.1vw, 0.8rem);
    font-weight: 700;
    line-height: 1.2;
}

.wb-open-faits-kpi__meteo-icon {
    width: clamp(1.75rem, 2.5vw, 2.25rem);
    height: clamp(1.75rem, 2.5vw, 2.25rem);
    flex-shrink: 0;
    object-fit: contain;
}

@media (max-width: 900px) {
    .wb-open-faits-kpi {
        gap: 0.4rem;
    }
}
</style>
