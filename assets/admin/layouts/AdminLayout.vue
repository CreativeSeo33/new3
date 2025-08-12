<template>
  <div class="flex min-h-screen bg-gray-50 text-slate-700">
    <!-- Sidebar -->
    <AppSidebar :open="isSidebarOpen" />

    <!-- Overlay for mobile -->
    <div
      class="fixed inset-0 z-30 bg-black/40 backdrop-blur-sm md:hidden"
      v-if="isSidebarOpen"
      @click="isSidebarOpen = false"
    />

    <!-- Main area -->
    <div class="flex-1 flex flex-col md:pl-64 min-w-0">
      <AppHeader @toggle-sidebar="isSidebarOpen = !isSidebarOpen" />
      <main class="px-4 sm:px-6 lg:px-8 py-6">
        <router-view />
      </main>
      <AppFooter />
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import AppHeader from '@admin/components/layout/AppHeader.vue'
import AppSidebar from '@admin/components/layout/AppSidebar.vue'
import AppFooter from '@admin/components/layout/AppFooter.vue'

const isSidebarOpen = ref(false)
const route = useRoute()

watch(
  () => route.fullPath,
  () => {
    // Закрываем мобильный сайдбар после навигации
    isSidebarOpen.value = false
  }
)
</script>

<style scoped>
</style>


