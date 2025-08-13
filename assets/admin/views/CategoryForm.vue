<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-xl font-semibold">
        {{ isCreating ? 'Новая категория' : `Категория #${id}` }}
      </h1>
      <div class="flex gap-2">
        <Button variant="secondary" :disabled="saving" @click="handleSave">Сохранить</Button>
      </div>
    </div>

    <div v-if="saveError" class="p-3 rounded border border-red-200 bg-red-50 text-sm text-red-800">
      {{ saveError }}
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
      <div class="lg:col-span-8 space-y-4">
        <div class="rounded-md border p-4 dark:border-neutral-800">
          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <label class="text-sm">
              <span class="mb-1 block text-neutral-600 dark:text-neutral-300">Название</span>
              <input v-model="form.name" type="text" class="h-9 w-full rounded-md border px-2 text-sm dark:border-neutral-800 dark:bg-neutral-900" />
            </label>
            <label class="text-sm">
              <span class="mb-1 block text-neutral-600 dark:text-neutral-300">Slug</span>
              <input v-model="form.slug" type="text" class="h-9 w-full rounded-md border px-2 text-sm dark:border-neutral-800 dark:bg-neutral-900" />
            </label>
            <label class="text-sm sm:col-span-2">
              <span class="mb-1 block text-neutral-600 dark:text-neutral-300">Описание</span>
              <textarea v-model="form.description" rows="4" class="w-full rounded-md border p-2 text-sm dark:border-neutral-800 dark:bg-neutral-900" />
            </label>
          </div>
        </div>

        <div class="rounded-md border p-4 dark:border-neutral-800">
          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <label class="text-sm">
              <span class="mb-1 block text-neutral-600 dark:text-neutral-300">Meta Title</span>
              <input v-model="form.metaTitle" type="text" class="h-9 w-full rounded-md border px-2 text-sm dark:border-neutral-800 dark:bg-neutral-900" />
            </label>
            <label class="text-sm">
              <span class="mb-1 block text-neutral-600 dark:text-neutral-300">H1</span>
              <input v-model="form.metaH1" type="text" class="h-9 w-full rounded-md border px-2 text-sm dark:border-neutral-800 dark:bg-neutral-900" />
            </label>
            <label class="text-sm sm:col-span-2">
              <span class="mb-1 block text-neutral-600 dark:text-neutral-300">Meta Description</span>
              <textarea v-model="form.metaDescription" rows="3" class="w-full rounded-md border p-2 text-sm dark:border-neutral-800 dark:bg-neutral-900" />
            </label>
            <label class="text-sm sm:col-span-2">
              <span class="mb-1 block text-neutral-600 dark:text-neutral-300">Meta Keywords</span>
              <input v-model="form.metaKeywords" type="text" class="h-9 w-full rounded-md border px-2 text-sm dark:border-neutral-800 dark:bg-neutral-900" />
            </label>
          </div>
        </div>
      </div>

      <div class="lg:col-span-4 space-y-4">
        <div class="rounded-md border p-4 dark:border-neutral-800">
          <div class="flex items-center justify-between">
            <span class="text-sm">Видимость</span>
            <input v-model="form.visibility" type="checkbox" class="h-4 w-4" />
          </div>
        </div>
        <div class="rounded-md border p-4 dark:border-neutral-800">
          <div class="grid grid-cols-1 gap-4">
            <label class="text-sm">
              <span class="mb-1 block text-neutral-600 dark:text-neutral-300">Родительская категория</span>
              <SelectRoot v-model="parentValue">
                <SelectTrigger class="inline-flex h-9 w-full items-center justify-between gap-2 rounded-md border px-2 text-left text-sm dark:border-neutral-800 dark:bg-neutral-900">
                  <SelectValue placeholder="— Без родителя —" />
                  <SelectIcon />
                </SelectTrigger>
                <SelectPortal>
                  <SelectContent class="min-w-[var(--reka-select-trigger-width)] rounded-md border bg-white p-1 shadow-md dark:border-neutral-800 dark:bg-neutral-900">
                    <SelectViewport class="max-h-64">
                      <SelectItem value="none" class="cursor-pointer rounded px-2 py-1.5 text-sm data-[highlighted]:bg-neutral-100 dark:data-[highlighted]:bg-white/10">
                        <SelectItemText>— Без родителя —</SelectItemText>
                        <SelectItemIndicator />
                      </SelectItem>
                      <template v-for="opt in parentOptions" :key="opt.id">
                        <SelectItem :value="String(opt.id)" class="cursor-pointer rounded px-2 py-1.5 text-sm data-[highlighted]:bg-neutral-100 dark:data-[highlighted]:bg-white/10">
                          <SelectItemText>{{ opt.label }}</SelectItemText>
                          <SelectItemIndicator />
                        </SelectItem>
                      </template>
                    </SelectViewport>
                  </SelectContent>
                </SelectPortal>
              </SelectRoot>
            </label>
            <label class="text-sm">
              <span class="mb-1 block text-neutral-600 dark:text-neutral-300">Сортировка</span>
              <input v-model.number="form.sortOrder" type="number" min="0" class="h-9 w-full rounded-md border px-2 text-sm dark:border-neutral-800 dark:bg-neutral-900" />
            </label>
            <div class="flex items-center justify-between">
              <span class="text-sm">В шапке</span>
              <input v-model="form.navbarVisibility" type="checkbox" class="h-4 w-4" />
            </div>
            <div class="flex items-center justify-between">
              <span class="text-sm">В подвале</span>
              <input v-model="form.footerVisibility" type="checkbox" class="h-4 w-4" />
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Toasts -->
    <ToastRoot v-for="n in toastCount" :key="n" type="foreground" :duration="3000">
      <ToastDescription>{{ lastToastMessage }}</ToastDescription>
    </ToastRoot>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Button from '@admin/ui/components/Button.vue'
