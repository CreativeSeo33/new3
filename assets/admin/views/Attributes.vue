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
              <td class="px-4 py-2">{{ attr.name }}</td>
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

    <Modal v-model="openCreate" title="Новый атрибут">
      <form class="space-y-4" @submit.prevent="submit">
        <Input v-model="form.name" label="Название" placeholder="Например: Цвет" />

        <div class="flex justify-end gap-2 pt-2">
          <button type="button" class="h-9 rounded-md px-3 text-sm hover:bg-neutral-100 dark:hover:bg-white/10" @click="openCreate = false">Отмена</button>
          <button
            type="submit"
            :disabled="submitting || !form.name.trim()"
            class="inline-flex h-9 items-center rounded-md bg-neutral-900 px-3 text-sm font-medium text-white shadow hover:bg-neutral-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:opacity-50 dark:bg-white dark:text-neutral-900 dark:hover:bg-neutral-100"
          >
            {{ submitting ? 'Сохранение…' : 'Сохранить' }}
          </button>
        </div>
      </form>
    </Modal>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import Modal from '@admin/ui/components/Modal.vue'
import Input from '@admin/ui/components/Input.vue'

type Attribute = { id: number; name: string | null }

const attributes = ref<Attribute[]>([])
const loading = ref<boolean>(false)

const openCreate = ref<boolean>(false)
const submitting = ref<boolean>(false)
const form = ref<{ name: string }>({ name: '' })

async function fetchAttributes() {
  loading.value = true
  try {
    const res = await fetch('/api/attributes', {
      headers: { 'Accept': 'application/json' }
    })
    if (!res.ok) throw new Error('Failed to load attributes')
    const data = await res.json()
    // API Platform возвращает hydra коллекцию, но также может быть простой массив — покроем оба варианта
    const items = Array.isArray(data) ? data : (data['hydra:member'] ?? [])
    attributes.value = items as Attribute[]
  } finally {
    loading.value = false
  }
}

async function submit() {
  if (!form.value.name.trim()) return
  submitting.value = true
  try {
    const res = await fetch('/api/attributes', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      body: JSON.stringify({ name: form.value.name.trim() })
    })
    if (!res.ok) {
      const err = await res.text()
      throw new Error(err || 'Failed to create attribute')
    }
    form.value.name = ''
    openCreate.value = false
    await fetchAttributes()
  } finally {
    submitting.value = false
  }
}

onMounted(() => {
  fetchAttributes()
})
</script>


