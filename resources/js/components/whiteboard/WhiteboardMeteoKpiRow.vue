<script setup lang="ts">
import OpenFaitsKpiStrip, { type WhiteboardKpiItem } from '@/components/whiteboard/OpenFaitsKpiStrip.vue';
import WeeklyMeteoAllDepartmentsDonut from '@/components/whiteboard/WeeklyMeteoAllDepartmentsDonut.vue';

defineProps<{
    total: number;
    meteo1: number;
    meteo2: number;
    meteo3: number;
    kpiItems: WhiteboardKpiItem[];
}>();
</script>

<template>
    <div class="wb-meteo-kpi-row" role="region" aria-label="Météo générale et statuts des faits ouverts">
        <div class="wb-meteo-kpi-row__donut">
            <WeeklyMeteoAllDepartmentsDonut
                fill
                :total="total"
                :meteo1="meteo1"
                :meteo2="meteo2"
                :meteo3="meteo3"
            />
        </div>
        <OpenFaitsKpiStrip
            v-if="kpiItems.length > 0"
            class="wb-meteo-kpi-row__kpis"
            :items="kpiItems"
        />
    </div>
</template>

<style scoped>
.wb-meteo-kpi-row {
    display: grid;
    width: 100%;
    grid-template-columns: minmax(0, 1fr);
    align-items: stretch;
    gap: 0.5rem;
}

@media (min-width: 640px) {
    .wb-meteo-kpi-row {
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 0.625rem;
    }
}

.wb-meteo-kpi-row__donut {
    display: flex;
    min-width: 0;
    min-height: 0;
    align-items: stretch;
}

.wb-meteo-kpi-row__kpis {
    min-width: 0;
}

@media (min-width: 640px) {
    .wb-meteo-kpi-row__kpis {
        grid-column: span 3;
    }
}
</style>
