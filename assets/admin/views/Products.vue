  <template>
  <div class="space-y-6">
    

    <!-- Table -->
    <div class="overflow-hidden rounded-md border dark:border-neutral-800">
      <table class="w-full text-sm">
        <tbody>
          <tr class=" text-neutral-600 dark:bg-neutral-900/40 dark:text-neutral-300">
            <td colspan="9" class="px-4 py-3">
              <div class="space-y-3">
                <!-- Header / Actions -->
                
                <!-- Search -->
                <div class="flex items-center gap-2">
                  <input
                    type="text"
                    v-model="searchModel"
                    placeholder="Поиск по названию…"
                    class="h-9 w-full max-w-sm rounded-md border px-3 text-sm dark:border-neutral-800 dark:bg-neutral-900/40"
                  />
                  <button
                    type="button"
                    class="inline-flex h-9 items-center rounded-md border px-3 text-sm hover:bg-neutral-50 dark:border-neutral-800 dark:hover:bg-white/10"
                    @click="openCategoryModal"
                  >
                    Фильтр по категориям
                  </button>
                  <button
                    type="button"
                    class="inline-flex h-9 items-center rounded-md border px-3 text-sm hover:bg-neutral-50 disabled:opacity-50 disabled:cursor-not-allowed dark:border-neutral-800 dark:hover:bg-white/10"
                    :disabled="!hasActiveFilters"
                    @click="resetAllFilters"
                  >
                    Сбросить фильтры
                  </button>
                  <div class="flex items-center gap-2">
                    <Button variant="primary" @click="router.push({ name: 'admin-product-form', params: { id: 'new' } })">
                      + Добавить товар
                    </Button>
                    <Button
                      v-if="hasSelectedProducts"
                      variant="danger"
                      @click="confirmBulkDelete"
                    >
                      Удалить товар ({{ selectedProducts.size }})
                    </Button>
                  </div>
                </div>
              </div>
            </td>
          </tr>
          <tr class="bg-neutral-50 text-neutral-600 dark:bg-neutral-900/40 dark:text-neutral-300">
            <th class="px-4 py-2 text-left">
              <input
                type="checkbox"
                :checked="selectAll"
                @change="toggleSelectAll"
                class="rounded border-neutral-300 text-neutral-600 focus:ring-neutral-500 dark:border-neutral-600 dark:bg-neutral-700"
              />
            </th>
            <th class="px-4 py-2 text-left">Название товара</th>
            <th class="px-4 py-2 text-left">Изображение</th>
            <th class="px-4 py-2 text-left">Категории</th>
            <th class="px-4 py-2 text-left">Цена</th>
            <th
              class="px-4 py-2 text-left cursor-pointer select-none"
              :class="sortStatusDir ? 'text-neutral-900 dark:text-neutral-100' : ''"
              @click="toggleSortStatus"
            >
              Статус
              <span v-if="sortStatusDir === 'desc'">▼</span>
              <span v-else-if="sortStatusDir === 'asc'">▲</span>
            </th>
            <th class="px-4 py-2 text-left">Сортировка</th>
            <th
              class="px-4 py-2 text-left cursor-pointer select-none"
              :class="sortDateDir ? 'text-neutral-900 dark:text-neutral-100' : ''"
              @click="toggleSortCreated"
            >
              Дата создания
              <span v-if="sortDateDir === 'desc'">▼</span>
              <span v-else-if="sortDateDir === 'asc'">▲</span>
            </th>
            <th class="px-4 py-2 text-left">Действия</th>
          </tr>
          <tr v-for="p in products" :key="p.id" class="border-t dark:border-neutral-800">
            <td class="px-4 py-2">
              <input
                type="checkbox"
                :checked="isProductSelected(Number(p.id))"
                @change="toggleProductSelection(Number(p.id))"
                class="rounded border-neutral-300 text-neutral-600 focus:ring-neutral-500 dark:border-neutral-600 dark:bg-neutral-700"
              />
            </td>
            <td class="px-4 py-2">
              <div class="flex items-center gap-2">
                <RouterLink
                  :to="{ name: 'admin-product-form', params: { id: p.id } }"
                  class="text-neutral-900 underline-offset-2 hover:underline dark:text-neutral-100"
                >
                  {{ p.name || 'Без названия' }}
                </RouterLink>
                <a
                  v-if="p.slug"
                  :href="`/product/${p.slug}`"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="inline-flex items-center justify-center w-5 h-5 text-neutral-500 hover:text-neutral-700 dark:text-neutral-400 dark:hover:text-neutral-200 transition-colors"
                  title="Открыть в каталоге"
                >
                  <ArrowUpRightIcon class="w-4 h-4" />
                </a>
              </div>
              <div v-if="p.slug" class="text-xs text-neutral-500">/{{ p.slug }}</div>
            </td>
            <td class="px-4 py-2">
              <div class="h-12 w-12 overflow-hidden rounded bg-neutral-100 ">
                <img v-if="firstImageUrl(p)" :src="firstImageUrl(p) || undefined" class="h-full w-full object-cover" alt="" />
              </div>
            </td>
            <td class="px-4 py-2">
              <div v-if="p.categoryNames && p.categoryNames.length > 0">
                <ScrollAreaRoot type="auto" class="relative overflow-hidden h-20 w-full">
                  <ScrollAreaViewport class="pr-2 w-full h-full">
                    <ul class="list-none m-0 p-0">
                      <li v-for="(name, idx) in p.categoryNames" :key="idx">{{ name }}</li>
                    </ul>
                  </ScrollAreaViewport>
                  <ScrollAreaScrollbar orientation="vertical" class="w-3">
                    <ScrollAreaThumb class="bg-neutral-300 dark:bg-neutral-700 rounded-full" />
                  </ScrollAreaScrollbar>
                  <ScrollAreaCorner />
                </ScrollAreaRoot>
              </div>
              <span v-else>—</span>
            </td>
            <td class="px-4 py-2">
              <div class="flex items-center gap-1">
                <span class="font-medium">{{ p.salePrice ?? p.price ?? 0 }}</span>
                <span v-if="p.salePrice && p.price" class="text-xs text-neutral-500 line-through">{{ p.price }}</span>
              </div>
            </td>
            <td class="px-4 py-2">
              <span
                :class="[
                  'inline-flex items-center rounded-full px-2 py-0.5 text-xs',
                  p.status ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300' : 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
                ]"
              >
                {{ p.status ? 'Активен' : 'Отключен' }}
              </span>
            </td>
            <td class="px-4 py-2">{{ p.sortOrder ?? '—' }}</td>
            <td class="px-4 py-2">{{ formatDate(p.createdAt) }}</td>
            <td class="px-4 py-2">
              <div class="flex items-center gap-2">
                <RouterLink
                  :to="{ name: 'admin-product-form', params: { id: p.id } }"
                  class="inline-flex items-center rounded-md border border-blue-200 bg-blue-50 px-3 py-1 text-sm text-blue-700 hover:bg-blue-100 dark:border-blue-800 dark:bg-blue-900/20 dark:text-blue-300 dark:hover:bg-blue-900/40"
                >
                  Изменить
                </RouterLink>
                <button
                  type="button"
                  class="inline-flex items-center rounded-md border border-green-200 bg-green-50 px-3 py-1 text-sm text-green-700 hover:bg-green-100 dark:border-green-800 dark:bg-green-900/20 dark:text-green-300 dark:hover:bg-green-900/40"
                  @click="confirmCopy(p)"
                >
                  Копировать
                </button>
                <button
                  type="button"
                  class="inline-flex items-center rounded-md border border-red-200 bg-red-50 px-3 py-1 text-sm text-red-700 hover:bg-red-100 dark:border-red-800 dark:bg-red-900/20 dark:text-red-300 dark:hover:bg-red-900/40"
                  @click="confirmDelete(p)"
                >
                  Удалить
                </button>
              </div>
            </td>
          </tr>
          <tr v-if="!loading && products.length === 0">
            <td colspan="9" class="px-4 py-8 text-center text-neutral-500">Пока нет товаров</td>
          </tr>
          <tr v-if="loading">
            <td colspan="9" class="px-4 py-8 text-center text-neutral-500">Загрузка…</td>
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

    <!-- Copy confirmation -->
    <ConfirmDialog
      v-model="copyDialogOpen"
      title="Копировать товар?"
      :description="`Создать копию товара &quot;${pendingCopyProduct?.name ?? 'Неизвестный товар'}&quot; с префиксом &quot;Копия &quot;`"
      confirm-text="Копировать"
      @confirm="performCopy"
    />

    <!-- Bulk delete confirmation -->
    <ConfirmDialog
      v-model="bulkDeleteDialogOpen"
      title="Удалить товары?"
      :description="`Вы уверены, что хотите удалить ${selectedProducts.size} товар(ов)? Товары, которые используются в корзине покупателей, не будут удалены. Это действие необратимо для успешно удаленных товаров.`"
      confirm-text="Удалить"
      :danger="true"
      @confirm="performBulkDelete"
    />


    <!-- Category filter modal -->
    <Modal v-model="categoryModalOpen" title="Выберите категорию" size="xl">
      <div class="max-h-[60vh] overflow-auto">
        <ul class="space-y-1">
          <li v-for="n in flatCategoryList" :key="n.id">
            <button
              type="button"
              class="text-left hover:underline"
              :style="{ paddingLeft: `${n.level * 16}px` }"
              @click="applyCategoryFilter(n.id)"
            >
              {{ n.label }}
            </button>
          </li>
        </ul>
      </div>
    </Modal>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { ref as vueRef } from 'vue'
