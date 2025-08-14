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
              <div v-if="categoryLoading" class="text-sm text-neutral-500">Загрузка…</div>
              <ProductCategoryTree
                v-else
                :key="String(id)"
                :nodes="categoryTree"
                :checked-ids="selectedCategoryIds"
                :main-id="mainCategoryId"
                @toggle="toggleCategory"
                @set-main="setMainCategory"
              />
            </div>
          </div>
          <div class="lg:col-span-4">
            <div class="rounded-md border p-4 text-sm text-neutral-600 dark:border-neutral-800 dark:text-neutral-300">
              Отмечайте чекбокс, чтобы привязать товар к категории. Изменения применяются после нажатия «Сохранить».
            </div>
          </div>
        </div>
      </TabsContent>

      <TabsContent value="attributes" class="pt-6">
        <div class="rounded-md border p-4 dark:border-neutral-800">
          <div class="mb-3 flex items-center justify-between">
            <div class="text-sm font-medium">Атрибуты</div>
            <Button size="sm" @click="attrModalOpen = true">Добавить</Button>
          </div>
          <div v-if="attrLoading" class="text-sm text-neutral-500">Загрузка…</div>
          <div v-else class="space-y-6">
            <div v-for="group in productAttrGroups" :key="group.groupIri || '__no_group__'" class="rounded-md border">
              <div class="border-b px-3 py-2 text-sm font-medium">{{ group.groupName }}</div>
              <table class="w-full text-sm">
                <thead class="bg-neutral-50 text-neutral-600 dark:bg-neutral-900/40 dark:text-neutral-300">
                  <tr>
                    <th class="px-3 py-2 text-left">Атрибут</th>
                    <th class="px-3 py-2 text-left">Значение</th>
                    <th class="px-3 py-2 text-left w-28">Действия</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="item in group.items" :key="item.id" class="border-t">
                    <td class="px-3 py-2">{{ item.attributeName }}</td>
                    <td class="px-3 py-2">
                      <input
                        v-model="item.textProxy"
                        type="text"
                        class="h-9 w-full rounded-md border px-2 text-sm dark:border-neutral-800 dark:bg-neutral-900"
                        @blur="() => saveProductAttribute(item)"
                      />
                    </td>
                    <td class="px-3 py-2">
                      <button type="button" class="h-8 rounded-md bg-red-600 px-2 text-xs font-medium text-white hover:bg-red-700" @click="confirmDeletePA(item.id)">Удалить</button>
                    </td>
                  </tr>
                  <tr v-if="group.items.length === 0">
                    <td colspan="3" class="px-3 py-6 text-center text-neutral-500">Нет атрибутов</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
        <ProductAttributesAddModal v-if="attrModalOpen" v-model="attrModalOpen" @add="handleAddAttribute" />
        <ConfirmDialog v-model="deletePAOpen" :title="'Удалить атрибут?'" :description="'Это действие необратимо'" confirm-text="Удалить" :danger="true" @confirm="performDeletePA" />
      </TabsContent>

      <TabsContent value="photos" class="pt-6">
        <ProductPhotos v-if="activeTab === 'photos'" :product-id="String(id)" :is-creating="isCreating" @toast="publishToast" />
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
import ProductPhotos from '@admin/components/forms/ProductPhotos.vue'
import ProductAttributesAddModal from '@admin/components/forms/ProductAttributesAddModal.vue'
import ConfirmDialog from '@admin/components/ConfirmDialog.vue'
import { useProductForm } from '@admin/composables/useProductForm'
import { useProductSave } from '@admin/composables/useProductSave'
import type { ProductTab } from '@admin/types/product'
import { ProductRepository, type ProductDto } from '@admin/repositories/ProductRepository'
import { ProductAttributeGroupRepository } from '@admin/repositories/ProductAttributeGroupRepository'
import { ProductAttributeRepository } from '@admin/repositories/ProductAttributeRepository'
import { AttributeRepository } from '@admin/repositories/AttributeRepository'
import { AttributeGroupRepository } from '@admin/repositories/AttributeGroupRepository'
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
}

