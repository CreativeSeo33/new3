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
        <div class="text-sm text-muted-foreground">Пока пусто</div>
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
import { TabsContent, TabsIndicator, TabsList, TabsRoot, TabsTrigger, ToastDescription, ToastRoot } from 'reka-ui'
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
</script>

<style scoped></style>