import { httpClient } from '@admin/services/http'
import { getPaginationConfig } from '@admin/services/config'
import { ToastDescription, ToastRoot, ScrollAreaRoot, ScrollAreaScrollbar, ScrollAreaThumb, ScrollAreaViewport, ScrollAreaCorner } from 'reka-ui'
import ConfirmDialog from '@admin/components/ConfirmDialog.vue'
import Pagination from '@admin/ui/components/Pagination.vue'
import Button from '@admin/ui/components/Button.vue'
import { useCrud } from '@admin/composables/useCrud'
import { ProductRepository, type ProductDto } from '@admin/repositories/ProductRepository'
import Modal from '@admin/ui/components/Modal.vue'
import { CategoryRepository, type CategoryDto } from '@admin/repositories/CategoryRepository'
import { ArrowUpRightIcon } from '@heroicons/vue/24/outline'

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
const sortDateDir = vueRef<'asc' | 'desc' | null>(null)
const sortStatusDir = vueRef<'asc' | 'desc' | null>(null)
const searchModel = vueRef<string>('')
let searchDebounceHandle: any = null
const appliedSearch = vueRef<string>('')
const categoryModalOpen = vueRef(false)
const selectedCategoryId = vueRef<number | null>(null)
const categoryTree = vueRef<any[]>([])
const categoriesLoaded = vueRef(false)
const categoryRepo = new CategoryRepository()

