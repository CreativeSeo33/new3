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
        <ProductAttributesAddModal v-model="attrModalOpen" @add="handleAddAttribute" />
        <ConfirmDialog v-model="deletePAOpen" :title="'Удалить атрибут?'" :description="'Это действие необратимо'" confirm-text="Удалить" :danger="true" @confirm="performDeletePA" />
      </TabsContent>

      <TabsContent value="photos" class="pt-6">
        <ProductPhotos :product-id="String(id)" :is-creating="isCreating" @toast="publishToast" />
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
  await loadProductAttributes()
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
    const allGroups = await attributeGroupRepo.findAll({ itemsPerPage: 1000 }) as any
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


