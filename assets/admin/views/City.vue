<template>
  <div class="space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div class="flex items-center gap-3">
        <h1 class="text-xl font-semibold">Города</h1>
        <div class="text-sm text-neutral-500">Всего: <span class="font-medium text-neutral-700 dark:text-neutral-300">{{ totalItems }}</span></div>
      </div>
      <button
        type="button"
        class="inline-flex h-9 items-center rounded-md bg-neutral-900 px-3 text-sm font-medium text-white shadow hover:bg-neutral-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:opacity-50 dark:bg-white dark:text-neutral-900 dark:hover:bg-neutral-100"
        @click="openCreate = true"
      >
        Добавить город
      </button>
    </div>

    <div v-if="state.error" class="mb-2 rounded-md border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-700">
      {{ state.error }}
    </div>

    <div class="rounded-md border">
      <table class="w-full text-sm">
        <thead class="bg-neutral-50 text-neutral-600 dark:bg-neutral-900/40 dark:text-neutral-300">
          <tr>
            <th class="px-4 py-2 text-left">Город</th>
            <th class="px-4 py-2 text-left">Адрес</th>
            <th class="px-4 py-2 text-left w-40">Население</th>
            <th class="px-4 py-2 text-left w-28">Действия</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in rows" :key="row.id" class="border-t">
            <td class="px-4 py-2">
              <Input v-model="row.cityProxy" placeholder="Название города" @blur="() => saveRow(row)" />
            </td>
            <td class="px-4 py-2">
              <Input v-model="row.addressProxy" placeholder="Адрес" @blur="() => saveRow(row)" />
            </td>
            <td class="px-4 py-2">
              <Input v-model="row.populationProxy" type="number" placeholder="0" @blur="() => saveRow(row)" />
            </td>
            <td class="px-4 py-2">
              <button
                type="button"
                class="h-8 rounded-md bg-red-600 px-2 text-xs font-medium text-white hover:bg-red-700"
                @click="confirmDelete(row.id)"
              >
                Удалить
              </button>
            </td>
          </tr>
          <tr v-if="!loading && rows.length === 0">
            <td colspan="4" class="px-4 py-6 text-center text-neutral-500">Нет записей</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Footer / Pagination + Page size -->
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

    <DialogRoot v-model:open="openCreate">
      <DialogPortal>
        <DialogOverlay class="fixed inset-0 bg-black/50 backdrop-blur-[1px]" />
        <DialogContent
          class="fixed left-1/2 top-1/2 w-[92vw] max-w-md -translate-x-1/2 -translate-y-1/2 rounded-md border bg-white p-4 shadow-lg focus:outline-none dark:border-neutral-800 dark:bg-neutral-900"
        >
          <div class="mb-2">
            <DialogTitle class="text-base font-semibold text-neutral-900 dark:text-neutral-100">Новый город</DialogTitle>
            <DialogDescription class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">Заполните поля ниже</DialogDescription>
          </div>

          <form class="space-y-4" @submit.prevent="createSubmit">
            <Input v-model="createForm.city" label="Город" placeholder="Например: Москва" />
            <Input v-model="createForm.address" label="Адрес" placeholder="Адрес/улица" />
            <Input v-model="createForm.populationStr" label="Население" type="number" placeholder="0" />

            <div class="flex justify-end gap-2 pt-2">
              <button type="button" class="h-9 rounded-md px-3 text-sm hover:bg-neutral-100 dark:hover:bg-white/10" @click="openCreate = false">Отмена</button>
              <button
                type="submit"
                :disabled="submitting || !createForm.city.trim()"
                class="inline-flex h-9 items-center rounded-md bg-neutral-900 px-3 text-sm font-medium text-white shadow hover:bg-neutral-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:opacity-50 dark:bg-white dark:text-neutral-900 dark:hover:bg-neutral-100"
              >
                {{ submitting ? 'Сохранение…' : 'Сохранить' }}
              </button>
            </div>
          </form>

          <DialogClose as-child>
            <button aria-label="Закрыть" class="sr-only">Закрыть</button>
          </DialogClose>
        </DialogContent>
      </DialogPortal>
    </DialogRoot>

    <ToastRoot v-for="n in toastCount" :key="n" type="foreground" :duration="2200">
      <ToastDescription>{{ lastToastMessage }}</ToastDescription>
    </ToastRoot>
    <ConfirmDialog v-model="deleteOpen" :title="'Удалить город?'" :description="'Это действие необратимо'" confirm-text="Удалить" :danger="true" @confirm="performDelete" />
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { DialogClose, DialogContent, DialogDescription, DialogOverlay, DialogPortal, DialogRoot, DialogTitle, ToastDescription, ToastRoot } from 'reka-ui'
import Input from '@admin/ui/components/Input.vue'
import Pagination from '@admin/ui/components/Pagination.vue'
import ConfirmDialog from '@admin/components/ConfirmDialog.vue'
import { httpClient } from '@admin/services/http'
import { useRoute, useRouter } from 'vue-router'
import { useCrud } from '@admin/composables/useCrud'
import { CityRepository, type City } from '@admin/repositories/CityRepository'
import { getPaginationConfig } from '@admin/services/config'

