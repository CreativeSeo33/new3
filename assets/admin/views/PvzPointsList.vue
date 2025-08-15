<template>
  <div class="space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div class="flex items-center gap-3">
        <h1 class="text-xl font-semibold">ПВЗ — точки</h1>
        <div class="text-sm text-neutral-500">Всего: <span class="font-medium text-neutral-700 dark:text-neutral-300">{{ totalItems }}</span></div>
      </div>
      <div class="flex items-center gap-2">
        <Input v-model="search" placeholder="Поиск по городу" class="w-64" />
      </div>
    </div>

    <div v-if="state.error" class="mb-2 rounded-md border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-700">
      {{ state.error }}
    </div>

    <div class="rounded-md border">
      <table class="w-full text-sm">
        <thead class="bg-neutral-50 text-neutral-600 dark:bg-neutral-900/40 dark:text-neutral-300">
          <tr>
            <th class="px-4 py-2 text-left">Город</th>
            <th class="px-4 py-2 text-left w-56">Регион</th>
            <th class="px-4 py-2 text-left">Адрес</th>
            <th class="px-4 py-2 text-left w-40">City Code</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in rows" :key="row.id" class="border-t">
            <td class="px-4 py-2">
              <Input v-model="row.cityProxy" placeholder="Город" @blur="() => saveRow(row)" />
            </td>
            <td class="px-4 py-2">
              <Input v-model="row.regionProxy" placeholder="Регион" @blur="() => saveRow(row)" />
            </td>
            <td class="px-4 py-2">
              <Input v-model="row.addressProxy" placeholder="Адрес" @blur="() => saveRow(row)" />
            </td>
            <td class="px-4 py-2">
              <Input v-model="row.cityCodeProxy" placeholder="Код города" @blur="() => saveRow(row)" />
            </td>
          </tr>
          <tr v-if="!loading && rows.length === 0">
            <td colspan="4" class="px-4 py-6 text-center text-neutral-500">Нет записей</td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="flex flex-col items-center justify-between gap-3 sm:flex-row">
      <div class="text-sm text-neutral-500">
        Показано <span class="font-medium text-neutral-700 dark:text-neutral-300">{{ rows.length }}</span>
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

    <ToastRoot v-for="n in toastCount" :key="n" type="foreground" :duration="2200">
      <ToastDescription>{{ lastToastMessage }}</ToastDescription>
    </ToastRoot>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { ToastDescription, ToastRoot } from 'reka-ui'
import Input from '@admin/ui/components/Input.vue'
import Pagination from '@admin/ui/components/Pagination.vue'
import { useRoute, useRouter } from 'vue-router'
import { useCrud } from '@admin/composables/useCrud'
import { PvzPointsRepository, type PvzPoint } from '@admin/repositories/PvzPointsRepository'
import { getPaginationConfig } from '@admin/services/config'
import { debounce } from '@admin/utils/debounce'

type EditableRow = {
  id: number
  cityProxy: string
  regionProxy: string
  addressProxy: string
  cityCodeProxy: string
}

const repo = new PvzPointsRepository()
const crud = useCrud<PvzPoint>(repo)
const state = crud.state
const loading = computed(() => !!state.loading)
const totalItems = computed(() => state.totalItems)
const totalPages = computed(() => state.pagination.totalPages)
const page = computed(() => state.pagination.page)
const itemsPerPage = computed(() => state.pagination.itemsPerPage)
const pageModel = computed({ get: () => page.value, set: (v: number) => crud.goToPage(v) })
const itemsPerPageModel = computed<number>({
  get: () => itemsPerPage.value,
  set: (v: number) => {
    const next = Number(v)
    crud.setItemsPerPage(next)
    const nextQuery: Record<string, any> = { ...route.query, itemsPerPage: String(next), page: '1' }
    router.replace({ query: nextQuery })
  }
})
const ippOptions = ref<number[]>([])

const rows = ref<EditableRow[]>([])
const route = useRoute()
const router = useRouter()
const search = ref('')

onMounted(async () => {
  let defaultIpp: number | null = null
  try {
    const cfg = await getPaginationConfig('pvz')
    ippOptions.value = cfg.itemsPerPageOptions
    defaultIpp = cfg.defaultItemsPerPage
  } catch (_) {
    ippOptions.value = []
  }

  const rawIpp = route.query.itemsPerPage
  const rawPage = route.query.page
  search.value = String(route.query.search ?? '')
  const desiredIpp = Number.isFinite(Number(rawIpp)) ? Number(rawIpp) : (defaultIpp ?? undefined)
  const desiredPage = Number.isFinite(Number(rawPage)) ? Math.max(1, Number(rawPage)) : 1

  await crud.fetchAll({
    itemsPerPage: typeof desiredIpp === 'number' ? desiredIpp : undefined,
    page: desiredPage,
    sort: { city: 'asc' },
    filters: search.value ? { city: search.value } : undefined,
  })
  syncRows()

  const ensuredQuery: Record<string, any> = { ...route.query }
  ensuredQuery.page = String(state.pagination.page)
  ensuredQuery.itemsPerPage = String(state.pagination.itemsPerPage)
  if (search.value) ensuredQuery.search = search.value
  router.replace({ query: ensuredQuery })
})

let initialized = false
watch([page, itemsPerPage], ([p, ipp]) => {
  if (!initialized) {
    initialized = true
    return
  }
  const qPage = String(route.query.page ?? '')
  const qIpp = String(route.query.itemsPerPage ?? '')
  const nextQuery: Record<string, any> = { ...route.query }
  let changed = false
  if (qPage !== String(p)) {
    nextQuery.page = String(p)
    changed = true
  }
  if (qIpp !== String(ipp)) {
    nextQuery.itemsPerPage = String(ipp)
    changed = true
  }
  if (search.value !== String(route.query.search ?? '')) {
    nextQuery.search = search.value || undefined
    changed = true
  }
  if (changed) router.replace({ query: nextQuery })
})

watch(
  () => state.items,
  () => syncRows(),
  { deep: true }
)

function syncRows() {
  const items = ((state.items ?? []) as PvzPoint[]).slice()
  items.sort((a, b) => String((a as any).city || '').localeCompare(String((b as any).city || '')))
  rows.value = items.map((c) => ({
    id: Number(c.id),
    cityProxy: String((c as any).city ?? ''),
    regionProxy: String((c as any).region ?? ''),
    addressProxy: String((c as any).address ?? ''),
    cityCodeProxy: String((c as any).cityCode ?? ''),
  }))
}

async function saveRow(row: EditableRow) {
  const payload: Partial<PvzPoint> = {
    city: row.cityProxy.trim() || null,
    region: row.regionProxy.trim() || null,
    address: row.addressProxy.trim() || null,
    cityCode: row.cityCodeProxy.trim() || null,
  }
  await crud.update(row.id, payload)
  publishToast('Сохранено')
}

const toastCount = ref(0)
const lastToastMessage = ref('')
function publishToast(message: string) {
  lastToastMessage.value = message
  toastCount.value++
}

// Поиск c debounce: фильтруем по city через API Platform
const doSearch = debounce(async () => {
  const nextQuery: Record<string, any> = { ...route.query }
  nextQuery.page = '1'
  if (search.value) nextQuery.search = search.value; else delete nextQuery.search
  router.replace({ query: nextQuery })
  await crud.fetchAll({ page: 1, filters: search.value ? { city: search.value } : undefined, sort: { city: 'asc' } })
  syncRows()
}, 300)

watch(search, () => doSearch())
</script>


