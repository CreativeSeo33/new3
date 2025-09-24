<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-xl font-semibold">
        {{ isCreating ? 'Новый товар' : (form.name || `Товар #${id}`) }}
        <a
          v-if="!isCreating && form.slug"
          :href="'/product/' + form.slug"
          target="_blank"
          rel="noopener noreferrer"
          class="ml-3 text-sm font-normal text-blue-600 hover:underline"
        >Открыть в каталоге</a>
      </h1>
      <div class="flex gap-2">
        <Button variant="secondary" :disabled="!canSave" @click="handleSave">Сохранить</Button>
      </div>
    </div>

    <div class="flex items-center gap-4 p-4 rounded-md border bg-gray-50 dark:bg-gray-900 dark:border-gray-700">
      <div class="flex items-center gap-2">
        <label for="product-type" class="text-sm font-medium text-gray-700 dark:text-gray-300">
          Тип товара: *
        </label>
        <select
          id="product-type"
          v-model="form.type"
          class="h-9 px-3 py-1 text-sm rounded-md border border-gray-300 bg-white dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
        >
          <option value="simple">Простой товар</option>
          <option value="variable">Вариативный товар</option>
        </select>
      </div>
    </div>

    <!-- Предупреждение для вариативного товара без вариаций -->
    <div v-if="isVariableWithoutVariations" class="p-6 rounded-lg border-2 border-red-500 bg-red-50 dark:bg-red-900/20 dark:border-red-400">
      <div class="flex items-start gap-3">
        <div class="text-2xl">⚠️</div>
        <div class="flex-1">
          <h3 class="text-lg font-semibold text-red-800 dark:text-red-200 mb-2">
            Вариативный товар без вариаций
          </h3>
          <p class="text-red-700 dark:text-red-300 mb-3">
            У этого вариативного товара нет ни одной вариации. Он не будет отображаться для покупки на сайте.
          </p>
          <p class="text-sm text-red-600 dark:text-red-400">
            Перейдите на вкладку "Опции" и добавьте хотя бы одну вариацию товара.
          </p>
        </div>
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
        <ProductDescriptionForm :form="form" :errors="errors" :validate-field="validateField" :product-type="form.type" :is-variable-without-variations="isVariableWithoutVariations" />
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
                @toggle="toggleCategory"
              />
            </div>
          </div>
          <div class="lg:col-span-4">
            <div class="rounded-md border p-4 text-sm text-neutral-600 space-y-3 dark:border-neutral-800 dark:text-neutral-300">
              <div>
                Отмечайте чекбокс, чтобы привязать товар к категории. Изменения применяются после нажатия «Сохранить».
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-foreground/80">Основная категория</label>
                <select v-model="mainCategoryId" class="h-9 w-full rounded-md border bg-background px-2 text-sm dark:border-neutral-800" :disabled="selectedCategoryIds.size === 0">
                  <option :value="null">Не выбрано</option>
                  <option v-for="opt in selectedCategoryOptions" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
                </select>
              </div>
            </div>
          </div>
        </div>
      </TabsContent>

      <TabsContent value="attributes" class="pt-6">
        <ProductAttributeAssignments 
          v-if="activeTab === 'attributes'" 
          :key="String(id)" 
          :product-id="String(id)" 
          :is-creating="isCreating" 
          @toast="publishToast" 
        />
      </TabsContent>

      <TabsContent value="options" class="pt-6">
        <ProductOptionAssignments
          v-if="activeTab === 'options'"
          :option-assignments="form.optionAssignments"
          :option-values-map="optionValuesMap"
          :option-names-map="optionNamesMap"
          @remove-option="handleRemoveOption"
          @add-option="handleAddOption"
          @remove-assignment="handleRemoveAssignment"
        />
      </TabsContent>

      <TabsContent value="photos" class="pt-6">
        <ProductPhotos v-if="activeTab === 'photos'" :product-id="String(id)" :is-creating="isCreating" :initial-photos="initialPhotos" @toast="publishToast" />
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
import ProductAttributeAssignments from '@admin/components/forms/ProductAttributeAssignments.vue'
import ProductOptionAssignments from '@admin/components/forms/ProductOptionAssignments.vue'
import { useProductForm } from '@admin/composables/useProductForm'
import { useProductSave } from '@admin/composables/useProductSave'
import type { ProductTab, ProductFormModel } from '@admin/types/product'
import { ProductRepository, type ProductDto } from '@admin/repositories/ProductRepository'
import { ProductFormRepository } from '@admin/repositories/ProductFormRepository'
// (options UI moved to ProductOptions)
import { uiLoading } from '@admin/shared/uiLoading'
import { CategoryRepository, type CategoryDto } from '@admin/repositories/CategoryRepository'
import { ProductCategoryRepository } from '@admin/repositories/ProductCategoryRepository'
import { OptionRepository } from '@admin/repositories/OptionRepository'
import { toNum } from '@admin/utils/num'