type EditableRow = {
  id: number
  cityProxy: string
  addressProxy: string
  populationProxy: string
}

const repo = new CityRepository()
const crud = useCrud<City>(repo)
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

onMounted(async () => {
  // 1) Load pagination options for City from backend config (with caching inside service)
  let defaultIpp: number | null = null
  try {
    const cfg = await getPaginationConfig('city')
    ippOptions.value = cfg.itemsPerPageOptions
    defaultIpp = cfg.defaultItemsPerPage
  } catch (_) {
    ippOptions.value = []
  }

  // 2) Determine initial itemsPerPage and page from URL (backend validates ipp)
  const rawIpp = route.query.itemsPerPage
  const rawPage = route.query.page
  const desiredIpp = Number.isFinite(Number(rawIpp)) ? Number(rawIpp) : (defaultIpp ?? undefined)
  const desiredPage = Number.isFinite(Number(rawPage)) ? Math.max(1, Number(rawPage)) : 1

  // 3) Single fetch with desired ipp/page
  await crud.fetchAll({
    itemsPerPage: typeof desiredIpp === 'number' ? desiredIpp : undefined,
    page: desiredPage,
    sort: { city: 'asc' },
  })
  syncRows()

  // 4) Ensure URL has page and itemsPerPage after initial fetch
  const ensuredQuery: Record<string, any> = { ...route.query }
  ensuredQuery.page = String(state.pagination.page)
  ensuredQuery.itemsPerPage = String(state.pagination.itemsPerPage)
  router.replace({ query: ensuredQuery })
})

// Sync URL only when user changes pagination (avoid initial redirects / loops)
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
  if (changed) router.replace({ query: nextQuery })
})

watch(
  () => state.items,
  () => syncRows(),
  { deep: true }
)

function syncRows() {
  const items = ((state.items ?? []) as City[]).slice()
  items.sort((a, b) => String((a as any).city || '').localeCompare(String((b as any).city || '')))
  rows.value = items.map((c) => ({
    id: Number(c.id),
    cityProxy: String((c as any).city ?? ''),
    addressProxy: String((c as any).address ?? ''),
    populationProxy: (c as any).population == null ? '' : String((c as any).population),
  }))
}

async function saveRow(row: EditableRow) {
  const payload: Partial<City> = {
    city: row.cityProxy.trim() || null,
    address: row.addressProxy.trim() || null,
    population: row.populationProxy === '' ? null : Number(row.populationProxy),
  }
  await crud.update(row.id, payload)
  publishToast('Сохранено')
}

// Create form
const openCreate = ref(false)
const submitting = ref(false)
const createForm = reactive({ city: '', address: '', populationStr: '' })

async function createSubmit() {
  if (!createForm.city.trim()) return
  submitting.value = true
  try {
    await crud.create({
      city: createForm.city.trim(),
      address: createForm.address.trim() || null,
      population: createForm.populationStr === '' ? null : Number(createForm.populationStr),
    } as Partial<City>)
    syncRows()
    openCreate.value = false
    Object.assign(createForm, { city: '', address: '', populationStr: '' })
    publishToast('Город добавлен')
  } finally {
    submitting.value = false
  }
}

// toasts
const toastCount = ref(0)
const lastToastMessage = ref('')
function publishToast(message: string) {
  lastToastMessage.value = message
  toastCount.value++
}

// delete
const deleteOpen = ref(false)
const pendingDeleteId = ref<number | null>(null)
function confirmDelete(id: number) {
  pendingDeleteId.value = id
  deleteOpen.value = true
}
async function performDelete() {
  if (pendingDeleteId.value == null) return
  await crud.remove(pendingDeleteId.value)
  rows.value = rows.value.filter(r => r.id !== pendingDeleteId.value!)
  publishToast('Город удалён')
  pendingDeleteId.value = null
  deleteOpen.value = false
}
</script>



