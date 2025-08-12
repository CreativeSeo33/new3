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

    <div>
      <div v-if="crudState.error" class="mb-2 rounded-md border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-700">
        {{ crudState.error }}
      </div>
      <div class="overflow-hidden rounded-md border">
        <table class="w-full text-sm">
          <thead class="bg-neutral-50 text-neutral-600 dark:bg-neutral-900/40 dark:text-neutral-300">
            <tr>
              <th class="px-4 py-2 text-left w-20">ID</th>
              <th class="px-4 py-2 text-left">Название</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="attr in attributes" :key="attr.id" class="border-t">
              <td class="px-4 py-2">{{ attr.id }}</td>
              <td class="px-4 py-2">
                <button
                  type="button"
                  class="text-left text-neutral-900 underline-offset-2 hover:underline dark:text-neutral-100"
                  @click="startEdit(attr)"
                >
                  {{ attr.name }}
                </button>
              </td>
            </tr>
            <tr v-if="!loading && attributes.length === 0">
              <td colspan="2" class="px-4 py-8 text-center text-neutral-500">Пока нет атрибутов</td>
            </tr>
            <tr v-if="loading">
              <td colspan="2" class="px-4 py-8 text-center text-neutral-500">Загрузка…</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <DialogRoot v-model:open="openCreate">
      <DialogPortal>
        <DialogOverlay class="fixed inset-0 bg-black/50 backdrop-blur-[1px]" />
        <DialogContent
          class="fixed left-1/2 top-1/2 w-[92vw] max-w-md -translate-x-1/2 -translate-y-1/2 rounded-md border bg-white p-4 shadow-lg focus:outline-none dark:border-neutral-800 dark:bg-neutral-900"
        >
          <div class="mb-2">
            <DialogTitle class="text-base font-semibold text-neutral-900 dark:text-neutral-100">
              {{ isEditing ? 'Редактировать атрибут' : 'Новый атрибут' }}
            </DialogTitle>
            <DialogDescription class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
              Заполните поля ниже и сохраните изменения
            </DialogDescription>
          </div>

          <form class="space-y-4" @submit.prevent="submit">
            <Input v-model="form.name" label="Название" placeholder="Например: Цвет" />

            <div class="flex justify-end gap-2 pt-2">
              <button type="button" class="h-9 rounded-md px-3 text-sm hover:bg-neutral-100 dark:hover:bg-white/10" @click="openCreate = false">Отмена</button>
              <button
                type="submit"
                :disabled="submitting || !form.name.trim()"
                class="inline-flex h-9 items-center rounded-md bg-neutral-900 px-3 text-sm font-medium text-white shadow hover:bg-neutral-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:opacity-50 dark:bg-white dark:text-neutral-900 dark:hover:bg-neutral-100"
              >
                {{ submitting ? 'Сохранение…' : (isEditing ? 'Обновить' : 'Сохранить') }}
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
import { onMounted, ref, computed } from 'vue'
import { DialogRoot, DialogPortal, DialogOverlay, DialogContent, DialogTitle, DialogDescription, DialogClose } from 'reka-ui'
import Input from '@admin/ui/components/Input.vue'
import { useCrud } from '@admin/composables/useCrud'
import { AttributeRepository, type Attribute } from '@admin/repositories/AttributeRepository'

const attributeRepository = new AttributeRepository()
const crud = useCrud<Attribute>(attributeRepository)
const crudState = crud.state
const attributes = computed<Attribute[]>(() => (crudState.items ?? []) as Attribute[])
const loading = computed(() => !!crudState.loading)

const openCreate = ref<boolean>(false)
const submitting = ref<boolean>(false)
const form = ref<{ name: string }>({ name: '' })
const editingId = ref<number | null>(null)
const isEditing = computed(() => editingId.value !== null)

async function fetchAttributes() {
  await crud.fetchAll({ itemsPerPage: 50 })
}

async function submit() {
  if (!form.value.name.trim()) return
  submitting.value = true
  try {
    if (isEditing.value && editingId.value !== null) {
      await crud.update(editingId.value, { name: form.value.name.trim() } as Partial<Attribute>)
    } else {
      await crud.create({ name: form.value.name.trim() } as Partial<Attribute>)
    }
    form.value.name = ''
    editingId.value = null
    openCreate.value = false
    // список уже оптимистично обновлен; при желании можно перезагрузить с сервера
    // await fetchAttributes()
  } finally {
    submitting.value = false
  }
}

function startEdit(attr: Attribute) {
  editingId.value = attr.id
  form.value.name = attr.name ?? ''
  openCreate.value = true
}

onMounted(() => {
  fetchAttributes()
})
</script>


