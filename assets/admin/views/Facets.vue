<template>
  <div class="space-y-4">
    <h1 class="text-lg font-semibold">Фасетный фильтр</h1>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <div class="md:col-span-1 space-y-3">
        <label class="block text-sm">Категория</label>
        <select v-model="selected" class="border rounded px-2 py-1 w-full">
          <option :value="null">Глобально</option>
          <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name ?? ('#' + c.id) }}</option>
        </select>

        <div class="flex gap-2">
          <button class="px-3 py-1 bg-emerald-600 text-white rounded" @click="handleSave">Сохранить</button>
          <button class="px-3 py-1 bg-sky-600 text-white rounded" @click="handleReindex">Перестроить справочники</button>
          <button class="px-3 py-1 bg-gray-200 rounded" @click="handleReset">Сбросить</button>
        </div>
      </div>

      <div class="md:col-span-2 space-y-4">
        <div>
          <h2 class="font-medium mb-2">Доступные (из словаря)</h2>
          <pre class="bg-gray-50 border rounded p-2 text-xs overflow-auto" v-if="available">{{ available }}</pre>
        </div>

        <div>
          <h2 class="font-medium mb-2">Конфигурация</h2>
          <pre class="bg-gray-50 border rounded p-2 text-xs overflow-auto" v-if="config">{{ config }}</pre>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import { FacetRepository, type FacetAvailableDto, type FacetConfigDto } from '@admin/repositories/FacetRepository'
import { httpClient } from '@admin/services/http'

const repo = new FacetRepository()
const selected = ref<number|null>(null)
const available = ref<FacetAvailableDto|null>(null)
const config = ref<FacetConfigDto|null>(null)
const categories = ref<Array<{ id: number; name: string|null }>>([])

async function loadCategories() {
  const res = await httpClient.get('/admin/categories/tree')
  // Плоский список из дерева
  const flat: Array<{ id: number; name: string|null }> = []
  function walk(nodes: any[]) {
    for (const n of nodes) { flat.push({ id: n.id, name: n.name }); if (n.children) walk(n.children) }
  }
  walk(res.tree)
  categories.value = flat
}

async function loadAll() {
  available.value = await repo.getAvailable(selected.value)
  config.value = await repo.getConfig(selected.value === null ? 'global' : selected.value)
}

async function handleSave() {
  if (!config.value) return
  await repo.saveConfig({ ...config.value, categoryId: selected.value })
}

async function handleReindex() {
  await repo.reindex(selected.value ?? 'all')
}

function handleReset() {
  void loadAll()
}

onMounted(async () => {
  await loadCategories()
  await loadAll()
})

watch(selected, async () => { await loadAll() })
</script>

<style scoped>
</style>