// Checkbox state for bulk operations
const selectedProducts = vueRef<Set<number>>(new Set())
const selectAll = vueRef(false)

const flatCategoryList = computed<{ id: number; label: string; level: number }[]>(() => {
  const out: { id: number; label: string; level: number }[] = []
  const walk = (nodes: any[], level = 0) => {
    for (const n of nodes || []) {
      out.push({ id: Number(n.id), label: String(n.label ?? ''), level })
      if (n.children && n.children.length) walk(n.children, level + 1)
    }
  }
  walk(categoryTree.value || [], 0)
  return out
})

const hasSelectedProducts = computed(() => selectedProducts.value.size > 0)

const isProductSelected = (productId: number) => selectedProducts.value.has(productId)

const toggleProductSelection = (productId: number) => {
  if (selectedProducts.value.has(productId)) {
    selectedProducts.value.delete(productId)
  } else {
    selectedProducts.value.add(productId)
  }
  updateSelectAllState()
}

const toggleSelectAll = () => {
  if (selectAll.value) {
    selectedProducts.value.clear()
  } else {
    products.value.forEach(p => {
      if (p.id) selectedProducts.value.add(Number(p.id))
    })
  }
  selectAll.value = !selectAll.value
}

const updateSelectAllState = () => {
  const currentPageProductIds = new Set(products.value.map(p => Number(p.id)).filter(id => id))
  const selectedOnCurrentPage = Array.from(selectedProducts.value).filter(id => currentPageProductIds.has(id))
  selectAll.value = selectedOnCurrentPage.length === currentPageProductIds.size && currentPageProductIds.size > 0
}

