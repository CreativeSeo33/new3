<template>
  <div class="p-6 space-y-6">
    <h2 class="text-2xl font-bold">Heroicons Примеры</h2>

    <!-- Outline иконки -->
    <div class="space-y-4">
      <h3 class="text-lg font-semibold">Outline стиль</h3>
      <div class="flex flex-wrap gap-4">
        <div class="flex flex-col items-center gap-2">
          <HomeIcon class="w-8 h-8 text-blue-600" />
          <span class="text-sm">HomeIcon</span>
        </div>
        <div class="flex flex-col items-center gap-2">
          <HeartIcon class="w-8 h-8 text-red-500" />
          <span class="text-sm">HeartIcon</span>
        </div>
        <div class="flex flex-col items-center gap-2">
          <StarIcon class="w-8 h-8 text-yellow-500" />
          <span class="text-sm">StarIcon</span>
        </div>
        <div class="flex flex-col items-center gap-2">
          <CogIcon class="w-8 h-8 text-gray-600" />
          <span class="text-sm">CogIcon</span>
        </div>
      </div>
    </div>

    <!-- Solid иконки -->
    <div class="space-y-4">
      <h3 class="text-lg font-semibold">Solid стиль</h3>
      <div class="flex flex-wrap gap-4">
        <div class="flex flex-col items-center gap-2">
          <HomeIconSolid class="w-8 h-8 text-blue-600" />
          <span class="text-sm">HomeIcon</span>
        </div>
        <div class="flex flex-col items-center gap-2">
          <HeartIconSolid class="w-8 h-8 text-red-500" />
          <span class="text-sm">HeartIcon</span>
        </div>
        <div class="flex flex-col items-center gap-2">
          <StarIconSolid class="w-8 h-8 text-yellow-500" />
          <span class="text-sm">StarIcon</span>
        </div>
        <div class="flex flex-col items-center gap-2">
          <CogIconSolid class="w-8 h-8 text-gray-600" />
          <span class="text-sm">CogIcon</span>
        </div>
      </div>
    </div>

    <!-- Размеры -->
    <div class="space-y-4">
      <h3 class="text-lg font-semibold">Размеры</h3>
      <div class="flex items-end gap-4">
        <HomeIcon class="w-4 h-4 text-blue-600" />
        <HomeIcon class="w-6 h-6 text-blue-600" />
        <HomeIcon class="w-8 h-8 text-blue-600" />
        <HomeIcon class="w-12 h-12 text-blue-600" />
      </div>
      <p class="text-sm text-gray-600">w-4 h-4, w-6 h-6, w-8 h-8, w-12 h-12</p>
    </div>

    <!-- Интерактивные примеры -->
    <div class="space-y-4">
      <h3 class="text-lg font-semibold">Интерактивные примеры</h3>
      <div class="flex gap-4">
        <button class="flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition-colors">
          <PlusIcon class="w-5 h-5" />
          Добавить
        </button>
        <button class="flex items-center gap-2 px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700 transition-colors">
          <TrashIcon class="w-5 h-5" />
          Удалить
        </button>
        <button class="flex items-center gap-2 px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 transition-colors">
          <CheckIcon class="w-5 h-5" />
          Сохранить
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
// Outline иконки
import {
  HomeIcon,
  HeartIcon,
  StarIcon,
  CogIcon,
  PlusIcon,
  TrashIcon,
  CheckIcon
} from '@heroicons/vue/24/outline'

// Solid иконки
import {
  HomeIcon as HomeIconSolid,
  HeartIcon as HeartIconSolid,
  StarIcon as StarIconSolid,
  CogIcon as CogIconSolid
} from '@heroicons/vue/24/solid'
</script>

<style scoped>
/* Дополнительные стили если нужны */
</style>
