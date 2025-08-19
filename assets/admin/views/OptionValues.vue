<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-xl font-semibold">Значения опций</h1>
      <button
        type="button"
        class="inline-flex h-9 items-center rounded-md bg-neutral-900 px-3 text-sm font-medium text-white shadow hover:bg-neutral-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:opacity-50 dark:bg_WHITE dark:text-neutral-900 dark:hover:bg-neutral-100"
        @click="openCreate = true"
      >
        Добавить значение
      </button>
    </div>

    <div v-if="state.error" class="mb-2 rounded-md border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-700">
      {{ state.error }}
    </div>

    <div class="space-y-6">
      <div v-for="group in grouped" :key="group.key" class="rounded-md border">
        <div class="border-b px-4 py-2 text-sm font-medium">
          {{ group.title }}
        </div>
        <table class="w-full text-sm">
          <thead class="bg-neutral-50 text-neutral-600 dark:bg-neutral-900/40 dark:text-neutral-300">
            <tr>
              <th class="px-4 py-2 text-left">Название</th>
              <th class="px-4 py-2 text-left w-72">Опция</th>
              <th class="px-4 py-2 text-left w-40">Сортировка</th>
              <th class="px-4 py-2 text-left w-28">Действия</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in group.items" :key="row.id" class="border-t">
              <td class="px-4 py-2">
                <Input v-model="row.valueProxy" placeholder="Название значения" @blur="() => saveRow(row)" />
              </td>
              <td class="px-4 py-2">
                <select
                  v-model="row.optionIriProxy"
                  class="h-9 w-full rounded-md border px-2 text-sm dark:border-neutral-800 dark:bg-neutral-900"
                  @change="() => saveRow(row)"
                >
                  <option :value="''">Без опции</option>
                  <option v-for="o in optionsSorted" :key="o.id" :value="o['@id']">{{ o.name || `Опция #${o.id}` }}<span v-if="(o as any).code"> ({{ (o as any).code }})</span></option>
                </select>
              </td>
              <td class="px-4 py-2">
                <Input v-model="row.sortOrderProxy" type="number" placeholder="0" @blur="() => saveRow(row)" />
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
            <tr v-if="!loading && group.items.length === 0">
              <td colspan="4" class="px-4 py-6 text-center text-neutral-500">Нет значений</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Modal create -->
    <DialogRoot v-model:open="openCreate">
      <DialogPortal>
        <DialogOverlay class="fixed inset-0 bg-black/50 backdrop-blur-[1px]" />
        <DialogContent
          class="fixed left-1/2 top-1/2 w-[92vw] max-w-md -translate-x-1/2 -translate-y-1/2 rounded-md border bg-white p-4 shadow-lg focus:outline-none dark:border-neutral-800 dark:bg-neutral-900"
        >
          <div class="mb-2">
            <DialogTitle class="text-base font-semibold text-neutral-900 dark:text-neutral-100">Новое значение</DialogTitle>
            <DialogDescription class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">Заполните поля ниже</DialogDescription>
          </div>

          <form class="space-y-4" @submit.prevent="createSubmit">
            <Input v-model="createForm.value" label="Название" placeholder="Например: Красный" />
            <label class="block text-sm">
              <span class="mb-1 block text-neutral-600 dark:text-neutral-300">Опция</span>
              <select v-model="createForm.optionIri" class="h-9 w-full rounded-md border px-2 text-sm dark:border-neutral-800 dark:bg-neutral-900">
                <option :value="''">Без опции</option>
                <option v-for="o in optionsSorted" :key="o.id" :value="o['@id']">{{ o.name || `Опция #${o.id}` }}</option>
              </select>
            </label>
            <Input v-model="createForm.sortOrderStr" label="Сортировка" type="number" placeholder="0" />

            <div class="flex justify-end gap-2 pt-2">
              <button type="button" class="h-9 rounded-md px-3 text-sm hover:bg-neutral-100 dark:hover:bg-white/10" @click="openCreate = false">Отмена</button>
              <button
                type="submit"
                :disabled="submitting || !createForm.value.trim()"
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
    <ConfirmDialog v-model="deleteOpen" :title="'Удалить значение?'" :description="'Это действие необратимо'" confirm-text="Удалить" :danger="true" @confirm="performDelete" />
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { DialogClose, DialogContent, DialogDescription, DialogOverlay, DialogPortal, DialogRoot, DialogTitle, ToastDescription, ToastRoot } from 'reka-ui'
import Input from '@admin/ui/components/Input.vue'
import ConfirmDialog from '@admin/components/ConfirmDialog.vue'
import { useCrud } from '@admin/composables/useCrud'
import { OptionRepository, type Option } from '@admin/repositories/OptionRepository'
import { OptionValueRepository, type OptionValue } from '@admin/repositories/OptionValueRepository'

type EditableRow = {
  id: number
  valueProxy: string
  optionIriProxy: string
  sortOrderProxy: string
}

const repo = new OptionValueRepository()
const optionRepo = new OptionRepository()
const crud = useCrud<OptionValue>(repo)
const state = crud.state
const loading = computed(() => !!state.loading)