const tabs = computed<ProductTab[]>(() => {
  const baseTabs: ProductTab[] = [
    { value: 'description', label: 'Описание товара' },
    { value: 'categories', label: 'Категории' },
    { value: 'attributes', label: 'Аттрибуты' },
    { value: 'photos', label: 'Фотографии' },
  ]

  // Добавляем вкладку опций только для вариативных товаров
  if (form?.type === 'variable') {
    baseTabs.splice(3, 0, { value: 'options', label: 'Опции' })
  }

  return baseTabs
})

const route = useRoute()
const router = useRouter()
const id = computed(() => route.params.id as string)
const isCreating = computed(() => !id.value || id.value === 'new')
type ProductTabValue = 'description' | 'categories' | 'attributes' | 'options' | 'photos'
const activeTab = ref<ProductTabValue>('description')
const tabParamKey = 'tab'
const validTabs = computed(() => new Set(tabs.value.map(t => t.value)))

// Проверка, является ли товар вариативным без валидных вариаций (опция+значение)
const isVariableWithoutVariations = computed(() => {
  if (form?.type !== 'variable') return false
  const rows = Array.isArray(form?.optionAssignments) ? (form.optionAssignments as any[]) : []
  const valid = rows.filter(r => r && typeof r.option === 'string' && r.option && typeof r.value === 'string' && r.value)
  return valid.length === 0
})

// Проверка, можно ли сохранить товар
const canSave = computed(() => {
  return !saving.value
})

const {
  form,
  errors,
  validateField,
  validateForm,
} = useProductForm()

const { saving, error: saveError, saveProduct } = useProductSave()

// Дополнительная защита от undefined
if (!form) {
  throw new Error('Form is not initialized properly')
}

// Load existing product for edit
const repo = new ProductRepository()
const formRepo = new ProductFormRepository()
const categoryRepo = new CategoryRepository()
const productCategoryRepo = new ProductCategoryRepository()
// Options bootstrap state (lightweight)
const optionsPrefetched = ref(false)
const optionRepo = new OptionRepository()
const prefetchedOptions = ref<any[]>([])
const initialPhotos = ref<Array<{ id: number; imageUrl: string; sortOrder: number }>>([])
async function loadOptionsBootstrap() {
  if (optionsPrefetched.value) return
  // Загружаем только список опций (легковесно). Значения — по требованию в дочернем компоненте.
  const data = await optionRepo.findAll({ itemsPerPage: 500, sort: { sortOrder: 'asc', name: 'asc' } }) as any
  prefetchedOptions.value = (data['hydra:member'] ?? data.member ?? []) as any[]
  optionsPrefetched.value = true
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
    type: (dto as any).type || 'simple',
    optionAssignments: (dto as any).optionAssignments || [],
  })
  // map optionAssignments if present
  ;(form as any).optionAssignments = Array.isArray((dto as any).optionAssignments)
    ? ((dto as any).optionAssignments as any[]).map((r: any) => ({
        option: typeof r.option === 'string' ? r.option : r.option?.['@id'] ?? (r.option?.id ? `/api/options/${r.option.id}` : ''),
        value: typeof r.value === 'string' ? r.value : r.value?.['@id'] ?? (r.value?.id ? `/api/option_values/${r.value.id}` : null),
        height: toNum(r.height),
        bulbsCount: toNum(r.bulbsCount),
        sku: r.sku ?? null,
        originalSku: r.originalSku ?? null,
        price: toNum(r.price),
        setPrice: r.setPrice ?? null,
        salePrice: toNum(r.salePrice),
        lightingArea: toNum(r.lightingArea),
        sortOrder: toNum(r.sortOrder),
        quantity: toNum(r.quantity),
        attributes: r.attributes ?? null,
      }))
    : []
}
const optionNamesMap = computed<Record<string, string>>(() => {
  try {
    const boot: any = (form as any)
    const list: Array<{ id: number; name: string }> = boot?.__bootOptionsList || []
    const map: Record<string, string> = {}
    for (const o of list) {
      const iri = `/api/options/${o.id}`
      map[iri] = o.name || iri
    }
    return map
  } catch { return {} }
})
const optionValuesMap = computed<Record<string, string>>(() => {
  try {
    const boot: any = (form as any)
    const byOpt: Record<string, Array<{ id: number; value: string }>> = boot?.__bootOptionValuesByOption || {}
    const map: Record<string, string> = {}
    for (const key of Object.keys(byOpt)) {
      for (const v of byOpt[key] || []) {
        const iri = `/api/option_values/${v.id}`
        map[iri] = v.value || iri
      }
    }
    return map
  } catch { return {} }
})
const loadBootstrap = async () => {
  const boot = await formRepo.fetchForm(isCreating.value ? undefined : id.value)
  hydrateForm(boot.product as any)
  try {
    if (isCreating.value) {
      // Новый товар: сбрасываем фото и очищаем отложенные изображения из прошлых сессий
      initialPhotos.value = []
      try { localStorage.removeItem('new-product-pending-images') } catch {}
    }
    if (boot?.categories?.tree) {
      categoryTree.value = Array.isArray(boot.categories.tree) ? boot.categories.tree : []
      categoriesLoaded.value = true
      const ids: number[] = Array.isArray(boot.categories.selectedCategoryIds) ? boot.categories.selectedCategoryIds.map((x: any) => Number(x)).filter((n: any) => Number.isFinite(n)) : []
      selectedCategoryIds.value = new Set(ids)
      mainCategoryId.value = (boot.categories.mainCategoryId != null && Number.isFinite(Number(boot.categories.mainCategoryId))) ? Number(boot.categories.mainCategoryId) : null
      categoriesInitialized.value = true
    }
    // Сохраним справочники опций для карт меток
    ;(form as any).__bootOptionsList = Array.isArray(boot?.options?.list) ? boot.options.list : []
    ;(form as any).__bootOptionValuesByOption = (boot?.options?.valuesByOption && typeof boot.options.valuesByOption === 'object') ? boot.options.valuesByOption : {}
    // Фото для инициализации
    initialPhotos.value = Array.isArray((boot as any).photos) ? (boot as any).photos : []
  } catch {}
}

