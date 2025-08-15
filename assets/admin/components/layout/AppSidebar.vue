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
        to="/categories"
        class="flex items-center gap-3 rounded-md px-3 py-2 text-sm hover:bg-gray-100"
        active-class="bg-gray-100 text-slate-900"
      >
        <span class="inline-block w-1.5 h-1.5 rounded-full bg-cyan-500"></span>
        Категории
      </router-link>
      <router-link
        to="/attributes"
        class="flex items-center gap-3 rounded-md px-3 py-2 text-sm hover:bg-gray-100"
        active-class="bg-gray-100 text-slate-900"
      >
        <span class="inline-block w-1.5 h-1.5 rounded-full bg-violet-500"></span>
        Атрибуты
      </router-link>
      <router-link
        to="/attribute-groups"
        class="flex items-center gap-3 rounded-md px-3 py-2 text-sm hover:bg-gray-100"
        active-class="bg-gray-100 text-slate-900"
      >
        <span class="inline-block w-1.5 h-1.5 rounded-full bg-fuchsia-500"></span>
        Группы атрибутов
      </router-link>
      <router-link
        to="/options"
        class="flex items-center gap-3 rounded-md px-3 py-2 text-sm hover:bg-gray-100"
        active-class="bg-gray-100 text-slate-900"
      >
        <span class="inline-block w-1.5 h-1.5 rounded-full bg-rose-500"></span>
        Опции
      </router-link>
      <router-link
        to="/design-system"
        class="flex items-center gap-3 rounded-md px-3 py-2 text-sm hover:bg-gray-100"
        active-class="bg-gray-100 text-slate-900"
      >
        <span class="inline-block w-1.5 h-1.5 rounded-full bg-amber-500"></span>
        Design System
      </router-link>
      <router-link
        to="/option-values"
        class="flex items-center gap-3 rounded-md px-3 py-2 text-sm hover:bg-gray-100"
        active-class="bg-gray-100 text-slate-900"
      >
        <span class="inline-block w-1.5 h-1.5 rounded-full bg-sky-500"></span>
        Значения опций
      </router-link>
      <router-link
        to="/users"
        class="flex items-center gap-3 rounded-md px-3 py-2 text-sm hover:bg-gray-100"
        active-class="bg-gray-100 text-slate-900"
      >
        <span class="inline-block w-1.5 h-1.5 rounded-full bg-slate-500"></span>
        Пользователи
      </router-link>
      <router-link
        to="/cities"
        class="flex items-center gap-3 rounded-md px-3 py-2 text-sm hover:bg-gray-100"
        active-class="bg-gray-100 text-slate-900"
      >
        <span class="inline-block w-1.5 h-1.5 rounded-full bg-lime-500"></span>
        Города
      </router-link>
      <router-link
        to="/pvz-points"
        class="flex items-center gap-3 rounded-md px-3 py-2 text-sm hover:bg-gray-100"
        active-class="bg-gray-100 text-slate-900"
      >
        <span class="inline-block w-1.5 h-1.5 rounded-full bg-orange-500"></span>
        Города Цены
      </router-link>
      <router-link
        to="/pvz-prices"
        class="flex items-center gap-3 rounded-md px-3 py-2 text-sm hover:bg-gray-100"
        active-class="bg-gray-100 text-slate-900"
      >
        <span class="inline-block w-1.5 h-1.5 rounded-full bg-orange-400"></span>
        PVZ Цены
      </router-link>
      <router-link
        to="/delivery-types"
        class="flex items-center gap-3 rounded-md px-3 py-2 text-sm hover:bg-gray-100"
        active-class="bg-gray-100 text-slate-900"
      >
        <span class="inline-block w-1.5 h-1.5 rounded-full bg-teal-500"></span>
        Типы доставок
      </router-link>
    </nav>
  </div>
</template>

<script setup lang="ts">
defineProps<{ open: boolean }>();
</script>

<style scoped>
</style>