const rows = ref<EditableRow[]>([])
const options = ref<Option[]>([] as any)
const optionsSorted = computed(() => {
  const list = (options.value || ([] as any)).slice()
  list.sort((a: any, b: any) => {
    const ao = a.sortOrder
    const bo = b.sortOrder
    if (ao != null && bo != null) return ao - bo
    if (ao != null) return -1
    if (bo != null) return 1
    return String(a.name || '').localeCompare(String(b.name || ''))
  })
  return list
})

type Grouped = { key: string; title: string; items: EditableRow[] }
const grouped = computed<Grouped[]>(() => {
  const map = new Map<string, Grouped>()
  const titleOf = (iri: string): string => {
    if (!iri) return 'Без опции'
    const found = optionsSorted.value.find(o => (o as any)['@id'] === iri)
    return found?.name ? String(found.name) : `Опция ${iri.split('/').pop()}`
  }
  for (const r of rows.value) {
    const key = r.optionIriProxy || '__no_option__'
    if (!map.has(key)) map.set(key, { key, title: titleOf(r.optionIriProxy), items: [] })
    map.get(key)!.items.push(r)
  }
  // order groups: option sortOrder asc, then name asc; empty group last
  const arr = Array.from(map.values())
  arr.sort((a, b) => {
    const aOpt = optionsSorted.value.find(o => (o as any)['@id'] === a.key)
    const bOpt = optionsSorted.value.find(o => (o as any)['@id'] === b.key)
    const aEmpty = a.key === '__no_option__'
    const bEmpty = b.key === '__no_option__'
    if (aEmpty && !bEmpty) return 1
    if (!aEmpty && bEmpty) return -1
    const ao = (aOpt as any)?.sortOrder ?? null
    const bo = (bOpt as any)?.sortOrder ?? null
    if (ao != null && bo != null) return ao - bo
    if (ao != null) return -1
    if (bo != null) return 1
    return String((aOpt as any)?.name || '').localeCompare(String((bOpt as any)?.name || ''))
  })
  return arr
})

onMounted(async () => {
  const [_, opts] = await Promise.all([
    crud.fetchAll({ itemsPerPage: 500, sort: { sortOrder: 'asc', value: 'asc' } }),
    optionRepo.findAll({ itemsPerPage: 500, sort: { sortOrder: 'asc', name: 'asc' } }) as any,
  ])
  const optionsCollection = (opts as any)?.['hydra:member'] ?? (opts as any)?.member ?? []
  options.value = optionsCollection as any
  syncRows()
})

watch(
  () => state.items,
  () => syncRows(),
  { deep: true }
)

function syncRows() {
  const items = ((state.items ?? []) as OptionValue[]).slice()
  items.sort((a, b) => {
    const ao = (a.sortOrder ?? null)
    const bo = (b.sortOrder ?? null)
    if (ao != null && bo != null) return ao - bo
    if (ao != null) return -1
    if (bo != null) return 1
    return String(a.value || '').localeCompare(String(b.value || ''))
  })
  rows.value = items.map((a) => ({
    id: Number(a.id),
    valueProxy: String((a as any).value ?? ''),
    optionIriProxy: normalizeOptionIri((a as any).optionType),
    sortOrderProxy: (a as any).sortOrder == null ? '' : String((a as any).sortOrder),
  }))
}

async function saveRow(row: EditableRow) {
  const payload: Partial<OptionValue> = {
    value: row.valueProxy.trim() || null,
    sortOrder: row.sortOrderProxy === '' ? 0 : Number(row.sortOrderProxy),
    optionType: row.optionIriProxy || null,
  }
  if (row.optionIriProxy) {
    const opt = optionsSorted.value.find(o => (o as any)['@id'] === row.optionIriProxy)
    if (opt && (opt as any).code) {
      ;(payload as any).code = String((opt as any).code)
    }
  }
  await crud.update(row.id, payload)
  publishToast('Сохранено')
}

// Create form
const openCreate = ref(false)
const submitting = ref(false)
const createForm = reactive({ value: '', optionIri: '', sortOrderStr: '' })

async function createSubmit() {
  if (!createForm.value.trim()) return
  submitting.value = true
  try {
    await crud.create({
      value: createForm.value.trim(),
      optionType: createForm.optionIri || null,
      sortOrder: createForm.sortOrderStr === '' ? 0 : Number(createForm.sortOrderStr),
      ...(function() {
        if (createForm.optionIri) {
          const opt = optionsSorted.value.find(o => (o as any)['@id'] === createForm.optionIri)
          if (opt && (opt as any).code) return { code: String((opt as any).code) } as any
        }
        return {}
      })(),
    } as Partial<OptionValue>)
    syncRows()
    openCreate.value = false
    Object.assign(createForm, { value: '', optionIri: '', sortOrderStr: '' })
    publishToast('Значение добавлено')
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

function normalizeOptionIri(value: any): string {
  if (!value) return ''
  if (typeof value === 'string') return value
  if (typeof value === 'object' && value['@id']) return String(value['@id'])
  if (typeof value === 'object' && value.id != null) return `/api/options/${value.id}`
  return ''
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
  publishToast('Значение удалено')
  pendingDeleteId.value = null
  deleteOpen.value = false
}
</script>


