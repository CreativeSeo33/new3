<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-semibold tracking-tight">Яндекс Доставка</h1>
        <p class="text-sm text-gray-500 mt-1">Загрузка пунктов самопривоза/ПВЗ из Яндекс Доставки и сохранение в БД.</p>
      </div>

      <div class="flex items-center gap-2">
        <button
          class="h-9 rounded-md bg-blue-600 px-3 text-sm text-white hover:bg-blue-700 disabled:opacity-60"
          :disabled="loading"
          @click="syncPickupPoints"
        >
          {{ loading ? 'Загрузка…' : 'Загрузить пункты выдачи' }}
        </button>
      </div>
    </div>

    <div v-if="error" class="rounded-md border border-rose-200 bg-rose-50 text-rose-800 px-4 py-3 text-sm">
      {{ error }}
    </div>

    <div class="rounded-lg border border-gray-200 bg-white">
      <div class="px-4 py-3 border-b border-gray-200 flex items-center justify-between">
        <h2 class="text-base font-medium">Отчёт о сохранении</h2>
      </div>
      <div class="px-4 py-4 text-sm">
        <div v-if="savedCount === null" class="text-gray-500">Нажмите «Загрузить пункты выдачи», чтобы выполнить синхронизацию.</div>
        <div v-else>
          <div class="text-slate-700">Сохранено пунктов: <span class="font-medium">{{ savedCount }}</span></div>
          <div v-if="lastSavedAt" class="text-gray-500 mt-1">Время: {{ lastSavedAt }}</div>
        </div>
      </div>
    </div>
  </div>
  
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { httpClient } from '../services/http'

const loading = ref(false)
const error = ref<string | null>(null)
const savedCount = ref<number | null>(null)
const lastSavedAt = ref<string | null>(null)

async function syncPickupPoints(): Promise<void> {
  loading.value = true
  error.value = null
  try {
    const resp = await httpClient.postJson<any>('/admin/yandex-delivery/pickup-points/sync', {
      payment_method: 'card_on_receipt',
      type: 'pickup_point'
    })
    const data = resp.data as any
    if (data?.ok) {
      savedCount.value = typeof data?.saved === 'number' ? data.saved : null
      lastSavedAt.value = new Date().toLocaleString()
    } else {
      error.value = 'Не удалось выполнить синхронизацию'
    }
  } catch (e: any) {
    error.value = e?.message || 'Ошибка загрузки'
  } finally {
    loading.value = false
  }
}

</script>

<style scoped>
</style>



