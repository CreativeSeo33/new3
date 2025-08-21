<template>
  <div class="rounded-md border p-4 dark:border-neutral-800">
    <div class="mb-3 flex items-center justify-between">
      <div class="text-sm font-medium">Атрибуты</div>
      <Button size="sm" @click="attrModalOpen = true">Добавить</Button>
    </div>
    <div v-if="attrLoading" class="text-sm text-neutral-500">Загрузка…</div>
    <div v-else class="space-y-6">
      <div 
        v-for="group in productAttrGroups" 
        :key="group.groupIri || '__no_group__'" 
        class="rounded-md border"
      >
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
            <tr 
              v-for="item in group.items" 
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
                  @click="confirmDeletePA(item.id)"
                >
                  Удалить
                </button>
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
  <ProductAttributesAddModal 
    v-if="attrModalOpen" 
    v-model="attrModalOpen" 
    @add="onAddFromModal" 
  />
  <ConfirmDialog 
    v-model="deletePAOpen" 
    title="Удалить атрибут?" 
    description="Это действие необратимо" 
    confirm-text="Удалить" 
    :danger="true" 
    @confirm="performDeletePA" 
  />
</template>

<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import Button from '@admin/ui/components/Button.vue'
import ProductAttributesAddModal from '@admin/components/forms/ProductAttributesAddModal.vue'
import ConfirmDialog from '@admin/components/ConfirmDialog.vue'
import { ProductAttributeAssignmentRepository } from '@admin/repositories/ProductAttributeAssignmentRepository'
import { AttributeRepository } from '@admin/repositories/AttributeRepository'
import { AttributeGroupRepository } from '@admin/repositories/AttributeGroupRepository'
import { useProductAttributes } from '@admin/composables/useProductAttributes'

// Props and emits
const props = defineProps<{ 
  productId: string
  isCreating: boolean 
}>()

const emit = defineEmits<{
  (e: 'toast', message: string): void
}>()

// Repositories
const assignmentRepo = new ProductAttributeAssignmentRepository()
const attributeRepo = new AttributeRepository()
const attributeGroupRepo = new AttributeGroupRepository()

// Reactive state
const attrModalOpen = ref(false)
const deletePAOpen = ref(false)
const pendingPAId = ref<number | null>(null)

// Composable for attribute management
const { 
  attrLoading, 
  productAttrGroups, 
  loadProductAttributes, 
  loadAttributesBootstrap,
  saveProductAttribute,
  addAttributeToProduct
} = useProductAttributes({
  assignmentRepo,
  attributeRepo,
  attributeGroupRepo,
  productId: props.productId,
  emit
})

// Delete operations
function confirmDeletePA(idNum: number) { 
  pendingPAId.value = idNum
  deletePAOpen.value = true 
}

async function performDeletePA() {
  if (pendingPAId.value == null) return
  
  try {
    await assignmentRepo.delete(pendingPAId.value)
    
    // Update UI immediately
    for (const g of productAttrGroups.value) {
      g.items = g.items.filter(i => i.id !== pendingPAId.value!)
    }
    
    emit('toast', 'Удалено')
  } catch (error) {
    emit('toast', 'Ошибка при удалении атрибута')
  } finally {
    pendingPAId.value = null
  }
}

// Modal handlers
async function onAddFromModal(payload: { attributeIri: string; value: string }) {
  if (props.isCreating || !props.productId || props.productId === 'new') {
    emit('toast', 'Сначала сохраните товар')
    return
  }
  
  try {
    await addAttributeToProduct(payload)
    emit('toast', 'Атрибут добавлен к товару')
  } catch (error) {
    emit('toast', 'Ошибка при добавлении атрибута')
  }
}

// Lifecycle and watchers
onMounted(async () => {
  await loadAttributesBootstrap()
  await loadProductAttributes()
})

watch(() => props.productId, async (val, oldVal) => {
  if (val !== oldVal) {
    // Очищаем данные перед загрузкой новых
    productAttrGroups.value = []
    await loadProductAttributes()
  }
})
</script>

<style scoped></style>