const hasActiveFilters = computed<boolean>(() => {
  return (
    (appliedSearch.value && appliedSearch.value.length >= 3) ||
    (selectedCategoryId.value != null) ||
    (sortDateDir.value !== null) ||
    (sortStatusDir.value !== null)
  )
})

onMounted(async () => {
  // Если форма товара попросила обновить список — сбросим кэш и сделаем свежий fetch
  try {
    const flag = sessionStorage.getItem('products:shouldReload')
    if (flag === '1') {
      sessionStorage.removeItem('products:shouldReload')
      ;(ProductRepository as any).invalidateCachePrefix?.('/v2/products')
    }
  } catch {}
  // 1) Load pagination options (cached)
  let defaultIpp: number | null = null
  try {
    const cfg = await getPaginationConfig('default')
    ippOptions.value = cfg.itemsPerPageOptions
    defaultIpp = cfg.defaultItemsPerPage
  } catch (_) {
    ippOptions.value = []
  }

  // 2) Determine initial itemsPerPage and page from URL (no redirects here)
  const rawIpp = route.query.itemsPerPage
  const rawPage = route.query.page
  const desiredIpp = Number.isFinite(Number(rawIpp)) ? Number(rawIpp) : (defaultIpp ?? undefined)
  const desiredPage = Number.isFinite(Number(rawPage)) ? Math.max(1, Number(rawPage)) : 1

  // 3) Single fetch with desired ipp/page; backend validates ipp
  const sortDateRaw = route.query['order[dateAdded]']
  const initialDateSort = typeof sortDateRaw === 'string' ? sortDateRaw.toLowerCase() : Array.isArray(sortDateRaw) ? String(sortDateRaw[0] || '').toLowerCase() : ''
  if (initialDateSort === 'asc' || initialDateSort === 'desc') sortDateDir.value = initialDateSort

  const sortStatusRaw = route.query['order[status]']
  const initialStatusSort = typeof sortStatusRaw === 'string' ? sortStatusRaw.toLowerCase() : Array.isArray(sortStatusRaw) ? String(sortStatusRaw[0] || '').toLowerCase() : ''
  if (initialStatusSort === 'asc' || initialStatusSort === 'desc') sortStatusDir.value = initialStatusSort
  const routeSearch = typeof route.query.name === 'string' ? route.query.name : Array.isArray(route.query.name) ? route.query.name[0] : ''
  searchModel.value = routeSearch ?? ''
  const initialTrimmed = (searchModel.value ?? '').trim()
  appliedSearch.value = initialTrimmed.length >= 3 ? initialTrimmed : ''
  const routeCategory = route.query.category
  selectedCategoryId.value = routeCategory != null ? Number(routeCategory) : null
  await crud.fetchAll({
    itemsPerPage: typeof desiredIpp === 'number' ? desiredIpp : undefined,
    page: desiredPage,
    sort: {
      ...(route.query['order[dateAdded]'] ? { dateAdded: String(route.query['order[dateAdded]']).toLowerCase() as 'asc' | 'desc' } : {}),
      ...(route.query['order[status]'] ? { status: String(route.query['order[status]']).toLowerCase() as 'asc' | 'desc' } : {}),
    },
    filters: {
      ...(appliedSearch.value ? { name: appliedSearch.value } : {}),
      ...(selectedCategoryId.value ? { category: selectedCategoryId.value } : {}),
    },
  })
})

