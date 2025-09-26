<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between gap-3">
      <div class="flex items-center gap-3">
        <h1 class="text-xl font-semibold">Заказ</h1>
        <div v-if="order" class="text-sm text-neutral-500">№ <span class="font-medium text-neutral-700 dark:text-neutral-300">{{ order.orderId }}</span></div>
      </div>
      <router-link class="inline-flex h-9 items-center rounded-md border px-3 text-sm hover:bg-neutral-50 dark:border-neutral-800 dark:hover:bg-white/10" :to="{ name: 'admin-orders' }">К списку</router-link>
    </div>

    <!-- Статус заказа: выбор и обновление -->
    <div v-if="order" class="rounded-md border p-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:gap-3">
      <label class="text-sm text-neutral-600 dark:text-neutral-300">Статус заказа</label>
      <select
        class="h-9 w-full max-w-xs rounded-md border px-2 text-sm disabled:opacity-60 dark:border-neutral-800 dark:bg-neutral-900/40"
        v-model="selectedStatusId"
        @change="onStatusChange"
        :disabled="statusChanging"
      >
        <option v-for="s in statusOptions" :key="s.id" :value="s.id">{{ s.name }}</option>
      </select>
      <div class="text-xs">
        <span v-if="statusChanging" class="text-neutral-500">Сохранение…</span>
        <span v-else-if="statusError" class="text-red-600">{{ statusError }}</span>
      </div>
    </div>

    <div v-if="error" class="rounded-md border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-700">{{ error }}</div>

    <div v-if="loading" class="flex items-center gap-2 text-sm text-neutral-600 dark:text-neutral-300">
      <span>Загрузка…</span>
    </div>

    <div v-if="order" class="grid grid-cols-1 gap-6 md:grid-cols-2">
      <!-- Клиент -->
      <div class="rounded-md border p-4">
        <h2 class="mb-2 font-semibold">Клиент</h2>
        <div class="text-sm space-y-1">
          <div>{{ customerName }}</div>
          <div class="text-neutral-500">{{ order.customer?.phone || '—' }}</div>
        </div>
      </div>

      <!-- Доставка -->
      <div class="rounded-md border p-4">
        <h2 class="mb-2 font-semibold">Доставка</h2>
        <div class="text-sm space-y-1">
          <div>Тип: {{ resolveDeliveryTypeName(order.delivery?.type) }}</div>
          <div>Город: {{ order.delivery?.city || '—' }}</div>
          <div v-if="order.delivery?.address">Адрес: {{ order.delivery?.address }}</div>
          <div v-if="order.delivery?.pvz">ПВЗ: {{ order.delivery?.pvz }}</div>
          <div>Стоимость доставки: {{ formatMoney(order.delivery?.cost ?? null) }}</div>
        </div>
      </div>

      <!-- Товары -->
      <div class="rounded-md border p-4">
        <h2 class="mb-3 font-semibold">Товары</h2>
        <table class="w-full text-sm">
          <thead class="bg-neutral-50 text-neutral-600 dark:bg-neutral-900/40 dark:text-neutral-300">
            <tr>
              <th class="px-3 py-2 text-left w-16">Фото</th>
              <th class="px-3 py-2 text-left">Товар</th>
              <th class="px-3 py-2 text-left w-24">Кол-во</th>
              <th class="px-3 py-2 text-left w-32">Цена</th>
              <th class="px-3 py-2 text-left w-32">Сумма</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="p in products" :key="p.id" class="border-t">
              <td class="px-3 py-2">
                <div class="h-12 w-12 overflow-hidden rounded bg-neutral-100">
                  <img v-if="getProductImage(p)" :src="getProductImage(p) || undefined" class="h-full w-full object-cover" alt="" />
                </div>
              </td>
              <td class="px-3 py-2">
                <router-link
                  v-if="getProductId(p)"
                  :to="{ name: 'admin-product-form', params: { id: getProductId(p) } }"
                  class="hover:underline"
                >
                  {{ p.product_name || '—' }}
                </router-link>
                <span v-else>{{ p.product_name || '—' }}</span>
                <a
                  v-if="getProductSlug(p)"
                  :href="'/product/' + getProductSlug(p)"
                  target="_blank"
                  rel="noopener"
                  class="ml-2 text-xs hover:underline"
                >
                  товар в каталоге
                </a>
              </td>
              <td class="px-3 py-2">{{ p.quantity }}</td>
              <td class="px-3 py-2">{{ formatMoney(p.price ?? p.salePrice) }}</td>
              <td class="px-3 py-2">{{ formatMoney((p.price ?? p.salePrice) ? (p.quantity * Number(p.price ?? p.salePrice)) : null) }}</td>
            </tr>
            <tr v-if="products.length === 0">
              <td colspan="4" class="px-3 py-4 text-center text-neutral-500">Позиции отсутствуют</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Итого -->
      <div class="rounded-md border p-4">
        <h2 class="mb-2 font-semibold">Итого</h2>
        <div class="text-sm">
          <div class="flex justify-between py-1"><span class="text-neutral-500">Товары</span><span>{{ formatMoney(itemsSubtotal) }}</span></div>
          <div class="flex justify-between py-1"><span class="text-neutral-500">Доставка</span><span>{{ formatMoney(shippingCost) }}</span></div>
          <div class="flex justify-between py-1 border-t mt-2 pt-2"><span class="font-medium">Итого</span><span class="font-medium">{{ formatMoney(orderTotal) }}</span></div>
        </div>
      </div>
    </div>
    
    <!-- Удаление заказа -->
    <div v-if="order" class="flex items-center justify-between gap-3">
      <div class="text-sm text-red-600" v-if="deleteError">{{ deleteError }}</div>
      <div class="flex-1" />
      <button
        type="button"
        class="inline-flex h-9 items-center rounded-md border px-3 text-sm text-white bg-red-600 hover:bg-red-700 disabled:opacity-60 dark:border-neutral-800"
        :disabled="deleting"
        @click="openConfirm"
      >
        Удалить заказ
      </button>
    </div>

    <!-- Confirm Delete Modal -->
    <Modal
      v-model="confirmOpen"
      title="Удалить заказ"
      size="sm"
      :closable="!deleting"
      :closeOnBackdrop="!deleting"
      :closeOnEsc="!deleting"
    >
      <p class="text-sm text-neutral-700 dark:text-neutral-300">
        Вы действительно хотите удалить заказ № {{ order?.orderId }}? Это действие необратимо.
      </p>
      <template #footer>
        <div class="flex items-center justify-end gap-2">
          <button
            type="button"
            class="inline-flex h-9 items-center rounded-md border px-3 text-sm hover:bg-neutral-50 disabled:opacity-60 dark:border-neutral-800 dark:hover:bg-white/10"
            :disabled="deleting"
            @click="confirmOpen = false"
          >
            Отмена
          </button>
          <button
            type="button"
            class="inline-flex h-9 items-center rounded-md border px-3 text-sm text-white bg-red-600 hover:bg-red-700 disabled:opacity-60 dark:border-neutral-800"
            :disabled="deleting"
            @click="confirmDelete"
          >
            {{ deleting ? 'Удаление…' : 'Удалить' }}
          </button>
        </div>
      </template>
    </Modal>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { OrderRepository, type OrderDto } from '@admin/repositories/OrderRepository'
