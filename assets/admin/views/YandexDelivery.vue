<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-semibold tracking-tight">Яндекс Доставка</h1>
        <p class="text-sm text-gray-500 mt-1">Получение доступных пунктов самопривоза/ПВЗ из Яндекс Доставки.</p>
      </div>

      <div class="flex items-center gap-2">
        <button
          class="h-9 rounded-md bg-blue-600 px-3 text-sm text-white hover:bg-blue-700 disabled:opacity-60"
          :disabled="loading"
          @click="loadPickupPoints"
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
        <h2 class="text-base font-medium">Первые 10 пунктов</h2>
        <span class="text-xs text-gray-500">Всего: {{ totalText }}</span>
      </div>
      <div class="divide-y divide-gray-100">
        <div v-if="points.length === 0" class="px-4 py-6 text-sm text-gray-500">Нет данных. Нажмите «Загрузить пункты выдачи».</div>
        <div v-for="(p, idx) in points.slice(0, 10)" :key="idx" class="px-4 py-3 text-sm">
          <div class="font-medium">{{ p.name || p.ID || p.id || 'ПВЗ' }}</div>
          <div class="text-gray-500">
            {{ formatAddress(p) }}
          </div>
        </div>
      </div>
    </div>
  </div>
  
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { httpClient } from '../services/http'

type YandexPickupPoint = Record<string, any>

const loading = ref(false)
const error = ref<string | null>(null)
const points = ref<YandexPickupPoint[]>([])
const totalText = ref<string>('-')

async function loadPickupPoints(): Promise<void> {
  loading.value = true
  error.value = null
  try {
    const resp = await httpClient.postJson<any>('/admin/yandex-delivery/pickup-points/list', {
      payment_method: 'card_on_receipt',
      type: 'pickup_point'
    })
    const data = resp.data as any
    // Ответ Яндекса содержит массив в поле `points`
    const list: YandexPickupPoint[] = Array.isArray(data?.points) ? data.points : []
    points.value = list
    totalText.value = String(list.length)
  } catch (e: any) {
    error.value = e?.message || 'Ошибка загрузки'
  } finally {
    loading.value = false
  }
}

function formatAddress(p: any): string {
  const a = p?.address || p?.location || {}
  const city = a.locality || a.city || ''
  const street = a.street || ''
  const house = a.house || ''
  const full = a.full_address || [city, street, house].filter(Boolean).join(', ')
  return full || '—'
}

</script>

<style scoped>
</style>