import { ToastDescription, ToastRoot, SelectContent, SelectIcon, SelectItem, SelectItemIndicator, SelectItemText, SelectPortal, SelectRoot, SelectTrigger, SelectValue, SelectViewport } from 'reka-ui'
import { useCrud } from '@admin/composables/useCrud'
import { CategoryRepository, type CategoryDto } from '@admin/repositories/CategoryRepository'
import { translit } from '@admin/utils/translit'

const route = useRoute()
const router = useRouter()
const id = computed(() => route.params.id as string)
const isCreating = computed(() => !id.value || id.value === 'new')
const currentId = computed<number | null>(() => {
  if (isCreating.value) return null
  const n = Number(id.value)
  return Number.isFinite(n) ? n : null
})

type CategoryForm = {
  name: string
  slug: string
  description: string
  metaTitle: string
  metaDescription: string
  metaKeywords: string
  metaH1: string
  visibility: boolean
  parentCategoryId: number | null
  sortOrder: number | null
  navbarVisibility: boolean
  footerVisibility: boolean
}

const form = reactive<CategoryForm>({
  name: '',
  slug: '',
  description: '',
  metaTitle: '',
  metaDescription: '',
  metaKeywords: '',
  metaH1: '',
  visibility: true,
  parentCategoryId: null,
  sortOrder: 0,
  navbarVisibility: true,
  footerVisibility: true,
})

// Автогенерация slug из name
const shouldAutoGenerateSlug = vueRef(true)
watch(
  () => form.name,
  (newName) => {
    if (!shouldAutoGenerateSlug.value) return
    form.slug = translit(String(newName || ''))
  }
)
watch(
  () => form.slug,
  (newSlug) => {
    // как только пользователь начинает менять slug вручную — перестаём автогенерировать
    if (newSlug && newSlug !== translit(String(form.name || ''))) {
      shouldAutoGenerateSlug.value = false
    }
  }
)

const repo = new CategoryRepository()
const crud = useCrud<CategoryDto>(repo)
const state = crud.state
const availableParents = computed(() => ((state.items ?? []) as CategoryDto[]).filter(c => c.id !== currentId.value))
type ParentOption = { id: number; label: string }
const parentOptions = computed<ParentOption[]>(() => flattenTreeWithIndent(buildTree(availableParents.value)))

// Reka Select v-model proxy: string ids | 'none'
const parentValue = vueRef<string>('none')
watch(
  () => form.parentCategoryId,
  (val) => {
    parentValue.value = val == null ? 'none' : String(val)
  },
  { immediate: true }
)
watch(parentValue, (val) => {
  form.parentCategoryId = val === 'none' ? null : Number(val)
})

