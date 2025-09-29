<template>
  <div class="space-y-4">
    <h1 class="text-xl font-semibold">Поиск по товарам</h1>

    <div class="flex items-center gap-3">
      <button
        type="button"
        class="inline-flex h-9 items-center rounded-md bg-indigo-600 px-3 text-sm font-medium text-white shadow hover:bg-indigo-700 disabled:opacity-60 disabled:cursor-not-allowed"
        :disabled="loading"
        @click="handleReindex"
      >
        {{ loading ? 'Индексируем…' : 'Индексировать товары' }}
      </button>
      <span v-if="lastMessage" class="text-sm text-slate-600">{{ lastMessage }}</span>
    </div>

    <!-- Toasts -->
    <ToastRoot v-for="n in toastCount" :key="n" type="foreground" :duration="2600">
      <ToastDescription>{{ lastToast }}</ToastDescription>
    </ToastRoot>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { ToastDescription, ToastRoot } from 'reka-ui'
import { SearchRepository } from '../repositories/SearchRepository'

const repo = new SearchRepository()
const loading = ref(false)
const lastMessage = ref('')

const toastCount = ref(0)
const lastToast = ref('')
function publishToast(msg: string) {
  lastToast.value = msg
  toastCount.value += 1
}

async function handleReindex() {
  if (loading.value) return
  loading.value = true
  lastMessage.value = ''
  try {
    const res = await repo.reindexProducts()
    const cnt = res?.count ?? 0
    const sec = res?.seconds ?? 0
    lastMessage.value = `Готово: переиндексировано ${cnt} товаров за ${sec}s`
    publishToast('✅ Индексация запущена')
  } catch (e: any) {
    const msg = e?.message || 'Не удалось запустить индексацию'
    lastMessage.value = msg
    publishToast(`❌ ${msg}`)
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
</style>