import { OrderStatusRepository, type OrderStatusDto } from '@admin/repositories/OrderStatusRepository'
import { ProductRepository } from '@admin/repositories/ProductRepository'
import { MediaRepository } from '@admin/repositories/MediaRepository'
import Modal from '@admin/ui/components/Modal.vue'
import { DeliveryTypeRepository, type DeliveryType } from '@admin/repositories/DeliveryTypeRepository'

const route = useRoute()
const router = useRouter()
const repo = new OrderRepository()
const statusRepo = new OrderStatusRepository()
const productRepo = new ProductRepository()
const mediaRepo = new MediaRepository()
const deliveryTypeRepo = new DeliveryTypeRepository()

const order = ref<OrderDto | null>(null)
const loading = ref<boolean>(false)
const error = ref<string>('')

const statusNameById = ref<Record<string, string>>({})
const statusOptions = ref<Array<{ id: string; name: string }>>([])
const selectedStatusId = ref<string>('')
const statusChanging = ref<boolean>(false)
const statusError = ref<string>('')
const productSlugById = ref<Record<string, string>>({})
const productImageById = ref<Record<string, string>>({})
const deliveryTypeNameByCode = ref<Record<string, string>>({})

const products = computed(() => order.value?.products || [])
const itemsSubtotal = computed<number>(() => {
  const list = products.value
  if (!Array.isArray(list) || list.length === 0) return 0
  return list.reduce((sum, p: any) => {
    const unit = Number((p?.price ?? p?.salePrice) ?? 0)
    const qty = Number(p?.quantity ?? 0)
    if (!Number.isFinite(unit) || !Number.isFinite(qty)) return sum
    return sum + unit * qty
  }, 0)
})
const shippingCost = computed<number>(() => Number(order.value?.delivery?.cost ?? 0))
const orderTotal = computed<number>(() => {
  // если бэкенд прислал готовое total — используем его, иначе считаем
  const backendTotal = order.value?.total
  if (Number.isFinite(Number(backendTotal))) return Number(backendTotal)
  return itemsSubtotal.value + shippingCost.value
})