const hydrateForm = (dto: CategoryDto) => {
  form.name = dto.name ?? ''
  form.slug = dto.slug ?? ''
  form.description = dto.description ?? ''
  form.metaTitle = dto.metaTitle ?? ''
  form.metaDescription = dto.metaDescription ?? ''
  form.metaKeywords = dto.metaKeywords ?? ''
  form.metaH1 = (dto as any).metaH1 ?? ''
  form.visibility = !!(dto.visibility ?? true)
  form.parentCategoryId = (dto.parentCategoryId ?? null) as any
  form.sortOrder = (dto.sortOrder ?? 0) as any
  form.navbarVisibility = !!(dto.navbarVisibility ?? true)
  form.footerVisibility = !!(dto.footerVisibility ?? true)
  // не позволяем выбрать саму себя в качестве родителя
  if (dto.id && form.parentCategoryId === dto.id) {
    form.parentCategoryId = null
  }
  // если у существующей категории уже есть slug — отключим автогенерацию
  shouldAutoGenerateSlug.value = !(form.slug && form.slug.trim().length > 0)
}

onMounted(async () => {
  // подгружаем все категории для выпадающего списка
  await crud.fetchAll({ itemsPerPage: 1000 })
  // если режим редактирования — загрузим текущую
  if (!isCreating.value) {
    const dto = await repo.findById(id.value)
    hydrateForm(dto)
    parentValue.value = dto.parentCategoryId == null ? 'none' : String(dto.parentCategoryId)
  }
})

type TreeNode = { id: number; name: string | null; children?: TreeNode[] }
function buildTree(items: CategoryDto[]): TreeNode[] {
  const byId = new Map<number, TreeNode>()
  const roots: TreeNode[] = []
  for (const i of items) {
    if (!i.id && i.id !== 0) continue
    byId.set(Number(i.id), { id: Number(i.id), name: i.name ?? null, children: [] })
  }
  for (const i of items) {
    const id = Number(i.id)
    const pid = (i as any).parentCategoryId ?? null
    const node = byId.get(id)!
    if (pid && byId.has(pid)) byId.get(pid)!.children!.push(node)
    else roots.push(node)
  }
  return roots
}
function flattenTreeWithIndent(nodes: TreeNode[], depth = 0): ParentOption[] {
  const out: ParentOption[] = []
  const pad = (d: number) => (d > 0 ? '— '.repeat(d) : '')
  const byName = (a: TreeNode, b: TreeNode) => String(a.name || '').localeCompare(String(b.name || ''))
  const dfs = (ns: TreeNode[], d: number) => {
    ns.sort(byName)
    for (const n of ns) {
      out.push({ id: n.id, label: `${pad(d)}${n.name || `Без названия (#${n.id})`}` })
      if (n.children && n.children.length) dfs(n.children, d + 1)
    }
  }
  dfs(nodes, depth)
  return out
}

const saving = computed(() => !!state.loading)
const saveError = computed(() => state.error)

async function handleSave() {
  const payload: Partial<CategoryDto> = {
    name: form.name,
    slug: form.slug,
    description: form.description,
    metaTitle: form.metaTitle,
    metaDescription: form.metaDescription,
    metaKeywords: form.metaKeywords,
    metaH1: form.metaH1,
    visibility: form.visibility,
    parentCategoryId: form.parentCategoryId,
    sortOrder: form.sortOrder,
    navbarVisibility: form.navbarVisibility,
    footerVisibility: form.footerVisibility,
  }
  try {
    if (isCreating.value) {
      const created = await repo.create(payload)
      publishToast('Категория создана')
      router.push({ name: 'admin-category-form', params: { id: (created as any).id } })
    } else {
      await repo.partialUpdate(id.value, payload)
      publishToast('Категория сохранена')
    }
  } catch (e) {
    // error already normalized by http client / useCrud state
  }
}

// Toasts
import { ref as vueRef, reactive } from 'vue'
const toastCount = vueRef(0)
const lastToastMessage = vueRef('')
function publishToast(message: string) {
  lastToastMessage.value = message
  toastCount.value++
}
</script>

<style scoped></style>


