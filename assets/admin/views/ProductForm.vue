<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-xl font-semibold">
        {{ isCreating ? 'Новый товар' : `Товар #${id}` }}
      </h1>
      <div class="flex gap-2">
        <Button variant="secondary" :disabled="saving" @click="handleSave">Сохранить</Button>
      </div>
    </div>

    <div v-if="saveError" class="p-3 rounded border border-red-200 bg-red-50 text-sm text-red-800">
      {{ saveError }}
    </div>

    <TabsRoot v-model="activeTab" class="w-full" :unmount-on-hide="false">
      <TabsList aria-label="product form tabs" class="relative flex w-full gap-2 border-b border-gray-200">
        <TabsIndicator class="absolute bottom-[-1px] h-[2px] bg-brand-500 transition-all" />
        <TabsTrigger
          v-for="tab in tabs"
          :key="tab.value"
          :value="tab.value"
          class="px-3 py-2 text-sm data-[state=active]:text-brand-600"
        >
          {{ tab.label }}
        </TabsTrigger>
      </TabsList>

      <TabsContent value="description" class="pt-6">
        <ProductDescriptionForm :form="form" :errors="errors" :validate-field="validateField" />
      </TabsContent>

      <TabsContent value="categories" class="pt-6">
        <div class="text-sm text-muted-foreground">Пока пусто</div>
      </TabsContent>

      <TabsContent value="attributes" class="pt-6">
        <div class="text-sm text-muted-foreground">Пока пусто</div>
      </TabsContent>

      <TabsContent value="photos" class="pt-6">
        <div class="text-sm text-muted-foreground">Пока пусто</div>
      </TabsContent>
    </TabsRoot>

    <!-- Toast instance(s) -->
    <ToastRoot v-for="n in toastCount" :key="n" type="foreground" :duration="3000">
      <ToastDescription>{{ lastToastMessage }}</ToastDescription>
    </ToastRoot>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Button from '@admin/ui/components/Button.vue'
import { TabsContent, TabsIndicator, TabsList, TabsRoot, TabsTrigger, ToastDescription, ToastRoot } from 'reka-ui'
import ProductDescriptionForm from '@admin/components/forms/ProductDescriptionForm.vue'
import { useProductForm } from '@admin/composables/useProductForm'
import { useProductSave } from '@admin/composables/useProductSave'
import type { ProductTab } from '@admin/types/product'

const tabs: ProductTab[] = [
  { value: 'description', label: 'Описание товара' },
  { value: 'categories', label: 'Категории' },
  { value: 'attributes', label: 'Аттрибуты' },
  { value: 'photos', label: 'Фотографии' },
]

const route = useRoute()
const router = useRouter()
const id = computed(() => route.params.id as string)
const isCreating = computed(() => !id.value || id.value === 'new')
const activeTab = ref<string>('description')

const {
  form,
  errors,
  priceInput,
  quantityInput,
  sortOrderInput,
  validateField,
  validateForm,
} = useProductForm()

const { saving, error: saveError, saveProduct } = useProductSave()

const handleSave = async () => {
  if (!validateForm()) {
    activeTab.value = 'description'
    return
  }
  const result = await saveProduct(id.value, form)
  if (result.success) {
    // показать toast + редирект
    publishToast('Товар сохранён')
    const newId = (result.result as any)?.id ?? id.value
    router.push({ name: 'admin-product-form', params: { id: newId } })
  }
}

// Imperative toast publisher per Reka UI docs (Duplicate toasts)
import { ref as vueRef } from 'vue'
const toastCount = vueRef(0)
function publishToast(message: string) {
  lastToastMessage.value = message
  toastCount.value++
}
const lastToastMessage = vueRef('')
</script>

<style scoped></style>


