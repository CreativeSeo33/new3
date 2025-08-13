<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-xl font-semibold">
        {{ isCreating ? 'Новый товар' : `Товар #${id}` }}
      </h1>
      <div class="flex gap-2">
        <Button variant="secondary" :disabled="saving" @click="handleSave">Сохранить</Button>
      </div>
    </div>

    <div v-if="saveError" class="p-3 rounded border border-red-200 bg-red-50 text-sm text-red-800">
      {{ saveError }}
    </div>

    <TabsRoot v-model="activeTab" class="w-full" :unmount-on-hide="false">
      <TabsList aria-label="product form tabs" class="relative flex w-full gap-2 border-b border-gray-200">
        <TabsIndicator class="absolute bottom-[-1px] h-[2px] bg-brand-500 transition-all" />
        <TabsTrigger
          v-for="tab in tabs"
          :key="tab.value"
          :value="tab.value"
          class="px-3 py-2 text-sm data-[state=active]:text-brand-600"
        >
          {{ tab.label }}
        </TabsTrigger>
      </TabsList>

      <TabsContent value="description" class="pt-6">
        <ProductDescriptionForm :form="form" :errors="errors" :validate-field="validateField" />
      </TabsContent>

      <TabsContent value="categories" class="pt-6">
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-12">
          <div class="lg:col-span-8">
            <div class="rounded-md border p-4 dark:border-neutral-800">
              <div class="mb-2 text-sm font-medium">Категории</div>
              <ProductCategoryTree :nodes="categoryTree" :checked-ids="selectedCategoryIds" :main-id="mainCategoryId" @toggle="toggleCategory" @set-main="setMainCategory" />
            </div>
          </div>
          <div class="lg:col-span-4">
            <div class="rounded-md border p-4 text-sm text-neutral-600 dark:border-neutral-800 dark:text-neutral-300">
              Отмечайте чекбокс, чтобы привязать товар к категории. Снятие чекбокса удалит привязку.
            </div>
          </div>
        </div>
      </TabsContent>

      <TabsContent value="attributes" class="pt-6">
        <div class="text-sm text-muted-foreground">Пока пусто</div>
      </TabsContent>

      <TabsContent value="photos" class="pt-6">
        <div class="flex items-center justify-between mb-4">
          <div class="text-sm text-neutral-600 dark:text-neutral-300">Добавляйте изображения из каталога /public/img</div>
          <DialogRoot v-model:open="photosModalOpen">
            <DialogTrigger as-child>
              <Button variant="secondary">Добавить изображение</Button>
            </DialogTrigger>
            <DialogOverlay class="fixed inset-0 bg-black/40" />
            <DialogContent class="fixed left-1/2 top-1/2 w-[90vw] max-w-5xl -translate-x-1/2 -translate-y-1/2 rounded-md bg-white p-0 shadow-lg focus:outline-none">
              <div class="flex items-center justify-between border-b px-4 py-3">
                <DialogTitle class="text-base font-medium">Библиотека изображений</DialogTitle>
                <DialogClose as-child>
                  <button class="text-neutral-500 hover:text-neutral-700">✕</button>
                </DialogClose>
              </div>
              <div class="grid grid-cols-12 gap-0">
                <div class="col-span-4 border-r max-h-[70vh] overflow-y-auto">
                  <div class="px-3 py-2 cursor-pointer hover:bg-neutral-50" :class="{ 'bg-neutral-100': selectedFolderPath === '' }" @click="selectFolder('')">
                    /img
                  </div>
                  <div v-for="f in flattenedFolders" :key="f.path" class="py-1 cursor-pointer hover:bg-neutral-50" :style="{ paddingLeft: (12 + f.depth * 16) + 'px' }" :class="{ 'bg-neutral-100': selectedFolderPath === f.path }" @click="selectFolder(f.path)">
                    <span class="px-3">{{ f.name }}</span>
                  </div>
                </div>
                <div class="col-span-8 max-h-[70vh] overflow-y-auto p-4">
                  <div class="flex items-center justify-between mb-3">
                    <div></div>
                    <div v-if="selectedImages.size > 0" class="">
                      <Button variant="secondary" @click="attachSelected" :disabled="attachLoading">
                        Выбрано: {{ selectedImages.size }}
                      </Button>
                    </div>
                  </div>
                  <div v-if="imagesLoading" class="text-sm text-neutral-500">Загрузка...</div>
                  <div v-else-if="imagesError" class="text-sm text-red-600">{{ imagesError }}</div>
                  <div v-else class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3">
                    <label v-for="img in folderImages" :key="img.relative" class="group cursor-pointer block">
                      <div class="relative">
                        <input type="checkbox" class="absolute left-2 top-2 z-10 h-4 w-4" :checked="selectedImages.has(img.relative)" @change="toggleImage(img.relative, ($event.target as HTMLInputElement).checked)" />
                        <div class="aspect-square w-full overflow-hidden rounded border bg-neutral-50">
                          <img :src="img.url" :alt="img.name" class="h-full w-full object-cover" loading="lazy" />
                        </div>
                      </div>
                      <div class="mt-1 truncate text-xs text-neutral-600">{{ img.name }}</div>
                    </label>
                  </div>
                </div>
              </div>
            </DialogContent>
          </DialogRoot>
        </div>

        <div class="mb-2 text-sm font-medium">Текущие фото товара</div>
        <div v-if="isCreating" class="text-sm text-neutral-500">Сохраните товар, чтобы добавлять и видеть фотографии.</div>
        <div v-else>
          <div v-if="productImagesLoading" class="text-sm text-neutral-500">Загрузка фото...</div>
          <div v-else-if="productImagesError" class="text-sm text-red-600">{{ productImagesError }}</div>
          <div v-else class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-3">
            <div v-for="pi in productImages" :key="pi.id" class="rounded border overflow-hidden relative group">
              <button class="absolute right-2 top-2 z-10 rounded bg-white/90 px-2 py-1 text-xs text-red-600 shadow hover:bg-white" @click="deleteProductImage(pi.id)" title="Удалить">Удалить</button>
              <div class="aspect-square bg-neutral-50">
                <img :src="pi.imageUrl" class="h-full w-full object-cover" loading="lazy" />
              </div>
              <div class="px-2 py-1 text-xs text-neutral-600">#{{ pi.id }} · {{ pi.sortOrder }}</div>
            </div>
          </div>
        </div>
      </TabsContent>
    </TabsRoot>

    <!-- Toast instance(s) -->
    <ToastRoot v-for="n in toastCount" :key="n" type="foreground" :duration="3000">
      <ToastDescription>{{ lastToastMessage }}</ToastDescription>
    </ToastRoot>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Button from '@admin/ui/components/Button.vue'