onMounted(() => {
  // sync tab from URL on load
  const q = route.query?.[tabParamKey]
  if (typeof q === 'string' && validTabs.value.has(q)) {
    activeTab.value = q as ProductTabValue
  }
  loadBootstrap()
})
watch(id, async () => {
  // reset per-product lazy flags and state
  selectedCategoryIds.value = new Set()
  mainCategoryId.value = null
  categoriesInitialized.value = false
  if (isCreating.value) {
    // При переходе на новый товар очищаем ожидающие изображения
    try { localStorage.removeItem('new-product-pending-images') } catch {}
    initialPhotos.value = []
  }
  // keep categoryTree and categoriesLoaded; they'll refetch on demand if product differs
  optionsPrefetched.value = false
  await loadBootstrap()
  // если пользователь находится на вкладке, подтянем данные сразу
  if (activeTab.value === 'categories') {
    if (!categoriesLoaded.value) await loadCategoriesTree()
    if (!isCreating.value) await loadProductCategories()
  } else if (activeTab.value === 'attributes') {
    // handled inside ProductAttributeAssignments
  } else if (activeTab.value === 'options' && form?.type === 'variable') {
    // отключено: предзагрузка опций только по запросу в модалке
  }
})
// reflect activeTab to URL
watch(activeTab, (val) => {
  const query = { ...route.query, [tabParamKey]: val }
  router.replace({ query })
})
// react to external URL tab changes
watch(() => route.query[tabParamKey], (val) => {
  if (typeof val === 'string' && validTabs.value.has(val) && val !== activeTab.value) {
    activeTab.value = val as ProductTabValue
  }
})

// when available tabs change (e.g., after product type loads), re-apply URL tab if it becomes valid
watch(tabs, () => {
  const q = route.query?.[tabParamKey]
  if (typeof q === 'string' && validTabs.value.has(q) && q !== activeTab.value) {
    activeTab.value = q as ProductTabValue
  }
})

// watch product type changes to handle tab visibility
watch(() => form?.type, (newType, oldType) => {
  // Если переключаемся с вариативного на простой товар и активна вкладка options
  if (newType === 'simple' && activeTab.value === 'options') {
    activeTab.value = 'description' // Переключаемся на первую доступную вкладку
  }
})

// watch для управления статусом вариативных товаров без валидных вариаций
watch([() => form?.type, () => form?.optionAssignments], ([newType, newOptionAssignments]) => {
  if (newType === 'variable') {
    const rows = Array.isArray(newOptionAssignments) ? (newOptionAssignments as any[]) : []
    const valid = rows.filter(r => r && typeof r.option === 'string' && r.option && typeof r.value === 'string' && r.value)
    if (valid.length === 0) {
      form.status = false
    }
  }
}, { deep: true })

