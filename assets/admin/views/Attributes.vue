<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-xl font-semibold">Атрибуты</h1>
      <button
        type="button"
        class="inline-flex h-9 items-center rounded-md bg-neutral-900 px-3 text-sm font-medium text-white shadow hover:bg-neutral-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:opacity-50 dark:bg-white dark:text-neutral-900 dark:hover:bg-neutral-100"
        @click="openCreate = true"
      >
        Добавить атрибут
      </button>
    </div>

    <div v-if="state.error" class="mb-2 rounded-md border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-700">
      {{ state.error }}
    </div>

    <div class="space-y-6">
      <div v-for="group in grouped" :key="group.key" class="rounded-md border">
        <div class="border-b px-4 py-2 text-sm font-medium text-neutral-700 dark:text-neutral-300">
          {{ group.title }}
        </div>
        <table class="w-full text-sm">
          <thead class="bg-neutral-50 text-neutral-600 dark:bg-neutral-900/40 dark:text-neutral-300">
            <tr>
              <th class="px-4 py-2 text-left">Название</th>
              <th class="px-4 py-2 text-left w-72">Группа</th>
              <th class="px-4 py-2 text-left w-40">Сортировка</th>
              <th class="px-4 py-2 text-left w-28">Действия</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in group.rows" :key="row.id" class="border-t">
              <td class="px-4 py-2">
                <Input v-model="row.nameProxy" placeholder="Название атрибута" @blur="() => saveRow(row)" />
              </td>
              <td class="px-4 py-2">
                <select
                  v-model="row.groupProxy"
                  class="h-9 w-full rounded-md border px-2 text-sm dark:border-neutral-800 dark:bg-neutral-900"
                  @change="() => saveRow(row)"
                >
                  <option :value="''">Без группы</option>
                  <option v-for="g in groupsSorted" :key="g.id" :value="g['@id']">{{ g.name || `Группа #${g.id}` }}</option>
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
            <tr v-if="!loading && group.rows.length === 0">
              <td colspan="3" class="px-4 py-6 text-center text-neutral-500">Нет атрибутов</td>
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
            <DialogTitle class="text-base font-semibold text-neutral-900 dark:text-neutral-100">Новый атрибут</DialogTitle>
            <DialogDescription class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">Заполните поля ниже</DialogDescription>
          </div>

          <form class="space-y-4" @submit.prevent="createSubmit">
            <Input v-model="createForm.name" label="Название" placeholder="Например: Цвет" />
            <label class="block text-sm">
              <span class="mb-1 block text-neutral-600 dark:text-neutral-300">Группа</span>
              <select v-model="createForm.groupIri" class="h-9 w-full rounded-md border px-2 text-sm dark:border-neutral-800 dark:bg-neutral-900">
                <option :value="''">Без группы</option>
                <option v-for="g in groupsSorted" :key="g.id" :value="g['@id']">{{ g.name || `Группа #${g.id}` }}</option>
              </select>
            </label>
            <Input v-model="createForm.sortOrderStr" label="Сортировка" type="number" placeholder="0" />

            <div class="flex justify-end gap-2 pt-2">
              <button type="button" class="h-9 rounded-md px-3 text-sm hover:bg-neutral-100 dark:hover:bg-white/10" @click="openCreate = false">Отмена</button>
              <button
                type="submit"
                :disabled="submitting || !createForm.name.trim()"
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
    <ConfirmDialog v-model="deleteOpen" :title="'Удалить атрибут?'" :description="'Это действие необратимо'" confirm-text="Удалить" :danger="true" @confirm="performDelete" />
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, reactive, watch } from 'vue'
import { DialogClose, DialogContent, DialogDescription, DialogOverlay, DialogPortal, DialogRoot, DialogTitle, ToastDescription, ToastRoot } from 'reka-ui'
import Input from '@admin/ui/components/Input.vue'
import { useCrud } from '@admin/composables/useCrud'
import { AttributeRepository, type Attribute } from '@admin/repositories/AttributeRepository'
import { AttributeGroupRepository, type AttributeGroupDto } from '@admin/repositories/AttributeGroupRepository'
import ConfirmDialog from '@admin/components/ConfirmDialog.vue'

type EditableRow = {
  id: number
  nameProxy: string
  groupProxy: string
  sortOrderProxy: string
}

const attrRepo = new AttributeRepository()
const groupRepo = new AttributeGroupRepository()
const crud = useCrud<Attribute>(attrRepo)
const state = crud.state
const loading = computed(() => !!state.loading)

