<template>
  <div class="space-y-4">
    <h1 class="text-xl font-semibold">Последние заказы</h1>
    <div v-if="error" class="mb-2 rounded-md border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-700">
      {{ error }}
    </div>
    <div class="rounded-md border overflow-x-auto">
      <table class="w-full text-sm min-w-[640px]">
        <thead class="bg-neutral-50 text-neutral-600">
          <tr>
            <th class="px-4 py-2 text-left w-40">Номер</th>
            <th class="px-4 py-2 text-left">Покупатель — Город</th>
            <th class="px-4 py-2 text-left w-40">Итого</th>
            <th class="px-4 py-2 text-left w-56">Дата</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="o in items" :key="o.id" class="border-t">
            <td class="px-4 py-2 whitespace-nowrap">
              <router-link :to="{ name: 'admin-order', params: { id: o.id } }" class="hover:underline">
                {{ o.orderId }}
              </router-link>
            </td>
            <td class="px-4 py-2">
              <span class="font-medium">{{ formatName(o) }}</span>
              <span v-if="formatCity(o)" class="text-neutral-500"> — {{ formatCity(o) }}</span>
            </td>
            <td class="px-4 py-2 whitespace-nowrap">{{ formatMoney(calcTotal(o)) }} руб.</td>
            <td class="px-4 py-2 whitespace-nowrap">{{ formatDate(o.dateAdded) }}</td>
          </tr>
          <tr v-if="!loading && items.length === 0">
            <td colspan="4" class="px-4 py-6 text-center text-neutral-500">Нет заказов</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { OrderRepository, type OrderDto } from '../repositories/OrderRepository'

const repo = new OrderRepository()
const items = ref<OrderDto[]>([])
const loading = ref<boolean>(false)
const error = ref<string>('')

onMounted(async () => {
  loading.value = true
  try {
    const data: any = await repo.findAll({ itemsPerPage: 10, sort: { dateAdded: 'desc' } })
    const list: OrderDto[] = Array.isArray(data) ? (data as OrderDto[]) : (data?.['hydra:member'] || data?.member || [])
    items.value = list || []
  } catch (e: any) {
    error.value = e?.message || 'Не удалось загрузить заказы'
  } finally {
    loading.value = false
  }
})

function pad2(n: number): string {
  return n < 10 ? `0${n}` : String(n)
}

function formatDate(iso: string | null | undefined): string {
  if (!iso) return '—'
  const d = new Date(iso)
  if (isNaN(d.getTime())) return String(iso)
  const yyyy = d.getFullYear()
  const mm = pad2(d.getMonth() + 1)
  const dd = pad2(d.getDate())
  const hh = pad2(d.getHours())
  const mi = pad2(d.getMinutes())
  return `${yyyy}-${mm}-${dd} ${hh}:${mi}`
}

function formatName(o: OrderDto): string {
  const c = o?.customer || null
  if (!c) return '—'
  return [c.name, c.surname].filter(Boolean).join(' ') || '—'
}

function formatCity(o: OrderDto): string {
  return o?.delivery?.city || ''
}

function formatMoney(v: number | null | undefined): string {
  if (v == null) return '—'
  const num = Number(v)
  if (!Number.isFinite(num)) return String(v)
  return new Intl.NumberFormat('ru-RU').format(num)
}

function calcTotal(o: OrderDto): number {
  const backend = Number((o as any)?.total)
  if (Number.isFinite(backend)) return backend
  const list = (o?.products || []) as any[]
  if (!Array.isArray(list) || list.length === 0) return 0
  return list.reduce((sum, p: any) => {
    const unit = Number((p?.salePrice ?? p?.price) ?? 0)
    const qty = Number(p?.quantity ?? 0)
    if (!Number.isFinite(unit) || !Number.isFinite(qty)) return sum
    return sum + unit * qty
  }, 0)
}
</script>

<style scoped>
</style>