import { TabsContent, TabsIndicator, TabsList, TabsRoot, TabsTrigger, ToastDescription, ToastRoot, DialogClose, DialogContent, DialogOverlay, DialogRoot, DialogTitle, DialogTrigger } from 'reka-ui'
import ProductDescriptionForm from '@admin/components/forms/ProductDescriptionForm.vue'
import ProductCategoryTree from '@admin/components/ProductCategoryTree.vue'
import { useProductForm } from '@admin/composables/useProductForm'
import { useProductSave } from '@admin/composables/useProductSave'
import type { ProductTab } from '@admin/types/product'
import { ProductRepository, type ProductDto } from '@admin/repositories/ProductRepository'
import { CategoryRepository, type CategoryDto } from '@admin/repositories/CategoryRepository'
import { ProductCategoryRepository } from '@admin/repositories/ProductCategoryRepository'

const tabs: ProductTab[] = [
  { value: 'description', label: 'Описание товара' },
  { value: 'categories', label: 'Категории' },
  { value: 'attributes', label: 'Аттрибуты' },
  { value: 'photos', label: 'Фотографии' },
]

const route = useRoute()
const router = useRouter()
const id = computed(() => route.params.id as string)
const isCreating = computed(() => !id.value || id.value === 'new')
const activeTab = ref<string>('description')
const tabParamKey = 'tab'
const validTabs = new Set(tabs.map(t => t.value))

const {
  form,
  errors,
  priceInput,
  quantityInput,
  sortOrderInput,
  validateField,
  validateForm,
} = useProductForm()

const { saving, error: saveError, saveProduct } = useProductSave()

// Load existing product for edit
const repo = new ProductRepository()
const categoryRepo = new CategoryRepository()
const productCategoryRepo = new ProductCategoryRepository()
const toNum = (v: unknown): number | null => {
  if (v === null || v === undefined || v === '') return null
  const n = Number(String(v).trim())
  return Number.isFinite(n) ? n : null
}
const hydrateForm = (dto: ProductDto) => {
  Object.assign(form, {
    name: dto.name ?? '',
    slug: dto.slug ?? '',
    price: toNum(dto.price),
    salePrice: toNum((dto as any).salePrice),
    status: dto.status ?? true,
    quantity: toNum(dto.quantity),
    description: dto.description ?? '',
    metaTitle: dto.metaTitle ?? '',
    metaDescription: dto.metaDescription ?? '',
    h1: dto.h1 ?? '',
    sortOrder: (dto as any).sortOrder ?? 0,
  })
}
const loadIfEditing = async () => {
  if (isCreating.value) return
  const dto = await repo.findById(id.value)
  hydrateForm(dto)
  await loadProductCategories()
}

