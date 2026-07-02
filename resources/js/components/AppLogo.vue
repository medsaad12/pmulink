<script setup lang="ts">
const logoSrc = '/logos/logo_lph.png';
</script>

<template>
    <div
        class="flex w-full min-w-0 items-center justify-center group-data-[collapsible=icon]:justify-center"
    >
        <div
            class="inline-flex max-w-full items-center gap-1.5 group-data-[collapsible=icon]:gap-0"
        >
            <img
                :src="logoSrc"
                alt="PMOLINK"
                class="h-5 w-auto max-h-5 shrink-0 object-contain object-center group-data-[collapsible=icon]:h-5 group-data-[collapsible=icon]:w-5 group-data-[collapsible=icon]:max-w-none"
            />
            <span
                class="shrink-0 text-base font-semibold leading-none tracking-tight group-data-[collapsible=icon]:hidden"
                >PMOLINK</span
            >
        </div>
    </div>
</template>
