<template>
  <div class="rounded-md border p-4 dark:border-neutral-800">
    <div class="mb-3 flex items-center justify-between">
      <div class="text-sm font-medium">Атрибуты</div>
      <Button size="sm" @click="attrModalOpen = true">Добавить</Button>
    </div>
    
    <!-- Отображение ошибки загрузки -->
    <div v-if="error" class="mb-3 rounded-md bg-red-50 p-3 text-sm text-red-700 dark:bg-red-900/20 dark:text-red-400">
      {{ error }}
    </div>
    
    <!-- Индикатор загрузки -->
    <div v-if="attrLoading" class="text-sm text-neutral-500">Загрузка…</div>
    
    <!-- Список групп атрибутов -->
    <div v-else-if="productAttrGroups.length > 0" class="space-y-6">
      <ProductAttributeGroup
        v-for="group in productAttrGroups"
        :key="group.groupIri || '__no_group__'"
        :group-name="group.groupName"
        :items="group.items"
        @save="saveProductAttribute"
        @delete="confirmDeletePA"
      />
    </div>
    
    <!-- Сообщение об отсутствии атрибутов -->
    <div v-else class="text-center text-sm text-neutral-500">
      У товара нет атрибутов
    </div>
  </div>
  
  <!-- Модальное окно добавления атрибута -->
  <ProductAttributesAddModal 
    v-if="attrModalOpen" 
    v-model="attrModalOpen" 
    :existing-attributes="existingAttributeIris"
    @add="onAddFromModal" 
  />
  
  <!-- Диалог подтверждения удаления -->
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
import { onMounted, ref, watch, computed } from 'vue'
import Button from '@admin/ui/components/Button.vue'
import ProductAttributesAddModal from '@admin/components/forms/ProductAttributesAddModal.vue'
import ConfirmDialog from '@admin/components/ConfirmDialog.vue'
import ProductAttributeGroup from '@admin/components/forms/ProductAttributeGroup.vue'
import { ProductAttributeAssignmentRepository } from '@admin/repositories/ProductAttributeAssignmentRepository'
import { AttributeRepository } from '@admin/repositories/AttributeRepository'
import { AttributeGroupRepository } from '@admin/repositories/AttributeGroupRepository'
import { useProductAttributes } from '@admin/composables/useProductAttributes'
import { getAttributeIri, getHydraMembers } from '@admin/utils/attributeUtils'

// Props и emits
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

// Состояние для хранения всех атрибутов
const allAttrs = ref<any[]>([])

// Реактивное состояние
const attrModalOpen = ref(false)
const deletePAOpen = ref(false)
const pendingPAId = ref<number | null>(null)

// Composable для управления атрибутами
const { 
  attrLoading, 
  productAttrGroups, 
  error,
  attributesLoaded,
  attributesPrefetched,
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

// Получаем IRI существующих атрибутов для отключения их в модальном окне
const existingAttributeIris = computed(() => {
  const iris: string[] = []
  productAttrGroups.value.forEach(group => {
    group.items.forEach(item => {
      // Находим IRI атрибута по его имени
      const attribute = allAttrs.value?.find((attr: any) => attr.name === item.attributeName)
      if (attribute) {
        const iri = getAttributeIri(attribute)
        if (iri) iris.push(iri)
      }
    })
  })
  return iris
})

// Операции удаления
function confirmDeletePA(idNum: number) { 
  pendingPAId.value = idNum
  deletePAOpen.value = true 
}

async function performDeletePA() {
  if (pendingPAId.value == null) return
  
  try {
    await assignmentRepo.delete(pendingPAId.value)
    
    // Обновляем UI немедленно
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

// Обработчики модального окна
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

// Жизненный цикл и наблюдатели
onMounted(async () => {
  // Загружаем все атрибуты для справочника
  try {
    const attrsData = await attributeRepo.findAllCached()
    allAttrs.value = getHydraMembers(attrsData)
  } catch (error) {
    console.error('Failed to load attributes:', error)
  }
  
  await loadAttributesBootstrap()
  // loadProductAttributes будет вызван через watcher с immediate: true
})

watch(() => props.productId, async (newVal, oldVal) => {
  if (newVal !== oldVal) {
    // Очищаем данные перед загрузкой новых
    productAttrGroups.value = []
    error.value = null
    attributesLoaded.value = false
    attributesPrefetched.value = false
    await loadProductAttributes()
  }
}, { immediate: true })
</script>

<style scoped></style>