onMounted(() => {
  // sync tab from URL on load
  const q = route.query?.[tabParamKey]
  if (typeof q === 'string' && validTabs.has(q)) {
    activeTab.value = q
  }
  loadIfEditing()
})
watch(id, () => loadIfEditing())
// reflect activeTab to URL
watch(activeTab, (val) => {
  const query = { ...route.query, [tabParamKey]: val }
  router.replace({ query })
})
// react to external URL tab changes
watch(() => route.query[tabParamKey], (val) => {
  if (typeof val === 'string' && validTabs.has(val) && val !== activeTab.value) {
    activeTab.value = val
  }
})

// Categories tree and selection
const selectedCategoryIds = ref<Set<number>>(new Set())
const mainCategoryId = ref<number | null>(null)
const categoryTree = ref<Array<{ id: number; label: string; children?: any[] }>>([])

async function loadCategoriesTree() {
  const res = await categoryRepo.findAll({ itemsPerPage: 1000 }) as any
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
}

async function loadProductCategories() {
  // fetch relations; assuming API Platform default path /product_to_categories?product=/api/products/{id}
  const iri = `/products/${id.value}`
  const data = await productCategoryRepo.findAll({ itemsPerPage: 1000, filters: { product: `/api${iri}` } }) as any
  const items = (data['hydra:member'] ?? data.member ?? []) as any[]
  selectedCategoryIds.value = new Set(items.map((r: any) => Number(r.category?.split('/').pop() || r.categoryId || 0)).filter(Boolean))
  const main = items.find((r: any) => r.isParent)
  mainCategoryId.value = main ? Number(main.category?.split('/').pop() || main.categoryId || null) : null
}

onMounted(async () => {
  await loadCategoriesTree()
  if (!isCreating.value) await loadProductCategories()
})

async function toggleCategory(idNum: number, checked: boolean) {
  if (isCreating.value) {
    publishToast('Сохраните товар, чтобы привязывать категории')
    return
  }
  const productIri = `/api/products/${id.value}`
  const categoryIri = `/api/categories/${idNum}`
  if (checked) {
    // create relation
    await productCategoryRepo.create({ product: productIri, category: categoryIri, visibility: true, isParent: false })
    selectedCategoryIds.value.add(idNum)
  } else {
    // need to find relation id to delete; fetch minimal
    const data = await productCategoryRepo.findAll({ itemsPerPage: 100, filters: { product: productIri, category: categoryIri } }) as any
    const rel = (data['hydra:member'] ?? data.member ?? []).at(0)
    if (rel?.id) {
      await productCategoryRepo.delete(rel.id)
      selectedCategoryIds.value.delete(idNum)
      if (mainCategoryId.value === idNum) mainCategoryId.value = null
    }
  }
}

async function setMainCategory(idNum: number) {
  if (isCreating.value) return
  if (!selectedCategoryIds.value.has(idNum)) {
    // если не отмечено — создадим связь сначала
    await toggleCategory(idNum, true)
  }
  const productIri = `/api/products/${id.value}`
  // снимаем флаг isParent у всех текущих связей
  const all = await productCategoryRepo.findAll({ itemsPerPage: 1000, filters: { product: productIri } }) as any
  const rels = (all['hydra:member'] ?? all.member ?? [])
  for (const r of rels) {
    const cid = Number(r.category?.split('/').pop() || r.categoryId || 0)
    const shouldBeParent = cid === idNum
    if (r.id && (r.isParent ?? false) !== shouldBeParent) {
      await productCategoryRepo.partialUpdate(r.id, { isParent: shouldBeParent })
    }
  }
  mainCategoryId.value = idNum
}

const handleSave = async () => {
  if (!validateForm()) {
    activeTab.value = 'description'
    return
  }
  const result = await saveProduct(id.value, form)
  if (result.success) {
    // показать toast + редирект
    publishToast('Товар сохранён')
    const newId = (result.result as any)?.id ?? id.value
    router.push({ name: 'admin-product-form', params: { id: newId } })
  }
}

// Imperative toast publisher per Reka UI docs (Duplicate toasts)
const toastCount = ref(0)
function publishToast(message: string) {
  lastToastMessage.value = message
  toastCount.value++
}
const lastToastMessage = ref('')

// Photos modal state
const photosModalOpen = ref(false)
type FolderNode = { name: string; path: string; children?: FolderNode[] }
type FlatFolder = { name: string; path: string; depth: number }
const folderTree = ref<FolderNode[]>([])
const flattenedFolders = computed<FlatFolder[]>(() => {
  const acc: FlatFolder[] = []
  const walk = (nodes: FolderNode[], depth: number) => {
    for (const n of nodes) {
      acc.push({ name: n.name, path: n.path, depth })
      if (n.children && n.children.length) walk(n.children, depth + 1)
    }
  }
  walk(folderTree.value, 0)
  return acc
})
const selectedFolderPath = ref<string>('')
const folderImages = ref<Array<{ name: string; relative: string; url: string }>>([])
const imagesLoading = ref(false)
const imagesError = ref<string>('')
const selectedImages = ref<Set<string>>(new Set())
const attachLoading = ref(false)

