<script setup lang="ts">
import type { DepartmentOption } from '@/types/faitMarquant';
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';

const props = withDefaults(
    defineProps<{
        departments: DepartmentOption[];
        userDepartmentIds?: number[];
        /** Affiche le badge « Vous » sur les départements assignés. */
        highlightOwn?: boolean;
    }>(),
    {
        userDepartmentIds: () => [],
        highlightOwn: false,
    },
);

const departmentFilterId = defineModel<number | null>({ required: true });

const scrollEl = ref<HTMLElement | null>(null);
const canScrollLeft = ref(false);
const canScrollRight = ref(false);

let resizeObserver: ResizeObserver | null = null;

function updateScrollState(): void {
    const el = scrollEl.value;

    if (!el) {
        canScrollLeft.value = false;
        canScrollRight.value = false;

        return;
    }

    const maxScroll = el.scrollWidth - el.clientWidth;

    canScrollLeft.value = el.scrollLeft > 4;
    canScrollRight.value = maxScroll > 4 && el.scrollLeft < maxScroll - 4;
}

function scrollByDirection(direction: -1 | 1): void {
    scrollEl.value?.scrollBy({ left: direction * 200, behavior: 'smooth' });
}

function isOwnDepartment(deptId: number): boolean {
    return props.highlightOwn && props.userDepartmentIds.includes(deptId);
}

/** Départements triés : ceux de l'utilisateur (badge « Vous ») d'abord, ordre d'origine conservé sinon. */
const sortedDepartments = computed<DepartmentOption[]>(() => {
    return props.departments
        .map((department, index) => ({ department, index }))
        .sort((a, b) => {
            const aOwn = isOwnDepartment(a.department.id) ? 0 : 1;
            const bOwn = isOwnDepartment(b.department.id) ? 0 : 1;

            return aOwn - bOwn || a.index - b.index;
        })
        .map((entry) => entry.department);
});

function onPillClick(id: number | null): void {
    departmentFilterId.value = id;
}

watch(
    () => props.departments.length,
    async () => {
        await nextTick();
        updateScrollState();
    },
);

onMounted(() => {
    updateScrollState();

    if (scrollEl.value && typeof ResizeObserver !== 'undefined') {
        resizeObserver = new ResizeObserver(() => updateScrollState());
        resizeObserver.observe(scrollEl.value);
    }
});

onUnmounted(() => {
    resizeObserver?.disconnect();
});
</script>

<template>
    <div class="wb-dept-filter-carousel">
        <button
            type="button"
            class="wb-dept-filter-nav"
            :disabled="!canScrollLeft"
            aria-label="Départements précédents"
            @click="scrollByDirection(-1)"
        >
            <ChevronLeft class="size-4" :stroke-width="2.5" aria-hidden="true" />
        </button>

        <div
            ref="scrollEl"
            class="wb-dept-filter-scroll"
            role="group"
            aria-label="Filtrer par département"
            @scroll.passive="updateScrollState"
        >
            <button
                type="button"
                class="wb-dept-filter-pill"
                :class="{ 'wb-dept-filter-pill--active': departmentFilterId === null }"
                :aria-pressed="departmentFilterId === null"
                @click="onPillClick(null)"
            >
                Tous
            </button>
            <button
                v-for="d in sortedDepartments"
                :key="d.id"
                type="button"
                class="wb-dept-filter-pill"
                :class="{ 'wb-dept-filter-pill--active': departmentFilterId === d.id }"
                :aria-pressed="departmentFilterId === d.id"
                :aria-label="isOwnDepartment(d.id) ? `${d.name}, votre département` : d.name"
                @click="onPillClick(d.id)"
            >
                <span class="wb-dept-filter-pill__label">{{ d.name }}</span>
                <span
                    v-if="isOwnDepartment(d.id)"
                    class="wb-dept-filter-pill__badge"
                    aria-hidden="true"
                >
                    Vous
                </span>
            </button>
        </div>

        <button
            type="button"
            class="wb-dept-filter-nav"
            :disabled="!canScrollRight"
            aria-label="Départements suivants"
            @click="scrollByDirection(1)"
        >
            <ChevronRight class="size-4" :stroke-width="2.5" aria-hidden="true" />
        </button>
    </div>
</template>

<style scoped>
.wb-dept-filter-carousel {
    display: flex;
    flex: 1 1 auto;
    min-width: 0;
    align-items: center;
    gap: 0.35rem;
    overflow: visible;
}

.wb-dept-filter-nav {
    display: flex;
    flex-shrink: 0;
    width: 2rem;
    height: 2rem;
    align-items: center;
    justify-content: center;
    border: none;
    border-radius: 9999px;
    color: rgb(71 85 105);
    background: rgb(255 255 255);
    box-shadow: 0 0 0 1px rgb(226 232 240);
    cursor: pointer;
    transition:
        background 0.15s ease,
        color 0.15s ease,
        opacity 0.15s ease;
}

.dark .wb-dept-filter-nav {
    color: rgb(203 213 225);
    background: rgb(30 41 59);
    box-shadow: 0 0 0 1px rgb(51 65 85);
}

