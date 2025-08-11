<template>
  <div class="flex min-h-screen bg-gray-50 text-slate-700">
    <!-- Sidebar -->
    <div
      class="fixed inset-y-0 left-0 z-40 w-64 transform bg-white border-r shadow-sm transition-transform duration-200 md:translate-x-0"
      :class="{ '-translate-x-full': !isSidebarOpen }"
    >
      <div class="h-14 flex items-center border-b px-4">
        <span class="text-base font-semibold">Admin</span>
      </div>
      <nav class="p-3 space-y-1">
        <router-link
          to="/dashboard"
          class="flex items-center gap-3 rounded-md px-3 py-2 text-sm hover:bg-gray-100"
          active-class="bg-gray-100 text-slate-900"
        >
          <span class="inline-block w-1.5 h-1.5 rounded-full bg-blue-500"></span>
          Дашборд
        </router-link>
        <router-link
          to="/products"
          class="flex items-center gap-3 rounded-md px-3 py-2 text-sm hover:bg-gray-100"
          active-class="bg-gray-100 text-slate-900"
        >
          <span class="inline-block w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
          Товары
        </router-link>
        <router-link
          to="/design-system"
          class="flex items-center gap-3 rounded-md px-3 py-2 text-sm hover:bg-gray-100"
          active-class="bg-gray-100 text-slate-900"
        >
          <span class="inline-block w-1.5 h-1.5 rounded-full bg-amber-500"></span>
          Design System
        </router-link>
      </nav>
    </div>

    <!-- Overlay for mobile -->
    <div
      class="fixed inset-0 z-30 bg-black/40 backdrop-blur-sm md:hidden"
      v-if="isSidebarOpen"
      @click="isSidebarOpen = false"
    />

    <!-- Main area -->
    <div class="flex-1 flex flex-col md:pl-64 min-w-0">
      <!-- Topbar -->
      <header class="sticky top-0 z-20 h-14 bg-white/80 backdrop-blur border-b">
        <div class="h-full px-4 sm:px-6 lg:px-8 flex items-center justify-between">
          <div class="flex items-center gap-3 min-w-0">
            <button
              class="md:hidden inline-flex items-center justify-center rounded-md p-2 text-slate-600 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500"
              aria-label="Toggle navigation"
              @click="isSidebarOpen = !isSidebarOpen"
            >
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
              </svg>
            </button>
            <div class="truncate">
              <div class="font-semibold truncate">Админ-панель</div>
              <div class="hidden sm:flex items-center gap-2 text-xs text-slate-500">
                <span>Главная</span>
                <span>/</span>
                <span class="truncate">Dashboard</span>
              </div>
            </div>
          </div>

          <div class="flex items-center gap-2">
            <div class="relative hidden sm:block">
              <input type="search" placeholder="Поиск..." class="w-56 rounded-md border border-slate-200 bg-white px-3 h-9 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500" />
            </div>
            <router-link to="/design-system" class="h-9 px-3 rounded-md bg-blue-600 text-white text-sm hover:bg-blue-700">DS</router-link>
            <button class="h-9 w-9 grid place-items-center rounded-full border border-slate-200 hover:bg-gray-50">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-slate-600">
                <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0112 20.25a8.967 8.967 0 01-8.312-4.478 23.848 23.848 0 005.454 1.31m5.715 0a24.255 24.255 0 01-5.715 0m5.715 0a3 3 0 10-5.715 0" />
              </svg>
            </button>
          </div>
        </div>
      </header>

      <!-- Content -->
      <main class="px-4 sm:px-6 lg:px-8 py-6">
        <router-view />
      </main>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import { useRoute } from 'vue-router'

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


