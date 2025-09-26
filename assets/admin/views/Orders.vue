<template>
  <div class="space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div class="flex items-center gap-3">
        <h1 class="text-xl font-semibold">Заказы</h1>
        <div class="text-sm text-neutral-500">Всего: <span class="font-medium text-neutral-700 dark:text-neutral-300">{{ totalItems }}</span></div>
      </div>
    </div>

    <!-- Search -->
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:gap-2">
      <input
        type="text"
        v-model="searchModel"
        placeholder="Поиск по номеру заказа…"
        class="h-9 w-full max-w-sm rounded-md border px-3 text-sm dark:border-neutral-800 dark:bg-neutral-900/40"
      />
      <input
        type="text"
        v-model="searchNameModel"
        placeholder="Поиск по имени клиента…"
        class="h-9 w-full max-w-sm rounded-md border px-3 text-sm dark:border-neutral-800 dark:bg-neutral-900/40"
      />
      <input
        type="text"
        v-model="searchPhoneModel"
        placeholder="Поиск по телефону…"
        class="h-9 w-full max-w-sm rounded-md border px-3 text-sm dark:border-neutral-800 dark:bg-neutral-900/40"
      />
      <button
        type="button"
        class="inline-flex h-9 items-center rounded-md border px-3 text-sm hover:bg-neutral-50 disabled:opacity-50 disabled:cursor-not-allowed dark:border-neutral-800 dark:hover:bg-white/10"
        :disabled="searchModel.trim() === appliedSearch && searchNameModel.trim() === appliedName && searchPhoneModel.trim() === appliedPhone"
        @click="applySearch"
      >
        Найти
      </button>
      <button
        v-if="appliedSearch || appliedName || appliedPhone"
        type="button"
        class="inline-flex h-9 items-center rounded-md border px-3 text-sm hover:bg-neutral-50 dark:border-neutral-800 dark:hover:bg-white/10"
        @click="resetSearch"
      >
        Сбросить
      </button>
    </div>

    <div v-if="state.error" class="mb-2 rounded-md border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-700">
      {{ state.error }}
    </div>

    <div class="rounded-md border overflow-x-auto">
      <table class="w-full text-sm min-w-[860px]">
        <thead class="bg-neutral-50 text-neutral-600 dark:bg-neutral-900/40 dark:text-neutral-300">
          <tr>
            <th class="px-4 py-2 text-left w-28">Номер</th>
            <th class="px-4 py-2 text-left w-64">Клиент / Город</th>
            <th class="px-4 py-2 text-left">Товары</th>
            <th class="px-4 py-2 text-left w-32">Статус</th>
            <th class="px-4 py-2 text-left w-56">
              <button type="button" class="inline-flex items-center gap-1 hover:underline" @click="toggleDateSort">
                Дата
                <span v-if="dateSort === 'desc'">↓</span>
                <span v-else-if="dateSort === 'asc'">↑</span>
              </button>
            </th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="o in orders" :key="o.id" class="border-t align-top">
            <td class="px-4 py-2 whitespace-nowrap">
              <router-link :to="{ name: 'admin-order', params: { id: o.id } }" class="hover:underline">
                {{ o.orderId }}
              </router-link>
            </td>
            <td class="px-4 py-2">
              <div class="flex flex-col leading-tight">
                <span class="font-medium">{{ [o.customer?.name, o.customer?.surname].filter(Boolean).join(' ') || '—' }}</span>
                <span class="text-neutral-500">{{ o.customer?.phone || '—' }}</span>
                <span class="text-neutral-500">{{ o.delivery?.city || '—' }}</span>
              </div>
            </td>
            <td class="px-4 py-2">
              <div class="flex flex-col gap-0.5">
                <div v-for="p in (o.products || [])" :key="p.id" class="truncate">
                  {{ p.product_name }} × {{ p.quantity }}
                </div>
                <div v-if="!o.products || o.products.length === 0" class="text-neutral-500">—</div>
              </div>
            </td>
            <td class="px-4 py-2 whitespace-nowrap">{{ formatStatus(o.status) }}</td>
            <td class="px-4 py-2 whitespace-nowrap">{{ formatDate(o.dateAdded) }}</td>
          </tr>
          <tr v-if="!loading && orders.length === 0">
            <td colspan="5" class="px-4 py-6 text-center text-neutral-500">Нет заказов</td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="flex flex-col items-center justify-between gap-3 sm:flex-row">
      <div class="text-sm text-neutral-500">
        Показано <span class="font-medium text-neutral-700 dark:text-neutral-300">{{ orders.length }}</span>
        из <span class="font-medium text-neutral-700 dark:text-neutral-300">{{ totalItems }}</span>
      </div>
      <div class="flex items-center gap-3">
        <label class="flex items-center gap-2 text-xs text-neutral-600 dark:text-neutral-300">
          <span>На странице</span>
          <select
            class="h-8 rounded-md border px-2 text-xs dark:border-neutral-800 dark:bg-neutral-900/40"
            v-model.number="itemsPerPageModel"
          >
            <option v-for="opt in ippOptions" :key="opt" :value="opt">{{ opt }}</option>
          </select>
        </label>
        <Pagination v-model="pageModel" :total-pages="totalPages" />
      </div>
    </div>
  </div>
  </template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import Pagination from '@admin/ui/components/Pagination.vue'
