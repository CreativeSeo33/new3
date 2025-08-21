<template>
  <div class="rounded-md border">
    <div class="border-b px-3 py-2 text-sm font-medium">{{ groupName }}</div>
    <table class="w-full text-sm">
      <thead class="bg-neutral-50 text-neutral-600 dark:bg-neutral-900/40 dark:text-neutral-300">
        <tr>
          <th class="px-3 py-2 text-left">Атрибут</th>
          <th class="px-3 py-2 text-left">Значение</th>
          <th class="px-3 py-2 text-left w-28">Действия</th>
        </tr>
      </thead>
      <tbody>
        <tr 
          v-for="item in items" 
          :key="item.id" 
          class="border-t"
        >
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
            <button 
              type="button" 
              class="h-8 rounded-md bg-red-600 px-2 text-xs font-medium text-white hover:bg-red-700" 
              @click="confirmDelete(item.id)"
            >
              Удалить
            </button>
          </td>
        </tr>
        <tr v-if="items.length === 0">
          <td colspan="3" class="px-3 py-6 text-center text-neutral-500">Нет атрибутов</td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script setup lang="ts">
import { ProductAttributeRow } from '@admin/utils/attributeUtils'

interface Props {
  groupName: string
  items: ProductAttributeRow[]
}

interface Emits {
  (e: 'save', item: ProductAttributeRow): void
  (e: 'delete', id: number): void
}

defineProps<Props>()
const emit = defineEmits<Emits>()

function saveProductAttribute(item: ProductAttributeRow) {
  emit('save', item)
}

function confirmDelete(id: number) {
  emit('delete', id)
}
</script>