.wb-dept-filter-nav:hover:not(:disabled) {
    color: rgb(109 40 217);
    background: rgb(245 243 255);
}

.dark .wb-dept-filter-nav:hover:not(:disabled) {
    color: rgb(196 181 253);
    background: rgb(51 65 85);
}

.wb-dept-filter-nav:disabled {
    opacity: 0.35;
    cursor: default;
}

.wb-dept-filter-nav:focus-visible {
    outline: 2px solid rgb(139 92 246 / 55%);
    outline-offset: 2px;
}

.wb-dept-filter-scroll {
    display: flex;
    flex: 1 1 auto;
    min-width: 0;
    flex-wrap: nowrap;
    align-items: center;
    gap: 0.5rem;
    overflow-x: auto;
    overflow-y: visible;
    overscroll-behavior-x: contain;
    -webkit-overflow-scrolling: touch;
    cursor: default;
    user-select: none;
    scrollbar-width: none;
    -ms-overflow-style: none;
    /* Évite que les pilules / ombres soient rognées par le clip du scroll horizontal */
    padding-block: 0.5rem;
    padding-inline: 0.35rem;
    margin-block: -0.5rem;
}

.wb-dept-filter-scroll::-webkit-scrollbar {
    display: none;
}

.wb-dept-filter-pill {
    display: inline-flex;
    flex-shrink: 0;
    align-items: center;
    gap: 0.375rem;
    cursor: pointer;
    white-space: nowrap;
    border: none;
    border-radius: 9999px;
    padding: 0.65rem 1.125rem 0.55rem;
    font-size: 0.8125rem;
    font-weight: 600;
    font-family: inherit;
    line-height: 1.2;
    color: rgb(51 65 85);
    background: rgb(255 255 255);
    box-shadow:
        0 1px 2px rgb(15 23 42 / 6%),
        0 0 0 1px rgb(226 232 240);
    transition:
        background 0.15s ease,
        color 0.15s ease,
        box-shadow 0.15s ease,
        transform 0.12s ease;
}

.dark .wb-dept-filter-pill {
    color: rgb(226 232 240);
    background: rgb(30 41 59);
    box-shadow:
        0 1px 2px rgb(0 0 0 / 25%),
        0 0 0 1px rgb(51 65 85);
}

.wb-dept-filter-pill:hover {
    box-shadow:
        0 4px 12px rgb(15 23 42 / 8%),
        0 0 0 1px rgb(199 210 254);
}

.wb-dept-filter-pill:focus-visible {
    outline: 2px solid rgb(139 92 246 / 55%);
    outline-offset: 2px;
}

.wb-dept-filter-pill--active {
    color: white;
    background: rgb(124 58 237);
    box-shadow:
        0 6px 18px rgb(124 58 237 / 38%),
        0 2px 6px rgb(15 23 42 / 12%);
}

.wb-dept-filter-pill--active:hover {
    background: rgb(109 40 217);
}

.dark .wb-dept-filter-pill--active {
    color: white;
    background: rgb(124 58 237);
}

.wb-dept-filter-pill__label {
    white-space: nowrap;
}

.wb-dept-filter-pill__badge {
    flex-shrink: 0;
    font-size: 0.625rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    line-height: 1;
    padding: 0.15rem 0.4rem;
    border-radius: 9999px;
    background: rgb(139 92 246 / 14%);
    color: rgb(109 40 217);
}

.dark .wb-dept-filter-pill__badge {
    background: rgb(167 139 250 / 18%);
    color: rgb(196 181 253);
}

.wb-dept-filter-pill--active .wb-dept-filter-pill__badge {
    background: rgb(255 255 255);
    color: rgb(109 40 217);
    box-shadow: 0 1px 2px rgb(15 23 42 / 14%);
}

@media (max-width: 1700px) {
    .wb-dept-filter-nav {
        width: 1.85rem;
        height: 1.85rem;
    }

    .wb-dept-filter-scroll {
        gap: 0.4rem;
        padding-inline: 0.25rem;
    }

    .wb-dept-filter-pill {
        padding: 0.55rem 0.9rem 0.5rem;
        font-size: 0.765rem;
    }

    .wb-dept-filter-pill__badge {
        font-size: 0.5625rem;
        padding: 0.12rem 0.32rem;
    }
}

@media (max-width: 1366px) {
    .wb-dept-filter-nav {
        width: 1.85rem;
        height: 1.85rem;
    }

    .wb-dept-filter-scroll {
        gap: 0.4rem;
        padding-inline: 0.25rem;
    }

    .wb-dept-filter-pill {
        padding: 0.55rem 0.85rem 0.5rem;
        font-size: 0.75rem;
    }
}

@media (max-width: 1180px) {
    .wb-dept-filter-scroll {
        gap: 0.35rem;
    }

    .wb-dept-filter-pill {
        padding: 0.5rem 0.72rem 0.45rem;
        font-size: 0.72rem;
    }

    .wb-dept-filter-pill__label {
        max-width: 8.5rem;
        overflow: hidden;
        text-overflow: ellipsis;
    }
}
</style>