const groups = ref<AttributeGroupDto[]>([])
const groupsSorted = computed(() => {
  const list = groups.value.slice()
  list.sort((a, b) => {
    const ao = a.sortOrder
    const bo = b.sortOrder
    if (ao != null && bo != null) return ao - bo
    if (ao != null) return -1
    if (bo != null) return 1
    return String(a.name || '').localeCompare(String(b.name || ''))
  })
  return list
})

const rows = ref<EditableRow[]>([])

onMounted(async () => {
  const [_, grps] = await Promise.all([
    crud.fetchAll({ itemsPerPage: 500, sort: { sortOrder: 'asc', name: 'asc' } }),
    groupRepo.findAll({ itemsPerPage: 500, sort: { sortOrder: 'asc', name: 'asc' } }) as any,
  ])
  const groupsCollection = (grps as any)?.['hydra:member'] ?? (grps as any)?.member ?? []
  groups.value = groupsCollection as AttributeGroupDto[]
  syncRows()
})

watch(
  () => state.items,
  () => syncRows(),
  { deep: true }
)

function groupKeyForRow(row: EditableRow): string {
  return row.groupProxy || '__no_group__'
}

const grouped = computed(() => {
  const map = new Map<string, EditableRow[]>()
  for (const r of rows.value) {
    const k = groupKeyForRow(r)
    const list = map.get(k)
    if (list) list.push(r)
    else map.set(k, [r])
  }
  const toTitle = (key: string): string => {
    if (key === '__no_group__') return 'Без группы'
    const g = groupsSorted.value.find((x) => x['@id'] === key)
    return g?.name || `Группа ${g?.id ?? ''}`
  }
  // сортировка групп как в groupsSorted
  const orderedKeys = [
    '__no_group__',
    ...groupsSorted.value.map((g) => String(g['@id'])),
  ]
  return orderedKeys
    .filter((k) => map.has(k))
    .map((k) => ({ key: k, title: toTitle(k), rows: (map.get(k) || []).slice().sort((a, b) => {
      const ao = a.sortOrderProxy === '' ? null : Number(a.sortOrderProxy)
      const bo = b.sortOrderProxy === '' ? null : Number(b.sortOrderProxy)
      if (ao != null && bo != null) return ao - bo
      if (ao != null) return -1
      if (bo != null) return 1
      return String(a.nameProxy || '').localeCompare(String(b.nameProxy || ''))
    }) }))
})

function syncRows() {
  const items = ((state.items ?? []) as Attribute[]).slice()
  items.sort((a, b) => {
    const ao = (a.sortOrder ?? null)
    const bo = (b.sortOrder ?? null)
    if (ao != null && bo != null) return ao - bo
    if (ao != null) return -1
    if (bo != null) return 1
    return String(a.name || '').localeCompare(String(b.name || ''))
  })
  rows.value = items.map((a) => ({
    id: Number(a.id),
    nameProxy: String(a.name ?? ''),
    groupProxy: normalizeGroupIri((a as any).attributeGroup),
    sortOrderProxy: (a as any).sortOrder == null ? '' : String((a as any).sortOrder),
  }))
}

async function saveRow(row: EditableRow) {
  const payload: Partial<Attribute> = {
    name: row.nameProxy.trim() || null,
    sortOrder: row.sortOrderProxy === '' ? null : Number(row.sortOrderProxy),
    attributeGroup: row.groupProxy || null,
  }
  await crud.update(row.id, payload)
  publishToast('Сохранено')
}

// Create form
const openCreate = ref(false)
const submitting = ref(false)
const createForm = reactive({ name: '', groupIri: '', sortOrderStr: '' })

async function createSubmit() {
  if (!createForm.name.trim()) return
  submitting.value = true
  try {
    const created = await crud.create({
      name: createForm.name.trim(),
      attributeGroup: createForm.groupIri || null,
      sortOrder: createForm.sortOrderStr === '' ? null : Number(createForm.sortOrderStr),
    } as Partial<Attribute>)
    // элемент уже добавлен в state.items внутри useCrud.create; синхронизируем строки
    syncRows()
    openCreate.value = false
    Object.assign(createForm, { name: '', groupIri: '', sortOrderStr: '' })
    publishToast('Атрибут добавлен')
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

function normalizeGroupIri(value: any): string {
  if (!value) return ''
  if (typeof value === 'string') return value
  if (typeof value === 'object' && value['@id']) return String(value['@id'])
  if (typeof value === 'object' && value.id != null) return `/api/attribute_groups/${value.id}`
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
  publishToast('Атрибут удалён')
  pendingDeleteId.value = null
  deleteOpen.value = false
}
</script>