// Watcher больше не нужен: используем одноразовый флажок из sessionStorage

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

// Copy with confirmation + toast
const copyDialogOpen = vueRef(false)
const pendingCopyId = vueRef<number | null>(null)
const pendingCopyProduct = vueRef<ProductDto | null>(null)

// Bulk delete state
const bulkDeleteDialogOpen = vueRef(false)


async function confirmDelete(p: ProductDto) {
  if (!p.id) return
  pendingDeleteId.value = Number(p.id)
  deleteDialogOpen.value = true
}
async function performDelete() {
  if (pendingDeleteId.value == null) return

  try {
    await crud.remove(pendingDeleteId.value)
    pendingDeleteId.value = null
    publishToast('Товар успешно удалён')
  } catch (error: any) {

    // Обработка конкретных ошибок
    if (error.response?.status === 409) {
      // Товар используется в корзине
      const product = products.value.find(p => Number(p.id) === pendingDeleteId.value)
      const productName = product ? `"${product.name}"` : `ID ${pendingDeleteId.value}`

      publishToast(`❌ Невозможно удалить товар ${productName} - он используется в корзине покупателей`)
    } else if (error.response?.status === 404) {
      // Товар не найден
      publishToast('❌ Товар не найден или уже был удалён')
    } else if (error.response?.status === 403) {
      // Нет прав доступа
      publishToast('❌ Недостаточно прав для удаления товара')
    } else {
      // Общая ошибка
      const errorMessage = error.response?.data?.['hydra:description'] ||
                          error.response?.data?.message ||
                          error.message ||
                          'Неизвестная ошибка'
      publishToast(`❌ Ошибка при удалении товара: ${errorMessage}`)
    }
  } finally {
    deleteDialogOpen.value = false
  }
}

async function confirmCopy(p: ProductDto) {
  if (!p.id) return
  pendingCopyId.value = Number(p.id)
  pendingCopyProduct.value = p
  copyDialogOpen.value = true
}

async function performCopy() {
  if (pendingCopyId.value == null) return

  try {
    const response = await httpClient.post(`/admin/products/${pendingCopyId.value}/copy`, {
      copyCategories: true,
      copyImages: true,
      copyAttributes: true,
      copySeo: true,
      namePrefix: 'Копия ',
      setInactive: true
    })

    if (response.data.success) {
      publishToast(`✅ Товар успешно скопирован: "${response.data.data.name}"`)
      // Обновляем список товаров
      await crud.fetchAll({ page: crudState.pagination.page, itemsPerPage: crudState.pagination.itemsPerPage })
    } else {
      publishToast(`❌ Ошибка копирования: ${response.data.message}`)
    }

    pendingCopyId.value = null
    pendingCopyProduct.value = null
  } catch (error: any) {
    const errorMessage = error.response?.data?.message ||
                        error.response?.data?.['hydra:description'] ||
                        error.message ||
                        'Неизвестная ошибка'
    publishToast(`❌ Ошибка при копировании товара: ${errorMessage}`)
  } finally {
    copyDialogOpen.value = false
  }
}


// Bulk delete functions
async function confirmBulkDelete() {
  if (selectedProducts.value.size === 0) return
  bulkDeleteDialogOpen.value = true
}

