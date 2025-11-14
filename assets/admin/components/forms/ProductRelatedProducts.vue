<template>
  <div class="space-y-4">
    <div v-if="isCreating" class="rounded-md border p-4 text-sm text-neutral-600 dark:border-neutral-800 dark:text-neutral-300">
      Сохраните товар, чтобы добавить похожие товары
    </div>

    <template v-else>
      <div class="space-y-4">
        <Autocomplete
          label="Добавить похожий товар"
          placeholder="Начните вводить название товара…"
          :search="searchProducts"
          :min-query-length="3"
          :limit="10"
          :key="autocompleteKey"
          @select="handleProductSelect"
        />

        <p v-if="errorMessage" class="text-sm text-destructive">{{ errorMessage }}</p>
      </div>

      <div v-if="state.error" class="text-sm text-destructive">{{ state.error }}</div>
      <div v-if="!isLoading && relatedProducts.length === 0" class="text-sm text-neutral-600 dark:text-neutral-400">
        Список пуст. Добавьте похожие товары через поле поиска выше.
      </div>
      <div v-if="relatedProducts.length > 0" class="rounded-md border overflow-x-auto">
        <table class="w-full text-sm min-w-[600px]">
          <thead class="bg-neutral-50 text-neutral-600 dark:bg-neutral-900/40 dark:text-neutral-300">
            <tr>
              <th class="px-4 py-2 text-left">Изображение</th>
              <th class="px-4 py-2 text-left">Название</th>
              <th class="px-4 py-2 text-left w-40">Сортировка</th>
              <th class="px-4 py-2 text-left w-28">Действия</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in relatedProducts" :key="item.id" class="border-t dark:border-neutral-800">
              <td class="px-4 py-2">
                <div class="h-12 w-12 overflow-hidden rounded bg-neutral-100 dark:bg-neutral-800">
                  <img
                    v-if="getProductImage(item)"
                    :src="getProductImage(item) || undefined"
                    class="h-full w-full object-cover"
                    alt=""
                  />
                  <div v-else class="h-full w-full flex items-center justify-center text-neutral-400 text-xs">
                    Нет фото
                  </div>
                </div>
              </td>
              <td class="px-4 py-2">
                <div class="font-medium">{{ getProductName(item) || 'Без названия' }}</div>
              </td>
              <td class="px-4 py-2">
                <Input
                  v-model="item.sortOrderProxy"
                  type="number"
                  placeholder="0"
                  class="w-full"
                  @blur="() => saveSortOrder(item)"
                />
              </td>
              <td class="px-4 py-2">
                <button
                  type="button"
                  class="h-8 rounded-md bg-red-600 px-2 text-xs font-medium text-white hover:bg-red-700"
                  @click="confirmDelete(item.id!)"
                >
                  Удалить
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <ConfirmDialog
        v-model="deleteOpen"
        title="Удалить похожий товар?"
        description="Это действие необратимо"
        confirm-text="Удалить"
        :danger="true"
        @confirm="performDelete"
      />
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import Autocomplete from '@admin/ui/components/Autocomplete.vue'
import Input from '@admin/ui/components/Input.vue'
import ConfirmDialog from '@admin/components/ConfirmDialog.vue'
import { ProductRepository, type ProductDto } from '@admin/repositories/ProductRepository'
import { RelatedProductRepository, type RelatedProductDto } from '@admin/repositories/RelatedProductRepository'
import { useCrud } from '@admin/composables/useCrud'

type HydraCollection<T = any> = {
  '@context'?: string
  '@id'?: string
  '@type'?: string
  'hydra:member'?: T[]
  member?: T[]
  'hydra:totalItems'?: number
  totalItems?: number
}

type RelatedProductRow = RelatedProductDto & {
  sortOrderProxy: string
  relatedProductData?: ProductDto | null
}

interface Props {
  productId: string
  isCreating: boolean
}

const props = defineProps<Props>()

const repo = new ProductRepository()
const relatedProductRepo = new RelatedProductRepository()
const crud = useCrud<RelatedProductDto>(relatedProductRepo)
const state = crud.state

const errorMessage = ref<string | null>(null)
const autocompleteKey = ref(0)
const deleteOpen = ref(false)
const deletingId = ref<number | null>(null)
const addingProduct = ref(false)

const relatedProducts = ref<RelatedProductRow[]>([])
const productCache = ref<Record<string, ProductDto>>({})

const isLoading = computed(() => !!state.loading)

type AutocompleteItem = { id: number | string; name: string | null; firstImageUrl?: string | null }

async function searchProducts(
  query: string,
  limit: number
): Promise<HydraCollection<{ id: number | string; name: string | null; firstImageUrl?: string | null }>> {
  const res = await repo.searchProducts(query, limit) as any
  const original = (res['hydra:member'] ?? res.member ?? []) as Array<any>
  const currentProductId = Number(props.productId)
  
  // Исключаем текущий товар из результатов поиска
  const mapped = original
    .map((p) => {
      const id = p?.id
      if (id === null || id === undefined || Number(id) === currentProductId) return null
      return {
        id: id as number,
        name: (p?.name ?? null) as string | null,
        firstImageUrl: (p?.firstImageUrl ?? null) as string | null,
      }
    })
    .filter(Boolean) as Array<{ id: number; name: string | null; firstImageUrl?: string | null }>

  return {
    ...(res as any),
    'hydra:member': mapped,
    member: mapped,
  }
}

