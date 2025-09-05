<template>
  <header class="sticky top-0 z-20 h-14 bg-white/80 backdrop-blur border-b">
    <div class="h-full px-4 sm:px-6 lg:px-8 flex items-center justify-between">
      <div class="flex items-center gap-3 min-w-0">
        <button
          class="md:hidden inline-flex items-center justify-center rounded-md p-2 text-slate-600 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500"
          aria-label="Toggle navigation"
          @click="$emit('toggle-sidebar')"
        >
          <Bars3Icon class="w-6 h-6" />
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
          <UserIcon class="w-5 h-5 text-slate-600" />
        </button>
      </div>
    </div>
  </header>
</template>

<script setup lang="ts">
import { Bars3Icon, UserIcon } from '@heroicons/vue/24/outline'

defineEmits<{ (e: 'toggle-sidebar'): void }>()
</script>

<style scoped>
</style>