onMounted(() => {
  // sync tab from URL on load
  const q = route.query?.[tabParamKey]
  if (typeof q === 'string' && validTabs.has(q)) {
    activeTab.value = q
  }
  loadIfEditing()
})
watch(id, async () => {
  // reset per-product lazy flags and state
  selectedCategoryIds.value = new Set()
  mainCategoryId.value = null
  categoriesInitialized.value = false
  // keep categoryTree and categoriesLoaded; they'll refetch on demand if product differs
  productAttrGroups.value = []
  attributesLoaded.value = false
  await loadIfEditing()
  // если пользователь находится на вкладке, подтянем данные сразу
  if (activeTab.value === 'categories') {
    if (!categoriesLoaded.value) await loadCategoriesTree()
    if (!isCreating.value) await loadProductCategories()
  } else if (activeTab.value === 'attributes') {
    if (!isCreating.value) await loadProductAttributes()
  }
})
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
const categoriesLoaded = ref(false)
const categoriesInitialized = ref(false)
const categoryLoading = ref(false)

async function loadCategoriesTree() {
  const res = await categoryRepo.findAllCached({ itemsPerPage: 1000 }) as any
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

async function loadProductCategories() {
  // fetch relations; assuming API Platform default path /product_to_categories?product=/api/products/{id}
  const iri = `/products/${id.value}`
  const expectedProductIri = `/api${iri}`
  const data = await productCategoryRepo.findAll({ itemsPerPage: 1000, filters: { product: expectedProductIri } }) as any
  const allItems = (data['hydra:member'] ?? data.member ?? []) as any[]
  // Frontend-guard: фильтруем строго по product IRI на случай, если сервер игнорирует фильтр
  const items = allItems.filter((r: any) => {
    const p = r.product
    if (typeof p === 'string') return p === expectedProductIri
    if (p && typeof p === 'object') {
      const iriObj = p['@id'] || (p.id ? `/api/products/${p.id}` : null)
      return iriObj === expectedProductIri
    }
    return false
  })
  selectedCategoryIds.value = new Set(items.map((r: any) => Number(r.category?.split('/').pop() || r.categoryId || 0)).filter(Boolean))
  const main = items.find((r: any) => r.isParent)
  mainCategoryId.value = main ? Number(main.category?.split('/').pop() || main.categoryId || null) : null
  categoriesInitialized.value = true
}

async function loadCategoriesBootstrap() {
  categoryLoading.value = true
  try {
    const res = await fetch(`/api/admin/products/${id.value}/bootstrap`, { headers: { Accept: 'application/json' } })
    if (!res.ok) throw new Error('bootstrap failed')
    const data = await res.json()
    const list = Array.isArray(data.categories) ? data.categories : []
    // build tree
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
    // selection
    const ids: number[] = Array.isArray(data.selectedCategoryIds) ? data.selectedCategoryIds.map((x: any) => Number(x)).filter((n: any) => Number.isFinite(n)) : []
    selectedCategoryIds.value = new Set(ids)
    mainCategoryId.value = (data.mainCategoryId != null && Number.isFinite(Number(data.mainCategoryId))) ? Number(data.mainCategoryId) : null
    categoriesInitialized.value = true
  } catch {
    // fallback to legacy loaders
    if (!categoriesLoaded.value) await loadCategoriesTree()
    if (!categoriesInitialized.value) await loadProductCategories()
  } finally {
    categoryLoading.value = false
  }
}

// Ленивые загрузки по вкладкам
watch(activeTab, async (val) => {
  if (val === 'categories') {
    // сразу сбросим выбранные, чтобы не мигали значения от предыдущего товара
    if (!categoriesInitialized.value) {
      selectedCategoryIds.value = new Set()
      mainCategoryId.value = null
    }
    if (!isCreating.value) {
      await loadCategoriesBootstrap()
    } else {
      // для нового товара достаточно дерева
      categoryLoading.value = true
      if (!categoriesLoaded.value) await loadCategoriesTree()
      categoryLoading.value = false
    }
  }
  if (val === 'attributes') {
    if (!isCreating.value && !attributesLoaded.value) await loadProductAttributes()
  }
}, { immediate: false })

async function toggleCategory(idNum: number, checked: boolean) {
  if (isCreating.value) {
    publishToast('Сохраните товар, чтобы привязывать категории')
    return
  }
  const next = new Set<number>(selectedCategoryIds.value)
  if (checked) next.add(idNum)
  else {
    next.delete(idNum)
    if (mainCategoryId.value === idNum) mainCategoryId.value = null
  }
  selectedCategoryIds.value = next
}

async function setMainCategory(idNum: number) {
  if (isCreating.value) return
  if (!selectedCategoryIds.value.has(idNum)) {
    const next = new Set<number>(selectedCategoryIds.value)
    next.add(idNum)
    selectedCategoryIds.value = next
  }
  mainCategoryId.value = idNum
}

async function saveProductCategories(productNumericId: number | string) {
  const productIri = `/api/products/${productNumericId}`
  const desiredIds = new Set<number>(selectedCategoryIds.value)
  const desiredMainId = mainCategoryId.value

  const all = await productCategoryRepo.findAll({ itemsPerPage: 1000, filters: { product: productIri } }) as any
  const rels = (all['hydra:member'] ?? all.member ?? []) as any[]
  const byCategory = new Map<number, any>()
  for (const r of rels) {
    const cid = Number(r.category?.split('/').pop() || (r as any).categoryId || 0)
    if (cid) byCategory.set(cid, r)
  }

  // delete removed
  for (const [cid, r] of byCategory.entries()) {
    if (!desiredIds.has(cid) && r?.id) {
      await productCategoryRepo.delete(r.id)
    }
  }

  // update existing isParent
  for (const [cid, r] of byCategory.entries()) {
    if (desiredIds.has(cid) && r?.id) {
      const shouldBeParent = desiredMainId != null && cid === desiredMainId
      if ((r.isParent ?? false) !== shouldBeParent) {
        await productCategoryRepo.partialUpdate(r.id, { isParent: shouldBeParent })
      }
    }
  }

  // create new
  for (const cid of desiredIds.values()) {
    if (!byCategory.has(cid)) {
      await productCategoryRepo.create({
        product: productIri,
        category: `/api/categories/${cid}`,
        visibility: true,
        isParent: desiredMainId != null && cid === desiredMainId,
      })
    }
  }
}

const handleSave = async () => {
  if (!validateForm()) {
    activeTab.value = 'description'
    return
  }
  const result = await saveProduct(id.value, form)
  if (result.success) {
    const newId = (result.result as any)?.id ?? id.value
    // Сохраняем категории, только если они были загружены (чтобы не снести связи без открытия вкладки)
    if (categoriesInitialized.value) {
      await saveProductCategories(newId)
    }
    // показать toast + редирект
    publishToast('Товар сохранён')
    router.push({ name: 'admin-product-form', params: { id: newId }, query: { ...route.query } })
  }
}

// Imperative toast publisher per Reka UI docs (Duplicate toasts)
const toastCount = ref(0)
function publishToast(message: string) {
  lastToastMessage.value = message
  toastCount.value++
}
const lastToastMessage = ref('')

// Attributes add modal
const attrModalOpen = ref(false)
const productAttributeGroupRepo = new ProductAttributeGroupRepository()
const productAttributeRepo = new ProductAttributeRepository()
const attributeRepo = new AttributeRepository()
const attributeGroupRepo = new AttributeGroupRepository()
const attrLoading = ref(false)
const attributesLoaded = ref(false)
type ProductAttributeRow = { id: number; attributeName: string; textProxy: string; pagIri: string }
const productAttrGroups = ref<Array<{ groupIri: string | null; groupName: string; items: ProductAttributeRow[] }>>([])
async function handleAddAttribute(attributeIri: string) {
  if (isCreating.value) {
    publishToast('Сначала сохраните товар')
    return
  }
  // 1) найти группу у атрибута
  const attributeId = Number(attributeIri.split('/').pop())
  // минимальный GET атрибута
  const attr = await attributeRepo.findById(attributeId) as any
  const groupRaw = attr.attributeGroup as any
  const groupIri: string | null = typeof groupRaw === 'string' ? groupRaw : groupRaw?.['@id'] ?? (groupRaw?.id ? `/api/attribute_groups/${groupRaw.id}` : null)

  // 2) получить/создать ProductAttributeGroup для пары (product, attributeGroup)
  const productIri = `/api/products/${id.value}`
  let pag = null as any
  if (groupIri) {
    // ищем группу товара с этой группой атрибутов
    const found = await productAttributeGroupRepo.findAll({ itemsPerPage: 10, filters: { product: productIri, attributeGroup: groupIri } }) as any
    const member = (found['hydra:member'] ?? found.member ?? [])[0]
    if (member?.id) {
      pag = member
    }
  }
  if (!pag) {
    pag = await productAttributeGroupRepo.create({ product: productIri, attributeGroup: groupIri })
  }

  // 3) создать ProductAttribute в этой группе
  await productAttributeRepo.create({ productAttributeGroup: pag['@id'] ?? `/api/product_attribute_groups/${pag.id}`, attribute: attributeIri, text: null })
  publishToast('Атрибут добавлен к товару')
  await loadProductAttributes()
}

// load existing product attributes grouped
async function loadProductAttributes() {
  if (isCreating.value) return
  attrLoading.value = true
  try {
    const productIri = `/api/products/${id.value}`
    // Загрузим группы для продукта
    const groupsData = await productAttributeGroupRepo.findAll({ itemsPerPage: 1000, filters: { product: productIri } }) as any
    const groups = (groupsData['hydra:member'] ?? groupsData.member ?? []) as any[]

    // Сопоставим названия групп
    const allGroups = await attributeGroupRepo.findAllCached() as any
    const groupsDict = new Map<string, string>()
    for (const g of (allGroups['hydra:member'] ?? allGroups.member ?? [])) {
      groupsDict.set(g['@id'], g.name ?? `Группа ${g.id}`)
    }

    // Загрузим все ProductAttribute по каждому PAG отдельно (надёжно)
    const paBatches = await Promise.all(groups.map(g => productAttributeRepo.findAll({ itemsPerPage: 1000, filters: { productAttributeGroup: g['@id'] } }) as any))
    const pas: any[] = []
    for (const b of paBatches) pas.push(...((b['hydra:member'] ?? b.member ?? []) as any[]))

    // Нормализуем
    const byPag = new Map<string, any[]>()
    for (const pa of pas) {
      const k = typeof pa.productAttributeGroup === 'string' ? pa.productAttributeGroup : pa.productAttributeGroup?.['@id']
      if (!k) continue
      if (!byPag.has(k)) byPag.set(k, [])
      byPag.get(k)!.push(pa)
    }

    const rows: Array<{ groupIri: string | null; groupName: string; items: ProductAttributeRow[] }> = []
    for (const g of groups) {
      const groupIri = typeof g.attributeGroup === 'string' ? g.attributeGroup : g.attributeGroup?.['@id'] ?? null
      const title = groupIri ? (groupsDict.get(groupIri) ?? 'Группа') : 'Без группы'
      const items: ProductAttributeRow[] = []
      const paList = byPag.get(g['@id']) ?? []
      for (const pa of paList) {
        const attr = typeof pa.attribute === 'object' ? pa.attribute : null
        const attrName = attr?.name ?? `Атрибут ${String(pa.attribute || '').split('/').pop()}`
        items.push({ id: Number(pa.id), attributeName: attrName, textProxy: String(pa.text ?? ''), pagIri: g['@id'] })
      }
      rows.push({ groupIri, groupName: String(title), items })
    }
    productAttrGroups.value = rows
  } finally {
    attrLoading.value = false
    attributesLoaded.value = true
  }
}

async function saveProductAttribute(item: ProductAttributeRow) {
  await productAttributeRepo.partialUpdate(item.id, { text: item.textProxy })
  publishToast('Сохранено')
}

const deletePAOpen = ref(false)
const pendingPAId = ref<number | null>(null)
function confirmDeletePA(idNum: number) { pendingPAId.value = idNum; deletePAOpen.value = true }
async function performDeletePA() {
  if (pendingPAId.value == null) return
  await productAttributeRepo.delete(pendingPAId.value)
  for (const g of productAttrGroups.value) {
    g.items = g.items.filter(i => i.id !== pendingPAId.value!)
  }
  publishToast('Удалено')
  pendingPAId.value = null
}
</script>

<style scoped></style>


