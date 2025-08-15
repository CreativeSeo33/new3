<template>
  <div class="space-y-6">
    <!-- Header / Actions -->
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h1 class="text-xl font-semibold text-neutral-900 dark:text-neutral-100">Products List</h1>
        <p class="mt-1 text-sm text-neutral-500">Track your store's progress to boost your sales.</p>
      </div>
      <div class="flex items-center gap-2">
        <button
          type="button"
          class="inline-flex h-9 items-center rounded-md border px-3 text-sm shadow-sm hover:bg-neutral-50 dark:border-neutral-800 dark:hover:bg-white/10"
        >
          Export
        </button>
        <RouterLink
          :to="{ name: 'admin-product-form', params: { id: 'new' } }"
          class="inline-flex h-9 items-center rounded-md bg-neutral-900 px-3 text-sm font-medium text-white shadow hover:bg-neutral-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:opacity-50 dark:bg-white dark:text-neutral-900 dark:hover:bg-neutral-100"
        >
          Add Product
        </RouterLink>
      </div>
    </div>
    

    <!-- Table -->
    <div class="overflow-hidden rounded-md border dark:border-neutral-800">
      <table class="w-full text-sm">
        <thead class="bg-neutral-50 text-neutral-600 dark:bg-neutral-900/40 dark:text-neutral-300">
          <tr>
            <th class="px-4 py-2 text-left w-16">#</th>
            <th class="px-4 py-2 text-left">Изображение</th>
            <th class="px-4 py-2 text-left">Product</th>
            <th class="px-4 py-2 text-left">Price</th>
            <th class="px-4 py-2 text-left">Stock</th>
            <th class="px-4 py-2 text-left">Status</th>
            <th class="px-4 py-2 text-left w-40">Action</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="p in products" :key="p.id" class="border-t dark:border-neutral-800">
            <td class="px-4 py-2 text-neutral-500">{{ p.id }}</td>
            <td class="px-4 py-2">
              <div class="h-12 w-12 overflow-hidden rounded bg-neutral-100 dark:bg-neutral-800">
                <img v-if="firstImageUrl(p)" :src="firstImageUrl(p) || undefined" class="h-full w-full object-cover" alt="" />
              </div>
            </td>
            <td class="px-4 py-2">
              <RouterLink
                :to="{ name: 'admin-product-form', params: { id: p.id } }"
                class="text-neutral-900 underline-offset-2 hover:underline dark:text-neutral-100"
              >
                {{ p.name || 'Без названия' }}
              </RouterLink>
              <div v-if="p.slug" class="text-xs text-neutral-500">/{{ p.slug }}</div>
            </td>
            <td class="px-4 py-2">{{ p.manufacturerName || '—' }}</td>
            <td class="px-4 py-2">
              <div class="flex items-center gap-1">
                <span class="font-medium">{{ p.salePrice ?? p.price ?? 0 }}</span>
                <span v-if="p.salePrice && p.price" class="text-xs text-neutral-500 line-through">{{ p.price }}</span>
              </div>
            </td>
            <td class="px-4 py-2">{{ p.quantity ?? 0 }}</td>
            <td class="px-4 py-2">
              <span
                :class="[
                  'inline-flex items-center rounded-full px-2 py-0.5 text-xs',
                  p.status ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300' : 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
                ]"
              >
                {{ p.status ? 'Active' : 'Disabled' }}
              </span>
            </td>
            <td class="px-4 py-2">
              <div class="flex items-center gap-2">
                <RouterLink
                  :to="{ name: 'admin-product-form', params: { id: p.id } }"
                  class="inline-flex h-8 items-center rounded-md border px-2 text-xs hover:bg-neutral-50 dark:border-neutral-800 dark:hover:bg-white/10"
                >
                  View More
                </RouterLink>
                <a
                  v-if="p.slug"
                  :href="`/product/${p.slug}`"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="inline-flex h-8 items-center rounded-md border px-2 text-xs hover:bg-neutral-50 dark:border-neutral-800 dark:hover:bg-white/10"
                >
                  View
                </a>
                <button
                  type="button"
                  class="inline-flex h-8 items-center rounded-md bg-red-600 px-2 text-xs font-medium text-white hover:bg-red-700"
                  @click="confirmDelete(p)"
                >
                  Delete
                </button>
              </div>
            </td>
          </tr>
          <tr v-if="!loading && products.length === 0">
            <td colspan="7" class="px-4 py-8 text-center text-neutral-500">Пока нет товаров</td>
          </tr>
          <tr v-if="loading">
            <td colspan="7" class="px-4 py-8 text-center text-neutral-500">Загрузка…</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Footer / Pagination + Page size -->
    <div class="flex flex-col items-center justify-between gap-3 sm:flex-row">
      <div class="text-sm text-neutral-500">
        Showing <span class="font-medium text-neutral-700 dark:text-neutral-300">{{ products.length }}</span>
        of <span class="font-medium text-neutral-700 dark:text-neutral-300">{{ totalItems }}</span>
      </div>
      <div class="flex items-center gap-3">
        <label class="flex items-center gap-2 text-xs text-neutral-600 dark:text-neutral-300">
          <span>Per page</span>
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

    <!-- Toasts -->
    <ToastRoot v-for="n in toastCount" :key="n" type="foreground" :duration="3000">
      <ToastDescription>{{ lastToastMessage }}</ToastDescription>
    </ToastRoot>

    <!-- Delete confirmation -->
    <ConfirmDialog v-model="deleteDialogOpen" title="Удалить товар?" description="Это действие необратимо. Товар будет удалён навсегда." confirm-text="Удалить" :danger="true" @confirm="performDelete" />
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { ref as vueRef } from 'vue'
import { httpClient } from '@admin/services/http'
import { ToastDescription, ToastRoot } from 'reka-ui'
import ConfirmDialog from '@admin/components/ConfirmDialog.vue'
import Pagination from '@admin/ui/components/Pagination.vue'
import { useCrud } from '@admin/composables/useCrud'
import { ProductRepository, type ProductDto } from '@admin/repositories/ProductRepository'

