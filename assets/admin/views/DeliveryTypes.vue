<template>
  <div class="space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div class="flex items-center gap-3">
        <h1 class="text-xl font-semibold">Типы доставок</h1>
        <div class="text-sm text-neutral-500">Всего: <span class="font-medium text-neutral-700 dark:text-neutral-300">{{ totalItems }}</span></div>
      </div>
      <button
        type="button"
        class="inline-flex h-9 items-center rounded-md bg-neutral-900 px-3 text-sm font-medium text-white shadow hover:bg-neutral-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:opacity-50 dark:bg-white dark:text-neutral-900 dark:hover:bg-neutral-100"
        @click="openCreate = true"
      >
        Добавить тип доставки
      </button>
    </div>

    <div v-if="state.error" class="mb-2 rounded-md border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-700">
      {{ state.error }}
    </div>

    <div class="rounded-md border">
      <table class="w-full text-sm">
        <thead class="bg-neutral-50 text-neutral-600 dark:bg-neutral-900/40 dark:text-neutral-300">
          <tr>
            <th class="px-4 py-2 text-left">Название</th>
            <th class="px-4 py-2 text-left">Код</th>
            <th class="px-4 py-2 text-left w-24">Активен</th>
            <th class="px-4 py-2 text-left w-24">По умолч.</th>
            <th class="px-4 py-2 text-left w-28">Сортировка</th>
            <th class="px-4 py-2 text-left w-28">Действия</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in rows" :key="row.id" class="border-t">
            <td class="px-4 py-2">
              <Input v-model="row.name" placeholder="Название" @blur="() => saveRow(row)" />
            </td>
            <td class="px-4 py-2">
              <Input v-model="row.code" placeholder="Код" @blur="() => saveRow(row)" />
            </td>
            <td class="px-4 py-2">
              <input type="checkbox" v-model="row.active" @change="() => saveRow(row)" />
            </td>
            <td class="px-4 py-2">
              <input type="checkbox" :checked="row.default" @change="() => setDefault(row.id)" />
            </td>
            <td class="px-4 py-2">
              <Input v-model="row.sortOrderStr" type="number" placeholder="0" @blur="() => saveRow(row)" />
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
            <td colspan="5" class="px-4 py-6 text-center text-neutral-500">Нет записей</td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="flex items-center justify-between">
      <div class="text-sm text-neutral-500">Всего записей: <span class="font-medium text-neutral-700 dark:text-neutral-300">{{ rows.length }}</span></div>
      <div />
    </div>

    <DialogRoot v-model:open="openCreate">
      <DialogPortal>
        <DialogOverlay class="fixed inset-0 bg-black/50 backdrop-blur-[1px]" />
        <DialogContent class="fixed left-1/2 top-1/2 w-[92vw] max-w-md -translate-x-1/2 -translate-y-1/2 rounded-md border bg-white p-4 shadow-lg focus:outline-none dark:border-neutral-800 dark:bg-neutral-900">
          <div class="mb-2">
            <DialogTitle class="text-base font-semibold text-neutral-900 dark:text-neutral-100">Новый тип доставки</DialogTitle>
            <DialogDescription class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">Заполните поля ниже</DialogDescription>
          </div>
          <form class="space-y-4" @submit.prevent="createSubmit">
            <Input v-model="createForm.name" label="Название" placeholder="Например: Курьер" />
            <Input v-model="createForm.code" label="Код" placeholder="Например: courier" />
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" v-model="createForm.active" /> Активен</label>
            <Input v-model="createForm.sortOrderStr" label="Сортировка" type="number" placeholder="0" />
            <div class="flex justify-end gap-2 pt-2">
              <button type="button" class="h-9 rounded-md px-3 text-sm hover:bg-neutral-100 dark:hover:bg-white/10" @click="openCreate = false">Отмена</button>
              <button type="submit" :disabled="submitting || !createForm.name.trim() || !createForm.code.trim()" class="inline-flex h-9 items-center rounded-md bg-neutral-900 px-3 text-sm font-medium text-white shadow hover:bg-neutral-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:opacity-50 dark:bg-white dark:text-neutral-900 dark:hover:bg-neutral-100">
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
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { DialogClose, DialogContent, DialogDescription, DialogOverlay, DialogPortal, DialogRoot, DialogTitle } from 'reka-ui'
import Input from '@admin/ui/components/Input.vue'
import { useRoute, useRouter } from 'vue-router'
import { useCrud } from '@admin/composables/useCrud'
import { DeliveryTypeRepository, type DeliveryType } from '@admin/repositories/DeliveryTypeRepository'
import { getPaginationConfig } from '@admin/services/config'

type EditableRow = {
  id: number
  name: string
  code: string
  active: boolean
  default: boolean
  sortOrderStr: string
}

const repo = new DeliveryTypeRepository()
const crud = useCrud<DeliveryType>(repo)
const state = crud.state
const loading = computed(() => !!state.loading)
const totalItems = computed(() => state.items.length)

const rows = ref<EditableRow[]>([])
const route = useRoute()
const router = useRouter()

onMounted(async () => {
  await crud.fetchAll({ sort: { sortOrder: 'asc' } })
  syncRows()
})

// без пагинации — URL не синхронизируем

watch(() => state.items, () => syncRows(), { deep: true })

function syncRows() {
  const items = ((state.items ?? []) as DeliveryType[]).slice()
  items.sort((a, b) => Number(a.sortOrder ?? 0) - Number(b.sortOrder ?? 0))
  rows.value = items.map((d) => ({
    id: Number(d.id),
    name: String(d.name ?? ''),
    code: String(d.code ?? ''),
    active: Boolean((d as any).active ?? true),
    default: Boolean((d as any)['default'] ?? false),
    sortOrderStr: String(Number((d as any).sortOrder ?? 0)),
  }))
}

async function saveRow(row: EditableRow) {
  const payload: Partial<DeliveryType> = {
    name: row.name.trim() || '',
    code: row.code.trim() || '',
    active: Boolean(row.active),
    sortOrder: row.sortOrderStr === '' ? 0 : Number(row.sortOrderStr),
  }
  await crud.update(row.id, payload)
}

const openCreate = ref(false)
const submitting = ref(false)
const createForm = reactive({ name: '', code: '', active: true, default: false, sortOrderStr: '0' })

async function createSubmit() {
  if (!createForm.name.trim() || !createForm.code.trim()) return
  submitting.value = true
  try {
    await crud.create({
      name: createForm.name.trim(),
      code: createForm.code.trim(),
      active: Boolean(createForm.active),
      'default': Boolean(createForm.default),
      sortOrder: Number(createForm.sortOrderStr || '0'),
    } as Partial<DeliveryType>)
    syncRows()
    openCreate.value = false
    Object.assign(createForm, { name: '', code: '', active: true, default: false, sortOrderStr: '0' })
  } finally {
    submitting.value = false
  }
}

async function setDefault(id: number) {
  // Обеспечиваем единственность: отмечаем выбранный как default=true, остальные false
  const current = rows.value.find(r => r.id === id)
  if (!current) return
  // Оптимистично обновим UI
  rows.value = rows.value.map(r => ({ ...r, default: r.id === id }))
  // Сначала снимем default у всех прочих
  const others = rows.value.filter(r => r.id !== id && r.default)
  await Promise.all(others.map(r => crud.update(r.id, { 'default': false } as Partial<DeliveryType>)))
  // Поставим default выбранному
  await crud.update(id, { 'default': true } as Partial<DeliveryType>)
}
</script>