import { useRoute, useRouter } from 'vue-router'
import { useCrud } from '@admin/composables/useCrud'
import { OrderRepository, type OrderDto } from '@admin/repositories/OrderRepository'
import { getPaginationConfig } from '@admin/services/config'
import { OrderStatusRepository, type OrderStatusDto } from '@admin/repositories/OrderStatusRepository'

const repo = new OrderRepository()
const crud = useCrud<OrderDto>(repo)
const state = crud.state
const loading = computed(() => !!state.loading)
const totalItems = computed(() => state.totalItems)
const totalPages = computed(() => state.pagination.totalPages)
const page = computed(() => state.pagination.page)
const itemsPerPage = computed(() => state.pagination.itemsPerPage)
const orders = computed(() => (state.items as OrderDto[]) || [])

const route = useRoute()
const router = useRouter()

const pageModel = computed({
  get: () => page.value,
  set: (v: number) => {
    crud.fetchAll({
      page: v,
      itemsPerPage: itemsPerPage.value,
      sort: { dateAdded: dateSort.value },
      filters: appliedSearch ? { orderId: appliedSearch } : undefined,
    })
  }
})
const itemsPerPageModel = computed<number>({
  get: () => itemsPerPage.value,
  set: (v: number) => {
    const next = Number(v)
    // Перезапрашиваем явным образом, чтобы сохранить фильтры
    crud.fetchAll({
      itemsPerPage: next,
      page: 1,
      sort: { dateAdded: dateSort.value },
      filters: appliedSearch ? { orderId: appliedSearch } : undefined,
    })
    const nextQuery: Record<string, any> = { ...route.query, itemsPerPage: String(next), page: '1' }
    router.replace({ query: nextQuery })
  }
})
const ippOptions = ref<number[]>([])

// Кэш статусов (id -> name)
const statusRepo = new OrderStatusRepository()
const statusNameById = ref<Record<string, string>>({})
const statusFetchInProgress = new Set<string>()
const statusLoading = ref<boolean>(false)
const statusInitialized = ref<boolean>(false)

const dateSort = ref<'asc' | 'desc'>('desc')
const searchModel = ref<string>('')
const appliedSearch = ref<string>('')
const searchNameModel = ref<string>('')
const appliedName = ref<string>('')
const searchPhoneModel = ref<string>('')
const appliedPhone = ref<string>('')