// Data fetching via repository + useCrud
const productRepository = new ProductRepository()
const crud = useCrud<ProductDto>(productRepository)
const crudState = crud.state
const route = useRoute()
const router = useRouter()

const products = computed<ProductDto[]>(() => (crudState.items ?? []) as ProductDto[])
const loading = computed(() => !!crudState.loading)
const totalItems = computed(() => crudState.totalItems ?? products.value.length)
const page = computed(() => crudState.pagination.page)
const itemsPerPage = computed(() => crudState.pagination.itemsPerPage)
const pageModel = computed({
  get: () => page.value,
  set: (v: number) => crud.goToPage(v),
})
const itemsPerPageModel = computed<number>({
  get: () => crudState.pagination.itemsPerPage,
  set: (v: number) => crud.setItemsPerPage(Number(v)),
})
const totalPages = computed(() => crudState.pagination.totalPages)
const ippOptions = vueRef<number[]>([])
const hasNextPage = computed(() => page.value < totalPages.value)
const hasPrevPage = computed(() => page.value > 1)

onMounted(async () => {
  // 1) Load pagination options from backend config
  let defaultIpp: number | null = null
  try {
    const res = await httpClient.get('/config/pagination')
    const data: any = res.data
    const options = Array.isArray(data?.itemsPerPageOptions) ? data.itemsPerPageOptions : []
    ippOptions.value = options.map((n: any) => Number(n)).filter((n: number) => Number.isFinite(n) && n > 0)
    defaultIpp = Number.isFinite(Number(data?.defaultItemsPerPage)) ? Number(data.defaultItemsPerPage) : null
  } catch (_) {
    ippOptions.value = []
  }

  // 2) Determine initial itemsPerPage and page from URL (no redirects here)
  const rawIpp = route.query.itemsPerPage
  const rawPage = route.query.page
  const desiredIpp = Number.isFinite(Number(rawIpp)) ? Number(rawIpp) : (defaultIpp ?? undefined)
  const desiredPage = Number.isFinite(Number(rawPage)) ? Math.max(1, Number(rawPage)) : 1

  // 3) Single fetch with desired ipp/page; backend validates ipp
  await crud.fetchAll({
    itemsPerPage: typeof desiredIpp === 'number' ? desiredIpp : undefined,
    page: desiredPage,
  })
})

// Sync URL only when user triggers actions (avoid initial redirects / loops)
let initialized = false
watch([page, itemsPerPage], ([p, ipp], [prevP, prevIpp]) => {
  if (!initialized) {
    initialized = true
    return
  }
  const curPage = Number(route.query.page ?? 1)
  const curIpp = Number(route.query.itemsPerPage ?? ipp)
  const nextQuery: Record<string, any> = { ...route.query }
  if (p !== curPage) nextQuery.page = String(p)
  if (ipp !== curIpp) nextQuery.itemsPerPage = String(ipp)
  router.replace({ query: nextQuery })
})

function firstImageUrl(p: ProductDto): string | null {
  return (p as any).firstImageUrl ?? null
}

function nextPage() {
  crud.nextPage()
}
function prevPage() {
  crud.prevPage()
}

// Filters (stub)
const filters = reactive<{ category: string; company: string }>({ category: '', company: '' })
function resetFilters() {
  filters.category = ''
  filters.company = ''
}
async function applyFilters() {
  // TODO: when backend supports filters, map to query
  await crud.fetchAll({ itemsPerPage: crudState.pagination.itemsPerPage })
}

// Delete with confirmation + toast
import { AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogOverlay, AlertDialogPortal, AlertDialogRoot, AlertDialogTitle, AlertDialogTrigger } from 'reka-ui'
const deleteDialogOpen = vueRef(false)
const pendingDeleteId = vueRef<number | null>(null)

async function confirmDelete(p: ProductDto) {
  if (!p.id) return
  pendingDeleteId.value = Number(p.id)
  deleteDialogOpen.value = true
}
async function performDelete() {
  if (pendingDeleteId.value == null) return
  await crud.remove(pendingDeleteId.value)
  pendingDeleteId.value = null
  publishToast('Товар удалён')
}

// Imperative toast publisher (per Reka UI)
const toastCount = vueRef(0)
const lastToastMessage = vueRef('')
function publishToast(message: string) {
  lastToastMessage.value = message
  toastCount.value++
}
</script>

<style scoped></style>

