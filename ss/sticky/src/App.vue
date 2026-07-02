<script setup>
import { ref } from 'vue'
import StickyBoard from './components/StickyBoard.vue'
import GeneralFaitsTableView from './components/GeneralFaitsTableView.vue'

/** 'board' = vue cartes (root), 'general' = vue tableau */
const appView = ref('board')

function onSetView(mode) {
  if (mode === 'board' || mode === 'general') appView.value = mode
}
</script>

<template>
  <StickyBoard
    v-if="appView === 'board'"
    @set-view="onSetView"
  />
  <GeneralFaitsTableView
    v-else
    @set-view="onSetView"
  />
</template>