async function performBulkDelete() {
  if (selectedProducts.value.size === 0) return

  try {
    const originalSelectedCount = selectedProducts.value.size
    let successCount = 0
    let failedProducts: string[] = []

    // Delete products one by one to handle individual errors
    for (const productId of selectedProducts.value) {
      try {
        await crud.remove(productId)
        successCount++
      } catch (error: any) {

        // Обработка разных типов ошибок
        const product = products.value.find(p => Number(p.id) === productId)
        const productName = product ? `"${product.name}"` : `ID ${productId}`

        if (error.response?.status === 409) {
          // Товар используется в корзине
          failedProducts.push(`${productName} (используется в корзине)`)
        } else if (error.response?.status === 404) {
          // Товар не найден
          failedProducts.push(`${productName} (не найден)`)
        } else if (error.response?.status === 403) {
          // Нет прав доступа
          failedProducts.push(`${productName} (нет прав доступа)`)
        } else {
          // Другая ошибка
          const errorMessage = error.response?.data?.['hydra:description'] ||
                              error.response?.data?.message ||
                              error.message ||
                              'неизвестная ошибка'
          failedProducts.push(`${productName} (${errorMessage})`)
        }
      }
    }

    selectedProducts.value.clear()
    selectAll.value = false

    if (successCount > 0) {
      const successMessage = successCount === originalSelectedCount
        ? `✅ Успешно удалено ${successCount} товар(ов)`
        : `✅ Успешно удалено ${successCount} из ${originalSelectedCount} товар(ов)`
      publishToast(successMessage)
    }

    if (failedProducts.length > 0) {
      const failedMessage = `❌ Не удалось удалить товары: ${failedProducts.join(', ')}`
      publishToast(failedMessage)
    }

  } catch (error) {
    const errorMessage = error.response?.data?.['hydra:description'] ||
                        error.response?.data?.message ||
                        error.message ||
                        'Неизвестная ошибка'
    publishToast(`❌ Критическая ошибка при удалении товаров: ${errorMessage}`)
  } finally {
    bulkDeleteDialogOpen.value = false
  }
}


// Imperative toast publisher (per Reka UI)
const toastCount = vueRef(0)
const lastToastMessage = vueRef('')
function publishToast(message: string) {
  lastToastMessage.value = message
  toastCount.value++
}

function formatDate(value: string | null | undefined): string {
  if (!value) return '—'
  try {
    const d = new Date(value)
    if (Number.isNaN(d.getTime())) return '—'
    return d.toLocaleDateString()
  } catch (_) {
    return '—'
  }
}

async function toggleSortCreated() {
  // Cycle: null -> desc -> asc -> null
  let nextDir: 'asc' | 'desc' | null
  if (sortDateDir.value === null) nextDir = 'desc'
  else if (sortDateDir.value === 'desc') nextDir = 'asc'
  else nextDir = null

  sortDateDir.value = nextDir

  const nextQuery: Record<string, any> = { ...route.query, page: '1' }
  if (nextDir) nextQuery['order[dateAdded]'] = nextDir
  else delete nextQuery['order[dateAdded]']

  router.replace({ query: nextQuery })
  await crud.fetchAll({
    page: 1,
    itemsPerPage: crudState.pagination.itemsPerPage,
    sort: {
      ...(nextDir ? { dateAdded: nextDir } : {}),
      ...(sortStatusDir.value ? { status: sortStatusDir.value } : {}),
    },
    filters: {
      ...(appliedSearch.value ? { name: appliedSearch.value } : {}),
      ...(selectedCategoryId.value ? { category: selectedCategoryId.value } : {}),
    },
  })
}

async function toggleSortStatus() {
  // Cycle: null -> desc -> asc -> null
  let nextDir: 'asc' | 'desc' | null
  if (sortStatusDir.value === null) nextDir = 'desc'
  else if (sortStatusDir.value === 'desc') nextDir = 'asc'
  else nextDir = null

  sortStatusDir.value = nextDir

  const nextQuery: Record<string, any> = { ...route.query, page: '1' }
  if (nextDir) nextQuery['order[status]'] = nextDir
  else delete nextQuery['order[status]']

  router.replace({ query: nextQuery })
  await crud.fetchAll({
    page: 1,
    itemsPerPage: crudState.pagination.itemsPerPage,
    sort: {
      ...(sortDateDir.value ? { dateAdded: sortDateDir.value } : {}),
      ...(nextDir ? { status: nextDir } : {}),
    },
    filters: {
      ...(appliedSearch.value ? { name: appliedSearch.value } : {}),
      ...(selectedCategoryId.value ? { category: selectedCategoryId.value } : {}),
    },
  })
}

