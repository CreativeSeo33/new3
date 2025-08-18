<template>
  <router-view />

  <!-- Глобальный оверлей со спиннером -->
  <div
    v-if="isGlobalLoading"
    class="fixed inset-0 z-[1100] flex items-center justify-center bg-black/15 pointer-events-none"
    aria-busy="true"
    aria-live="polite"
  >
    <Spinner />
  </div>
</template>

<script setup lang="ts">
import { isGlobalLoading } from '@admin/shared/uiLoading'
import Spinner from '@admin/ui/components/Spinner.vue'
</script>
