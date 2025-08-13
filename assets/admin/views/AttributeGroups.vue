<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-xl font-semibold">Группы атрибутов</h1>
      <button
        type="button"
        class="inline-flex h-9 items-center rounded-md bg-neutral-900 px-3 text-sm font-medium text-white shadow hover:bg-neutral-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:opacity-50 dark:bg-white dark:text-neutral-900 dark:hover:bg-neutral-100"
        @click="openCreate = true"
      >
        Добавить группу
      </button>
    </div>

    <div>
      <div v-if="state.error" class="mb-2 rounded-md border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-700">
        {{ state.error }}
      </div>
      <div class="overflow-hidden rounded-md border">
        <table class="w-full text-sm">
          <thead class="bg-neutral-50 text-neutral-600 dark:bg-neutral-900/40 dark:text-neutral-300">
            <tr>
              <th class="px-4 py-2 text-left">Название</th>
              <th class="px-4 py-2 text-left w-40">Сортировка</th>
              <th class="px-4 py-2 text-left w-28">Действия</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in rows" :key="row.id" class="border-t">
              <td class="px-4 py-2">
                <Input
                  v-model="row.nameProxy"
                  placeholder="Название группы"
                  @blur="() => saveRow(row)"
                />
              </td>
              <td class="px-4 py-2">
                <Input
                  v-model="row.sortOrderProxy"
                  type="number"
                  placeholder="0"
                  @blur="() => saveRow(row)"
                />
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
            <tr v-if="!loading && rows.length === 0">
              <td colspan="2" class="px-4 py-8 text-center text-neutral-500">Пока нет групп</td>
            </tr>
            <tr v-if="loading">
              <td colspan="2" class="px-4 py-8 text-center text-neutral-500">Загрузка…</td>
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
            <DialogTitle class="text-base font-semibold text-neutral-900 dark:text-neutral-100">
              Новая группа атрибутов
            </DialogTitle>
            <DialogDescription class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
              Заполните поля и сохраните
            </DialogDescription>
          </div>

          <form class="space-y-4" @submit.prevent="createSubmit">
            <Input v-model="createForm.name" label="Название" placeholder="Например: Электрика" />
            <Input v-model="createForm.sortOrderStr" label="Сортировка" type="number" placeholder="1" />

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

    <!-- toasts -->
    <ToastRoot v-for="n in toastCount" :key="n" type="foreground" :duration="2200">
      <ToastDescription>{{ lastToastMessage }}</ToastDescription>
    </ToastRoot>
    <ConfirmDialog v-model="deleteOpen" :title="'Удалить группу?'" :description="'Это действие необратимо'" confirm-text="Удалить" :danger="true" @confirm="performDelete" />
  </div>
  
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { DialogClose, DialogContent, DialogDescription, DialogOverlay, DialogPortal, DialogRoot, DialogTitle, ToastDescription, ToastRoot } from 'reka-ui'
import Input from '@admin/ui/components/Input.vue'
import { useCrud } from '@admin/composables/useCrud'
import { AttributeGroupRepository, type AttributeGroupDto } from '@admin/repositories/AttributeGroupRepository'
import ConfirmDialog from '@admin/components/ConfirmDialog.vue'

type EditableRow = {
  id: number
  nameProxy: string
  sortOrderProxy: string
}

const repo = new AttributeGroupRepository()
const crud = useCrud<AttributeGroupDto>(repo)
const state = crud.state
const loading = computed(() => !!state.loading)

const rows = ref<EditableRow[]>([])

onMounted(async () => {
  await crud.fetchAll({ itemsPerPage: 200, sort: { sortOrder: 'asc', name: 'asc' } })
  syncRows()
})

watch(
  () => state.items,
  () => syncRows(),
  { deep: true }
)

function compareGroups(a: AttributeGroupDto, b: AttributeGroupDto): number {
  const ao = a.sortOrder
  const bo = b.sortOrder
  if (ao != null && bo != null) return ao - bo
  if (ao != null) return -1
  if (bo != null) return 1
  return String(a.name || '').localeCompare(String(b.name || ''))
}

function syncRows() {
  const items = ((state.items ?? []) as AttributeGroupDto[]).slice().sort(compareGroups)
  rows.value = items.map((g) => ({
    id: Number(g.id),
    nameProxy: String(g.name ?? ''),
    sortOrderProxy: g.sortOrder == null ? '' : String(g.sortOrder),
  }))
}

async function saveRow(row: EditableRow) {
  const payload: Partial<AttributeGroupDto> = {
    name: row.nameProxy.trim() || null,
    sortOrder: row.sortOrderProxy === '' ? null : Number(row.sortOrderProxy),
  }
  try {
    await crud.update(row.id, payload)
    publishToast('Сохранено')
  } finally {
    // обновим локальное состояние из стора, чтобы не расползлось
    syncRows()
  }
}

// create form
const openCreate = ref(false)
const submitting = ref(false)
const createForm = reactive({ name: '', sortOrderStr: '' })

async function createSubmit() {
  if (!createForm.name.trim()) return
  submitting.value = true
  try {
    const created = await crud.create({
      name: createForm.name.trim(),
      sortOrder: createForm.sortOrderStr === '' ? null : Number(createForm.sortOrderStr),
    } as Partial<AttributeGroupDto>)
    // элементы уже добавлены в state.items внутри useCrud.create; синхронизируем прокси-строки
    syncRows()
    openCreate.value = false
    createForm.name = ''
    createForm.sortOrderStr = ''
    publishToast('Группа добавлена')
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

// delete group
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
  publishToast('Группа удалена')
  pendingDeleteId.value = null
  deleteOpen.value = false
}
</script>

<style scoped></style>