watch(
  () => searchModel.value,
  (v) => {
    if (searchDebounceHandle) clearTimeout(searchDebounceHandle)
    searchDebounceHandle = setTimeout(async () => {
      const trimmed = (v ?? '').trim()
      const hadActive = appliedSearch.value !== ''

      // If below threshold and nothing applied, do nothing
      if (trimmed.length < 3 && !hadActive) return

      const nextQuery: Record<string, any> = { ...route.query, page: '1' }
      if (trimmed.length >= 3) {
        nextQuery.name = trimmed
        appliedSearch.value = trimmed
      } else {
        delete nextQuery.name
        appliedSearch.value = ''
      }
      router.replace({ query: nextQuery })
      await crud.fetchAll({
        page: 1,
        itemsPerPage: crudState.pagination.itemsPerPage,
        sort: {
          ...(sortDateDir.value ? { dateAdded: sortDateDir.value } : {}),
          ...(sortStatusDir.value ? { status: sortStatusDir.value } : {}),
        },
        filters: {
          ...(appliedSearch.value ? { name: appliedSearch.value } : {}),
          ...(selectedCategoryId.value ? { category: selectedCategoryId.value } : {}),
        },
      })
    }, 300)
  },
)

function openCategoryModal() {
  categoryModalOpen.value = true
  if (!categoriesLoaded.value) loadCategoriesTree()
}

async function loadCategoriesTree() {
  const res = (await categoryRepo.findAllCached({ itemsPerPage: 1000 })) as any
  const list = (res['hydra:member'] ?? res.member ?? res ?? []) as CategoryDto[]
  const byId = new Map<number, any>()
  const roots: any[] = []
  for (const c of list) byId.set(Number(c.id), { id: Number(c.id), label: c.name || `Без названия (#${c.id})`, parentId: (c as any).parentCategoryId ?? null, children: [] })
  for (const n of byId.values()) {
    if (n.parentId && byId.has(n.parentId)) byId.get(n.parentId).children.push(n)
    else roots.push(n)
  }
  const sortRec = (nodes: any[]) => { nodes.sort((a,b)=> String(a.label).localeCompare(String(b.label))); nodes.forEach((n)=> n.children && sortRec(n.children)) }
  sortRec(roots)
  categoryTree.value = roots
  categoriesLoaded.value = true
}

async function applyCategoryFilter(id: number) {
  selectedCategoryId.value = id
  const nextQuery: Record<string, any> = { ...route.query, page: '1', category: String(id) }
  router.replace({ query: nextQuery })
  categoryModalOpen.value = false
  await crud.fetchAll({
    page: 1,
    itemsPerPage: crudState.pagination.itemsPerPage,
    sort: {
      ...(sortDateDir.value ? { dateAdded: sortDateDir.value } : {}),
      ...(sortStatusDir.value ? { status: sortStatusDir.value } : {}),
    },
    filters: {
      ...(appliedSearch.value ? { name: appliedSearch.value } : {}),
      ...(selectedCategoryId.value ? { category: selectedCategoryId.value } : {}),
    },
  })
}

async function resetAllFilters() {
  searchModel.value = ''
  appliedSearch.value = ''
  selectedCategoryId.value = null
  sortDateDir.value = null
  sortStatusDir.value = null

  const nextQuery: Record<string, any> = { ...route.query }
  delete nextQuery.name
  delete nextQuery.category
  delete nextQuery['order[dateAdded]']
  delete nextQuery['order[status]']
  nextQuery.page = '1'
  router.replace({ query: nextQuery })

  await crud.fetchAll({
    page: 1,
    itemsPerPage: crudState.pagination.itemsPerPage,
    sort: undefined,
    filters: undefined,
  })
}

// Watch for products changes to update checkbox state
watch(products, () => {
  updateSelectAllState()
}, { immediate: true })

</script>

<style scoped></style>