async function fetchFolderTree() {
  const res = await fetch('/api/admin/media/tree', { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
  if (!res.ok) throw new Error('Не удалось получить дерево каталогов')
  const ct = res.headers.get('content-type') || ''
  if (!ct.includes('application/json')) {
    const txt = await res.text()
    throw new Error('Получен не-JSON ответ. Возможно, требуется авторизация или произошла ошибка. ' + txt.slice(0, 120))
  }
  const data = await res.json()
  folderTree.value = Array.isArray(data.tree) ? data.tree : []
}

async function fetchImages(dir: string) {
  imagesLoading.value = true
  imagesError.value = ''
  try {
    const url = new URL('/api/admin/media/list', window.location.origin)
    if (dir) url.searchParams.set('dir', dir)
    const res = await fetch(url.toString(), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
    if (!res.ok) throw new Error('Не удалось загрузить изображения')
    const ct = res.headers.get('content-type') || ''
    if (!ct.includes('application/json')) {
      const txt = await res.text()
      throw new Error('Получен не-JSON ответ. Возможно, редирект или HTML ошибка. ' + txt.slice(0, 120))
    }
    const data = await res.json()
    folderImages.value = Array.isArray(data.items) ? data.items : []
  } catch (e: any) {
    imagesError.value = e?.message || 'Ошибка'
  } finally {
    imagesLoading.value = false
  }
}

function selectFolder(path: string) {
  selectedFolderPath.value = path
  fetchImages(path)
}

function toggleImage(relative: string, checked: boolean) {
  const set = new Set(selectedImages.value)
  if (checked) set.add(relative)
  else set.delete(relative)
  selectedImages.value = set
}

async function attachSelected() {
  if (selectedImages.value.size === 0) return
  if (isCreating.value) {
    publishToast('Сначала сохраните товар')
    return
  }
  attachLoading.value = true
  try {
    const res = await fetch(`/api/admin/media/product/${id.value}/images`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify({ items: Array.from(selectedImages.value) })
    })
    if (!res.ok) throw new Error('Не удалось сохранить изображения')
    const ct = res.headers.get('content-type') || ''
    if (!ct.includes('application/json')) throw new Error('Неожиданный ответ сервера')
    await res.json()
    await fetchProductImages()
    publishToast('Изображения добавлены')
    selectedImages.value = new Set()
    photosModalOpen.value = false
  } catch (e: any) {
    publishToast(e?.message || 'Ошибка')
  } finally {
    attachLoading.value = false
  }
}

// Product images list
type ProductImageDto = { id: number; imageUrl: string; sortOrder: number }
const productImages = ref<ProductImageDto[]>([])
const productImagesLoading = ref(false)
const productImagesError = ref<string>('')
const deletingImageIds = ref<Set<number>>(new Set())

async function fetchProductImages() {
  if (isCreating.value) return
  productImagesLoading.value = true
  productImagesError.value = ''
  try {
    const res = await fetch(`/api/v2/products/${id.value}`, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
    if (!res.ok) throw new Error('Не удалось загрузить товар')
    const data = await res.json()
    const imgs = Array.isArray(data.image) ? data.image : (Array.isArray(data.images) ? data.images : [])
    productImages.value = imgs.map((it: any) => ({ id: Number(it.id), imageUrl: String(it.imageUrl), sortOrder: Number(it.sortOrder ?? 0) }))
  } catch (e: any) {
    productImagesError.value = e?.message || 'Ошибка'
  } finally {
    productImagesLoading.value = false
  }
}

watch(id, () => fetchProductImages())
onMounted(() => fetchProductImages())

async function deleteProductImage(imageId: number) {
  if (!imageId) return
  const set = new Set(deletingImageIds.value); set.add(imageId); deletingImageIds.value = set
  try {
    const res = await fetch(`/api/admin/media/product-image/${imageId}`, { method: 'DELETE', credentials: 'same-origin' })
    if (!res.ok) throw new Error('Не удалось удалить изображение')
    await fetchProductImages()
    publishToast('Изображение удалено')
  } catch (e: any) {
    publishToast(e?.message || 'Ошибка удаления')
  } finally {
    const set2 = new Set(deletingImageIds.value); set2.delete(imageId); deletingImageIds.value = set2
  }
}

watch(photosModalOpen, async (open) => {
  if (open) {
    try {
      if (folderTree.value.length === 0) await fetchFolderTree()
      await fetchImages(selectedFolderPath.value)
    } catch (e) {
      // ignore; UI will show error via imagesError
    }
  }
})
</script>

<style scoped></style>


