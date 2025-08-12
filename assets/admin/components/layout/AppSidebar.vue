<template>
  <div
    class="fixed inset-y-0 left-0 z-40 w-64 transform bg-white border-r shadow-sm transition-transform duration-200 md:translate-x-0"
    :class="{ '-translate-x-full': !open }"
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
</template>

<script setup lang="ts">
defineProps<{ open: boolean }>();
</script>

<style scoped>
</style>


