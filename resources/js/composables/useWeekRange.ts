import { computed } from 'vue';

const LOCALE = 'fr-FR';

/** Monday 00:00:00.000 of the calendar week containing `ref` (ISO-style, week starts Monday). */
function startOfWeekMonday(ref: Date): Date {
    const d = new Date(ref);
    d.setHours(0, 0, 0, 0);
    const day = d.getDay();
    const delta = day === 0 ? -6 : 1 - day;
    d.setDate(d.getDate() + delta);

    return d;
}

function endOfWeekSunday(ref: Date): Date {
    const mon = startOfWeekMonday(ref);
    const sun = new Date(mon);
    sun.setDate(sun.getDate() + 6);

    return sun;
}

function formatDay(d: Date): string {
    return d.toLocaleDateString(LOCALE, {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}

/** Local calendar date `YYYY-MM-DD`. */
export function formatIsoDateLocal(ref: Date): string {
    const y = ref.getFullYear();
    const m = String(ref.getMonth() + 1).padStart(2, '0');
    const day = String(ref.getDate()).padStart(2, '0');

    return `${y}-${m}-${day}`;
}

/** Local calendar date `YYYY-MM-DD` for Monday of the week containing `ref` (align with PHP `startOfWeek(MONDAY)` in app timezone when both use local). */
function formatWeekStartIso(ref: Date): string {
    return formatIsoDateLocal(startOfWeekMonday(ref));
}

export function formatDateRangeLabel(startIso: string, endIso: string): string {
    const from = startIso <= endIso ? startIso : endIso;
    const to = startIso <= endIso ? endIso : startIso;
    const start = new Date(`${from}T12:00:00`);
    const end = new Date(`${to}T12:00:00`);

    if (!Number.isFinite(start.getTime()) || !Number.isFinite(end.getTime())) {
        return '—';
    }

    return `${formatDay(start)} - ${formatDay(end)}`;
}

export function useWeekRange() {
    const weekRangeLabel = computed(() => {
        const now = new Date();

        return `${formatDay(startOfWeekMonday(now))} - ${formatDay(endOfWeekSunday(now))}`;
    });

    const weekStartIso = computed(() => formatWeekStartIso(new Date()));

    const currentWeekStartIso = computed(() => formatWeekStartIso(new Date()));

    const currentWeekEndIso = computed(() => formatIsoDateLocal(endOfWeekSunday(new Date())));

    return { weekRangeLabel, weekStartIso, currentWeekStartIso, currentWeekEndIso, formatDateRangeLabel };
}