const deleting = ref<boolean>(false)
const deleteError = ref<string>('')
const confirmOpen = ref<boolean>(false)

function openConfirm() {
  if (!order.value) return
  deleteError.value = ''
  confirmOpen.value = true
}

async function confirmDelete() {
  if (!order.value) return
  deleting.value = true
  deleteError.value = ''
  try {
    await repo.delete(order.value.id)
    router.push({ name: 'admin-orders' })
  } catch (e: any) {
    deleteError.value = e?.message || 'Не удалось удалить заказ'
  } finally {
    deleting.value = false
    confirmOpen.value = false
  }
}
const customerName = computed(() => {
  const c = order.value?.customer
  if (!c) return '—'
  return [c.name, c.surname].filter(Boolean).join(' ') || '—'
})

onMounted(async () => {
  const idParam = route.params.id
  const id = Array.isArray(idParam) ? idParam[0] : idParam
  if (!id) return
  loading.value = true
  try {
    // Прогреваем справочник статусов (попробуем из persistent‑кэша)
    try {
      const statuses = await statusRepo.findAllCached() as any
      const list: any[] = Array.isArray(statuses) ? statuses : (statuses['hydra:member'] || statuses['member'] || [])
      const map: Record<string, string> = {}
      const opts: Array<{ id: string; name: string }> = []
      for (const s of list as OrderStatusDto[]) {
        const name = (s as any).name
        const idProp = (s as any)['@id'] ?? s.id
        const idStr = typeof idProp === 'string' ? idProp.replace(/^.*\//, '') : String(idProp)
        const idNum = Number(idStr)
        if (typeof name === 'string') {
          if (idStr) map[idStr] = name
          if (Number.isFinite(idNum)) map[String(idNum)] = name
          if (idStr) opts.push({ id: idStr, name })
          else if (Number.isFinite(idNum)) opts.push({ id: String(idNum), name })
        }
      }
      statusNameById.value = map
      statusOptions.value = opts
    } catch (_) {}

    order.value = await repo.findById(String(id))
    selectedStatusId.value = order.value?.status != null ? String(order.value.status) : ''

    // Загрузим slug и первую картинку для продуктов заказа
    try {
      const ids = Array.from(new Set((order.value?.products || []).map((p: any) => getProductId(p)).filter(Boolean))) as string[]
      await Promise.all(ids.map(async (pid) => {
        try {
          const product = await productRepo.findById(pid)
          const slug = (product as any)?.slug
          if (slug) productSlugById.value[String(pid)] = String(slug)
          const firstImageUrl = (product as any)?.firstImageUrl || (Array.isArray((product as any)?.image) && (product as any).image[0]?.imageUrl)
          if (firstImageUrl) productImageById.value[String(pid)] = String(firstImageUrl)
        } catch (_) { /* ignore individual errors */ }
      }))
      // Доп. попытка: для тех, у кого нет картинки, возьмём первое фото через MediaRepository
      const missing = ids.filter((pid) => !productImageById.value[pid])
      if (missing.length > 0) {
        await Promise.all(missing.map(async (pid) => {
          try {
            const items = await mediaRepo.fetchProductImages(pid)
            const first = (items || []).sort((a, b) => (a.sortOrder ?? 0) - (b.sortOrder ?? 0))[0]
            if (first?.imageUrl) productImageById.value[String(pid)] = String(first.imageUrl)
          } catch (_) {}
        }))
      }
    } catch (_) {}

    // Загрузим и раскешируем типы доставки: code -> name
    try {
      const types = await deliveryTypeRepo.findAllCached() as any
      const list: any[] = Array.isArray(types) ? types : (types['hydra:member'] || types['member'] || [])
      const map: Record<string, string> = {}
      for (const t of list as DeliveryType[]) {
        const code = (t as any).code
        const name = (t as any).name
        if (code && name) map[String(code)] = String(name)
      }
      deliveryTypeNameByCode.value = map
    } catch (_) {}
  } catch (e: any) {
    error.value = e?.message || 'Не удалось загрузить заказ'
  } finally {
    loading.value = false
  }
})

function pad2(n: number): string { return n < 10 ? `0${n}` : String(n) }
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
function formatMoney(v: number | null | undefined): string {
  if (v == null) return '—'
  const num = Number(v)
  if (!Number.isFinite(num)) return String(v)
  return `${new Intl.NumberFormat('ru-RU').format(num)} руб.`
}
function formatStatus(id: number | string | null | undefined): string {
  if (id == null || id === '') return '—'
  const byStr = statusNameById.value[String(id)]
  if (byStr) return byStr
  const idNum = Number(id)
  if (Number.isFinite(idNum)) {
    const byNum = statusNameById.value[String(idNum)]
    if (byNum) return byNum
  }
  return String(id)
}

function getProductId(p: any): string | null {
  const id = (p?.product_id ?? p?.productId ?? null)
  if (id == null) return null
  const n = Number(id)
  return Number.isFinite(n) ? String(n) : String(id)
}

function getProductSlug(p: any): string | null {
  const id = getProductId(p)
  if (!id) return null
  return productSlugById.value[id] || null
}

function resolveDeliveryTypeName(code: string | null | undefined): string {
  const c = (code ?? '').toString().trim()
  if (!c) return '—'
  return deliveryTypeNameByCode.value[c] || c
}

function getProductImage(p: any): string | null {
  const id = getProductId(p)
  if (!id) return null
  const inline = (p?.firstImageUrl ?? p?.imageUrl ?? null)
  if (inline) return String(inline)
  const byCache = productImageById.value[id]
  return byCache || null
}

async function onStatusChange() {
  if (!order.value) return
  const newId = selectedStatusId.value
  if (!newId) return
  statusError.value = ''
  statusChanging.value = true
  try {
    const updated = await repo.partialUpdate(order.value.id, { status: Number(newId) } as Partial<OrderDto>)
    order.value = { ...order.value, ...updated }
  } catch (e: any) {
    statusError.value = e?.message || 'Не удалось обновить статус'
    // откатим селект на текущее значение
    selectedStatusId.value = order.value.status != null ? String(order.value.status) : ''
  } finally {
    statusChanging.value = false
  }
}
</script>