async function handleProductSelect(item: AutocompleteItem): Promise<void> {
  addingProduct.value = true
  errorMessage.value = null
  try {
    const relatedProductId = Number(item.id)
    const productId = Number(props.productId)
    await relatedProductRepo.create({
      product: `/api/products/${productId}`,
      relatedProduct: `/api/products/${relatedProductId}`,
    })
    // Сбросить поле поиска и обновить список
    autocompleteKey.value += 1
    await loadRelatedProducts()
  } catch (e: any) {
    const violations = e?.violations as Array<{ message?: string }> | undefined
    if (violations && violations.length > 0) {
      errorMessage.value = violations[0]?.message || 'Ошибка валидации'
    } else {
      errorMessage.value = e?.message || 'Не удалось добавить похожий товар'
    }
  } finally {
    addingProduct.value = false
  }
}

function extractProductId(product: string | ProductDto): number | null {
  if (typeof product === 'string') {
    // Извлекаем ID из IRI вида /api/products/123 или /v2/products/123
    const match = product.match(/\/(?:api\/|v2\/)?products\/(\d+)/)
    return match ? Number(match[1]) : null
  }
  return product?.id ? Number(product.id) : null
}

async function fetchProductData(productId: number): Promise<ProductDto | null> {
  if (productCache.value[productId]) {
    return productCache.value[productId]
  }
  try {
    const product = await repo.findById(productId)
    productCache.value[productId] = product as ProductDto
    return product as ProductDto
  } catch {
    return null
  }
}

async function syncRows() {
  const items = ((state.items ?? []) as RelatedProductDto[]).slice()
  // Фильтруем только похожие товары для текущего продукта
  const productId = Number(props.productId)
  const filteredItems = items.filter((item) => {
    const itemProductId = extractProductId(item.product)
    return itemProductId === productId
  })
  
  // Сортируем по sortOrder
  filteredItems.sort((a, b) => {
    const ao = a.sortOrder ?? 0
    const bo = b.sortOrder ?? 0
    return ao - bo
  })
  
  relatedProducts.value = filteredItems.map((item) => ({
    ...item,
    sortOrderProxy: String(item.sortOrder ?? 0),
    relatedProductData: typeof item.relatedProduct === 'object' ? item.relatedProduct : null,
  }))
  
  // Загружаем данные продуктов для тех, у кого relatedProduct - это IRI
  const productPromises = filteredItems
    .filter((item) => typeof item.relatedProduct === 'string')
    .map(async (item) => {
      const relatedProductId = extractProductId(item.relatedProduct as string)
      if (relatedProductId) {
        const productData = await fetchProductData(relatedProductId)
        const row = relatedProducts.value.find((r) => r.id === item.id)
        if (row) {
          row.relatedProductData = productData
        }
      }
    })
  
  await Promise.all(productPromises)
}

async function loadRelatedProducts() {
  const productId = Number(props.productId)
  await crud.fetchAll({
    itemsPerPage: 500,
    sort: { sortOrder: 'asc' },
    filters: { product: `/api/products/${productId}` },
  })
  await syncRows()
}

watch(
  () => state.items,
  async () => {
    await syncRows()
  },
  { deep: true }
)

watch(
  () => props.productId,
  async () => {
    if (!props.isCreating) {
      await loadRelatedProducts()
    }
  }
)

onMounted(async () => {
  if (!props.isCreating) {
    await loadRelatedProducts()
  }
})

function getProductName(item: RelatedProductRow): string | null {
  if (item.relatedProductData) {
    return item.relatedProductData.name ?? null
  }
  if (typeof item.relatedProduct === 'object' && item.relatedProduct) {
    return item.relatedProduct.name ?? null
  }
  return null
}

function getProductImage(item: RelatedProductRow): string | null {
  const product = item.relatedProductData || (typeof item.relatedProduct === 'object' ? item.relatedProduct : null)
  if (!product) return null
  
  // Сначала проверяем firstImageUrl
  if (product.firstImageUrl) {
    return product.firstImageUrl
  }
  
  // Если firstImageUrl нет, берем первое изображение из массива image
  if (product.image && Array.isArray(product.image) && product.image.length > 0) {
    // Сортируем по sortOrder, если есть
    const sortedImages = [...product.image].sort((a, b) => (a.sortOrder ?? 0) - (b.sortOrder ?? 0))
    const firstImage = sortedImages[0]
    if (firstImage?.imageUrl) {
      return firstImage.imageUrl
    }
  }
  
  return null
}

async function saveSortOrder(item: RelatedProductRow): Promise<void> {
  if (item.id === undefined || item.id === null) return
  const sortOrder = Number(item.sortOrderProxy) || 0
  try {
    await crud.update(item.id, { sortOrder })
    // Обновляем локальное значение
    item.sortOrder = sortOrder
    // Пересортируем список
    await syncRows()
  } catch (e: any) {
    errorMessage.value = e?.message || 'Не удалось сохранить сортировку'
  }
}

function confirmDelete(id: number): void {
  deletingId.value = id
  deleteOpen.value = true
}

async function performDelete(): Promise<void> {
  if (deletingId.value === null) return
  try {
    await crud.remove(deletingId.value)
    deleteOpen.value = false
    deletingId.value = null
    // Список обновится автоматически через watch, но можно явно вызвать syncRows для надежности
    await syncRows()
  } catch (e: any) {
    errorMessage.value = e?.message || 'Не удалось удалить похожий товар'
  }
}
</script>

<style scoped></style>