// Categories tree and selection
const selectedCategoryIds = ref<Set<number>>(new Set())
const mainCategoryId = ref<number | null>(null)
const categoryTree = ref<Array<{ id: number; label: string; children?: any[] }>>([])
const categoriesLoaded = ref(false)
const categoriesInitialized = ref(false)
const categoryLoading = ref(false)
const selectedCategoryOptions = computed(() => {
  const flat: Array<{ id: number; label: string }> = []
  const visit = (nodes: Array<{ id: number; label: string; children?: any[] }>) => {
    for (const n of nodes) {
      if (selectedCategoryIds.value.has(n.id)) flat.push({ id: n.id, label: n.label })
      if (n.children && n.children.length) visit(n.children)
    }
  }
  visit(categoryTree.value)
  return flat
})

async function loadCategoriesTree() {
  const payload = await categoryRepo.fetchTreeCached()
  categoryTree.value = Array.isArray((payload as any).tree) ? (payload as any).tree : []
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

// removed legacy bootstrap loader

// Ленивые загрузки по вкладкам
watch(activeTab, async (val) => {
  if (val === 'categories') {
    if (isCreating.value) {
      categoryLoading.value = true
      if (!categoriesLoaded.value) await loadCategoriesTree()
      categoryLoading.value = false
      categoriesInitialized.value = true
    }
  }
  // attributes handled inside ProductAttributeAssignments
  if (val === 'options' && form?.type === 'variable') {
    // bootstrap списка опций отключён по требованию
  }
}, { immediate: false })

async function toggleCategory(idNum: number, checked: boolean) {
  const next = new Set<number>(selectedCategoryIds.value)
  if (checked) next.add(idNum)
  else {
    next.delete(idNum)
    if (mainCategoryId.value === idNum) mainCategoryId.value = null
  }
  selectedCategoryIds.value = next
}

async function setMainCategory(idNum: number) {
  if (!selectedCategoryIds.value.has(idNum)) {
    const next = new Set<number>(selectedCategoryIds.value)
    next.add(idNum)
    selectedCategoryIds.value = next
  }
  mainCategoryId.value = idNum
}

async function saveProductCategories(productNumericId: number | string) {
  const productIri = `/api/products/${productNumericId}`
  const desiredIds = Array.from(new Set<number>(selectedCategoryIds.value))
  const desiredMainId = mainCategoryId.value
  await productCategoryRepo.syncForProduct(productIri, desiredIds, desiredMainId)
}

const handleSave = async () => {
  // Если вариативный товар без вариаций — предупредим и принудительно выключим товар, но дадим сохранить прочие поля
  if (isVariableWithoutVariations.value) {
    publishToast('Вариативный товар без вариаций: товар будет выключен')
    form.status = false
  }

  if (!validateForm()) {
    activeTab.value = 'description'
    return
  }

  const buildPayload = () => {
    const product = {
      name: form.name || '',
      slug: form.slug || '',
      price: toNum(form.price) || null,
      salePrice: toNum((form as any).salePrice) || null,
      status: Boolean(form.status),
      quantity: toNum(form.quantity) || null,
      sortOrder: toNum((form as any).sortOrder) || null,
      type: (form as any).type || 'simple',
      description: form.description || '',
      metaTitle: form.metaTitle || '',
      metaDescription: form.metaDescription || '',
      h1: form.h1 || '',
    }
    const payload: any = { product }
    if (categoriesInitialized.value) {
      payload.categories = {
        selectedCategoryIds: Array.from(selectedCategoryIds.value.values()),
        mainCategoryId: mainCategoryId.value,
      }
    }
    payload.optionAssignments = Array.isArray((form as any).optionAssignments) ? (form as any).optionAssignments : []
    return payload
  }

  // Новый товар: сначала create (как сейчас), затем sync для зависимостей
  if (isCreating.value) {
    const result = await saveProduct(id.value, form, {
      onValidationError: (violations) => {
        mapViolationsToErrors(violations as any)
        // если slug неуникален — открыть описание, чтобы подсветка была видна
        if (Array.isArray(violations) && violations.some(v => String(v?.propertyPath || '').split('.')[0] === 'slug')) {
          activeTab.value = 'description'
          publishToast('Slug уже используется. Укажите уникальное значение.')
        }
      },
    })
    if (!result.success) {
      // В случае ошибки (например, дубль slug) переключим на вкладку описания
      activeTab.value = 'description'
      // Явно подсветим slug, если сообщение про дубликат пришло текстом
      const msg = String(result.error || '')
      if (/Slug уже используется/i.test(msg)) {
        ;(errors as any).slug = 'Slug уже используется. Укажите уникальное значение.'
      }
      return
    }
    const newId = (result.result as any)?.id ?? id.value
    try {
      const boot = await formRepo.sync(newId, buildPayload())
      hydrateForm(boot.product as any)
      if (boot?.categories?.tree) {
        categoryTree.value = Array.isArray(boot.categories.tree) ? boot.categories.tree : []
        categoriesLoaded.value = true
        const ids: number[] = Array.isArray(boot.categories.selectedCategoryIds) ? boot.categories.selectedCategoryIds.map((x: any) => Number(x)).filter((n: any) => Number.isFinite(n)) : []
        selectedCategoryIds.value = new Set(ids)
        mainCategoryId.value = (boot.categories.mainCategoryId != null && Number.isFinite(Number(boot.categories.mainCategoryId))) ? Number(boot.categories.mainCategoryId) : null
        categoriesInitialized.value = true
      }
      if (Array.isArray((boot as any).photos)) initialPhotos.value = (boot as any).photos as any[]
    } catch {}
    publishToast('Товар сохранён')
    router.push({ name: 'admin-product-form', params: { id: newId }, query: { ...route.query } })
    return
  }

  // Редактирование: 1 sync POST
  try {
    const boot = await formRepo.sync(id.value, buildPayload())
    hydrateForm(boot.product as any)
    if (boot?.categories?.tree) {
      categoryTree.value = Array.isArray(boot.categories.tree) ? boot.categories.tree : []
      categoriesLoaded.value = true
      const ids: number[] = Array.isArray(boot.categories.selectedCategoryIds) ? boot.categories.selectedCategoryIds.map((x: any) => Number(x)).filter((n: any) => Number.isFinite(n)) : []
      selectedCategoryIds.value = new Set(ids)
      mainCategoryId.value = (boot.categories.mainCategoryId != null && Number.isFinite(Number(boot.categories.mainCategoryId))) ? Number(boot.categories.mainCategoryId) : null
      categoriesInitialized.value = true
    }
    if (Array.isArray((boot as any).photos)) initialPhotos.value = (boot as any).photos as any[]
    publishToast('Товар сохранён')
  } catch (e) {
    publishToast('Ошибка сохранения')
  }
}

// Imperative toast publisher per Reka UI docs (Duplicate toasts)
const toastCount = ref(0)
function publishToast(message: string) {
  lastToastMessage.value = message
  toastCount.value++
}
const lastToastMessage = ref('')

async function handleRemoveOption(optionIri: string) {
  // Обновляем локальную форму (UI моментально)
  if (Array.isArray(form.optionAssignments)) {
    form.optionAssignments = form.optionAssignments.filter(a => a.option !== optionIri)
  }
  publishToast('Опция удалена (сохранится при «Сохранить»)')
}

async function handleAddOption(payload: any) {
  // payload может быть как IRI опции, так и объект новой строки
  const row = typeof payload === 'string'
    ? {
        option: payload,
        value: null,
        height: null,
        bulbsCount: null,
        sku: null,
        originalSku: null,
        price: null,
        setPrice: false,
        salePrice: null,
        lightingArea: null,
        sortOrder: null,
        quantity: null,
        attributes: null,
      }
    : payload

  if (!Array.isArray(form.optionAssignments)) {
    ;(form as any).optionAssignments = []
  }
  if ((row as any).__editOf) {
    const target = (row as any).__editOf
    const idx = (form.optionAssignments as any[]).indexOf(target)
    if (idx >= 0) (form.optionAssignments as any[])[idx] = { ...target, ...row }
    delete (row as any).__editOf
  } else {
    ;(form.optionAssignments as any[]).push(row)
  }

  publishToast('Опция/значение добавлены (сохранится при «Сохранить»)')
}

async function handleRemoveAssignment(row: any) {
  if (!Array.isArray(form.optionAssignments)) return
  const idx = (form.optionAssignments as any[]).indexOf(row)
  if (idx >= 0) (form.optionAssignments as any[]).splice(idx, 1)
  publishToast('Вариация удалена (сохранится при «Сохранить»)')
}

function mapViolationsToErrors(list: Array<{ propertyPath?: string; message?: string }>) {
  if (!Array.isArray(list)) return
  for (const v of list) {
    const path = String(v?.propertyPath || '').split('.')[0] as keyof ProductFormModel
    if (!path) continue
    ;(errors as any)[path] = v?.message || 'Ошибка'
  }
}
</script>

<style scoped></style>


