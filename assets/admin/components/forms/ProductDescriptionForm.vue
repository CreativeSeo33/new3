<template>
  <form class="grid grid-cols-1 md:grid-cols-2 gap-4" @submit.prevent>
    <Input
      v-model="form.name"
      :label="nameLabel"
      :error="errors.name"
      @blur="() => validateField('name')"
    />

    <Input
      v-model="form.slug"
      :label="slugLabel"
      :error="errors.slug"
      @blur="() => validateField('slug')"
    />

    <Input
      v-model="priceModel"
      :label="priceLabel"
      type="number"
      :error="errors.price"
      :disabled="isPriceFieldsDisabled"
      @blur="() => validateField('price')"
    />

    <Input
      v-model="salePriceModel"
      :label="salePriceLabel"
      type="number"
      :error="errors.salePrice"
      :disabled="isPriceFieldsDisabled"
      @blur="() => validateField('salePrice')"
    />

    <div class="grid gap-1.5">
      <label class="text-sm font-medium text-foreground/80">Статус</label>
      <select v-model="form.status" class="h-9 rounded-md border bg-background px-3 text-sm disabled:opacity-50 disabled:cursor-not-allowed" :disabled="isStatusDisabled">
        <option :value="true">Активен</option>
        <option :value="false">Выключен</option>
      </select>
      <p v-if="isStatusDisabled" class="text-xs text-red-600 dark:text-red-400">
        Нельзя активировать товар без вариаций
      </p>
    </div>

    <Input
      v-model="quantityModel"
      :label="quantityLabel"
      type="number"
      :error="errors.quantity"
      :disabled="isPriceFieldsDisabled"
      @blur="() => validateField('quantity')"
    />

    <Input v-model="form.h1" :label="h1Label" />
    <Input v-model="form.description" :label="descriptionLabel" :error="errors.description" @blur="() => validateField('description')" />
    <Input v-model="form.metaTitle" :label="metaTitleLabel" />
    <Input v-model="form.metaDescription" :label="metaDescriptionLabel" />

    <Input
      v-model="sortOrderModel"
      :label="sortOrderLabel"
      type="number"
      :error="errors.sortOrder"
      @blur="() => validateField('sortOrder')"
    />
  </form>
</template>

<script setup lang="ts">
import Input from '@admin/ui/components/Input.vue'
import type { ProductFormModel, ProductFormErrors } from '@admin/types/product'
import { computed } from 'vue'
import { toInt } from '@admin/utils/num'

interface Props {
  form: ProductFormModel
  errors: ProductFormErrors
  validateField: (field: keyof ProductFormModel) => boolean
  productType?: string
  isVariableWithoutVariations?: boolean
}

const props = defineProps<Props>()

// Определяем, должны ли поля быть disabled
// Для вариативного с ценами — блокируем, цены задаются в вариациях
// Для вариативного без цен — НЕ блокируем (цены на уровне товара)
const isPriceFieldsDisabled = computed(() => props.productType === 'variable')

// Определяем, заблокировано ли поле статуса
// Блокируем только попытку включить статус при отсутствии вариаций
const isStatusDisabled = computed(() => Boolean(props.isVariableWithoutVariations && props.form.status === true))

// Динамические лейблы для полей цены
const priceLabel = computed(() => props.productType === 'simple' ? 'Цена *' : 'Цена')
const salePriceLabel = computed(() => 'Цена со скидкой')

// Обязательные поля всегда имеют *
const nameLabel = computed(() => 'Название *')
const slugLabel = computed(() => 'Slug *')
const quantityLabel = computed(() => 'Количество')
const h1Label = computed(() => 'H1')
const descriptionLabel = computed(() => 'Описание')
const metaTitleLabel = computed(() => 'Meta Title')
const metaDescriptionLabel = computed(() => 'Meta Description')
const sortOrderLabel = computed(() => 'Сортировка')

// локальные computed-обертки, работают и без внешних пропсов
const priceModel = computed<string>({
  get: () => (props.form.price != null ? String(props.form.price) : ''),
  set: (v: string) => { props.form.price = toInt(v) },
})

const salePriceModel = computed<string>({
  get: () => (props.form.salePrice != null ? String(props.form.salePrice) : ''),
  set: (v: string) => { props.form.salePrice = toInt(v) },
})

const quantityModel = computed<string>({
  get: () => (props.form.quantity != null ? String(props.form.quantity) : ''),
  set: (v: string) => { props.form.quantity = toInt(v) },
})

const sortOrderModel = computed<string>({
  get: () => (props.form.sortOrder != null ? String(props.form.sortOrder) : ''),
  set: (v: string) => { props.form.sortOrder = toInt(v) },
})
</script>