onMounted(async () => {
  let defaultIpp: number | null = null
  try {
    const cfg = await getPaginationConfig('default')
    ippOptions.value = cfg.itemsPerPageOptions
    defaultIpp = cfg.defaultItemsPerPage
  } catch (_) {
    ippOptions.value = []
  }

  const rawIpp = route.query.itemsPerPage
  const rawPage = route.query.page
  const desiredIpp = Number.isFinite(Number(rawIpp)) ? Number(rawIpp) : (defaultIpp ?? undefined)
  const desiredPage = Number.isFinite(Number(rawPage)) ? Math.max(1, Number(rawPage)) : 1

  const sortDateRaw = route.query['order[dateAdded]']
  const initialDateSort = typeof sortDateRaw === 'string' ? sortDateRaw.toLowerCase() : Array.isArray(sortDateRaw) ? String(sortDateRaw[0] || '').toLowerCase() : ''
  if (initialDateSort === 'asc' || initialDateSort === 'desc') dateSort.value = initialDateSort

  const routeOrderId = typeof route.query.orderId === 'string' ? route.query.orderId : Array.isArray(route.query.orderId) ? String(route.query.orderId[0] || '') : ''
  searchModel.value = routeOrderId
  appliedSearch.value = (routeOrderId || '').trim()

  // Ранний прогрев справочника статусов (1 запрос, далее из persistent‑кэша)
  try {
    statusLoading.value = true
    const statuses = await statusRepo.findAllCached() as any
    const list: any[] = Array.isArray(statuses) ? statuses : (statuses['hydra:member'] || statuses['member'] || [])
    const map: Record<string, string> = {}
    for (const s of list as OrderStatusDto[]) {
      const name = (s as any).name
      const idProp = (s as any)['@id'] ?? s.id
      const idStr = typeof idProp === 'string' ? idProp.replace(/^.*\//, '') : String(idProp)
      const idNum = Number(idStr)
      if (typeof name === 'string') {
        if (idStr) map[idStr] = name
        if (Number.isFinite(idNum)) map[String(idNum)] = name
      }
    }
    statusNameById.value = map
  } catch (_) {}
  finally {
    statusInitialized.value = true
    statusLoading.value = false
  }

  await crud.fetchAll({
    itemsPerPage: typeof desiredIpp === 'number' ? desiredIpp : undefined,
    page: desiredPage,
    sort: { dateAdded: dateSort.value },
    filters: {
      ...(appliedSearch.value ? { orderId: appliedSearch.value } : {}),
      ...(appliedName.value ? { ['customer.name']: appliedName.value } : {}),
      ...(appliedPhone.value ? { ['customer.phone']: appliedPhone.value } : {}),
    },
  })

  const ensuredQuery: Record<string, any> = { ...route.query }
  ensuredQuery.page = String(state.pagination.page)
  ensuredQuery.itemsPerPage = String(state.pagination.itemsPerPage)
  ensuredQuery['order[dateAdded]'] = dateSort.value
  if (appliedSearch.value) ensuredQuery.orderId = appliedSearch.value
  if (appliedName.value) ensuredQuery['customer.name'] = appliedName.value
  if (appliedPhone.value) ensuredQuery['customer.phone'] = appliedPhone.value
  router.replace({ query: ensuredQuery })
})

let initialized = false
watch([page, itemsPerPage, dateSort], ([p, ipp, ds]) => {
  if (!initialized) {
    initialized = true
    return
  }
  const qPage = String(route.query.page ?? '')
  const qIpp = String(route.query.itemsPerPage ?? '')
  const qSort = String(route.query['order[dateAdded]'] ?? '')
  const qOrderId = String(route.query.orderId ?? '')
  const qName = String(route.query['customer.name'] ?? '')
  const qPhone = String(route.query['customer.phone'] ?? '')
  const nextQuery: Record<string, any> = { ...route.query }
  let changed = false
  if (qPage !== String(p)) { nextQuery.page = String(p); changed = true }
  if (qIpp !== String(ipp)) { nextQuery.itemsPerPage = String(ipp); changed = true }
  if (qSort !== String(ds)) { nextQuery['order[dateAdded]'] = String(ds); changed = true }
  if (qOrderId !== String(appliedSearch.value || '')) { if (appliedSearch.value) nextQuery.orderId = appliedSearch.value; else delete nextQuery.orderId; changed = true }
  if (qName !== String(appliedName.value || '')) { if (appliedName.value) nextQuery['customer.name'] = appliedName.value; else delete nextQuery['customer.name']; changed = true }
  if (qPhone !== String(appliedPhone.value || '')) { if (appliedPhone.value) nextQuery['customer.phone'] = appliedPhone.value; else delete nextQuery['customer.phone']; changed = true }
  if (changed) router.replace({ query: nextQuery })
})

function toggleDateSort() {
  dateSort.value = dateSort.value === 'desc' ? 'asc' : 'desc'
  crud.fetchAll({
    sort: { dateAdded: dateSort.value },
    filters: {
      ...(appliedSearch.value ? { orderId: appliedSearch.value } : {}),
      ...(appliedName.value ? { ['customer.name']: appliedName.value } : {}),
      ...(appliedPhone.value ? { ['customer.phone']: appliedPhone.value } : {}),
    },
  })
}

function applySearch() {
  const trimmed = searchModel.value.trim()
  appliedSearch.value = trimmed
  const nameTrim = searchNameModel.value.trim()
  appliedName.value = nameTrim
  const phoneTrim = searchPhoneModel.value.trim()
  appliedPhone.value = phoneTrim
  crud.fetchAll({
    page: 1,
    itemsPerPage: itemsPerPage.value,
    sort: { dateAdded: dateSort.value },
    filters: {
      ...(trimmed ? { orderId: trimmed } : {}),
      ...(nameTrim ? { ['customer.name']: nameTrim } : {}),
      ...(phoneTrim ? { ['customer.phone']: phoneTrim } : {}),
    },
  })
  const nextQuery: Record<string, any> = { ...route.query, page: '1' }
  if (trimmed) nextQuery.orderId = trimmed; else delete nextQuery.orderId
  if (nameTrim) nextQuery['customer.name'] = nameTrim; else delete nextQuery['customer.name']
  if (phoneTrim) nextQuery['customer.phone'] = phoneTrim; else delete nextQuery['customer.phone']
  router.replace({ query: nextQuery })
}

function resetSearch() {
  searchModel.value = ''
  appliedSearch.value = ''
  searchNameModel.value = ''
  appliedName.value = ''
  searchPhoneModel.value = ''
  appliedPhone.value = ''
  crud.fetchAll({
    page: 1,
    itemsPerPage: itemsPerPage.value,
    sort: { dateAdded: dateSort.value },
  })
  const nextQuery: Record<string, any> = { ...route.query, page: '1' }
  delete nextQuery.orderId
  delete nextQuery['customer.name']
  delete nextQuery['customer.phone']
  router.replace({ query: nextQuery })
}

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
  return new Intl.NumberFormat('ru-RU').format(num)
}
function formatCustomer(o: OrderDto): string {
  const c: any = (o as any).customer || null
  if (!c) return '—'
  const name = [c?.name, c?.surname].filter(Boolean).join(' ')
  const phone = c?.phone ? `, ${c.phone}` : ''
  return (name || '').trim() ? `${name}${phone}` : (phone ? phone.slice(2) : '—')
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
</script>


