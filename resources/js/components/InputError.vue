<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    message?: string | string[];
}>();

const text = computed(() => {
    const m = props.message;
    if (m == null || m === '') {
        return '';
    }
    return Array.isArray(m) ? m.filter(Boolean).join(' ') : m;
});
</script>

<template>
    <div v-show="text">
        <p class="text-sm text-red-600 dark:text-red-500">
            {{ text }}
        </p>
    </div>
</template>
