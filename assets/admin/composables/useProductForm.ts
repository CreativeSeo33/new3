import { computed, reactive, ref, watch } from 'vue'
import { translit } from '@admin/utils/translit'
import type { ProductFormModel, ProductFormErrors } from '@admin/types/product'

export function useProductForm(initialData?: Partial<ProductFormModel>) {
  const form = reactive<ProductFormModel>({
    name: '',
    slug: '',
    price: null,
    salePrice: null,
    status: true,
    quantity: 100,
    description: '',
    metaTitle: '',
    metaDescription: '',
    h1: '',
    sortOrder: 1,
    optionsJson: [],
    optionAssignments: [],
    ...initialData,
  })

  const errors = reactive<ProductFormErrors>({})
  const isValidating = ref(false)

  const createNumberInput = (key: keyof Pick<ProductFormModel, 'price' | 'salePrice' | 'quantity' | 'sortOrder'>) => {
    return computed<string>({
      get: () => {
        const value = form[key]
        return value !== null ? value.toString() : ''
      },
      set: (value: string) => {
        if (value === '') {
          ;(form as any)[key] = null
          return
        }
        const parsed = Number(value)
        ;(form as any)[key] = Number.isFinite(parsed) ? parsed : (form as any)[key]
      },
    })
  }

  const priceInput = createNumberInput('price')
  const salePriceInput = createNumberInput('salePrice')
  const quantityInput = createNumberInput('quantity')
  const sortOrderInput = createNumberInput('sortOrder')

  const validateField = (field: keyof ProductFormModel): boolean => {
    delete (errors as any)[field]

    switch (field) {
      case 'name':
        if (!form.name.trim()) {
          errors.name = 'Название обязательно'
          return false
        }
        if (form.name.length < 3) {
          errors.name = 'Название должно содержать минимум 3 символа'
          return false
        }
        break

      case 'description':
        if (form.description && form.description.length > 255) {
          ;(errors as ProductFormErrors).description = 'Описание не должно превышать 255 символов'
          return false
        }
        break

      case 'slug':
        if (!form.slug.trim()) {
          errors.slug = 'Slug обязателен'
          return false
        }
        if (!/^[a-z0-9-]+$/.test(form.slug)) {
          errors.slug = 'Slug может содержать только строчные буквы, цифры и дефисы'
          return false
        }
        break

      case 'price':
        if (form.price !== null && form.price < 0) {
          errors.price = 'Цена не может быть отрицательной'
          return false
        }
        break

      case 'salePrice':
        if (form.salePrice !== null && form.salePrice < 0) {
          errors.salePrice = 'Цена со скидкой не может быть отрицательной'
          return false
        }
        if (form.salePrice !== null && form.price !== null && form.salePrice > form.price) {
          errors.salePrice = 'Цена со скидкой не может быть больше обычной'
          return false
        }
        break

      case 'quantity':
        if (form.quantity !== null && form.quantity < 0) {
          errors.quantity = 'Количество не может быть отрицательным'
          return false
        }
        break
    }

    return true
  }

  const validateForm = (): boolean => {
    isValidating.value = true
    const fields: (keyof ProductFormModel)[] = ['name', 'slug', 'price', 'salePrice', 'quantity', 'description']
    const results = fields.map((field) => validateField(field))
    return results.every(Boolean)
  }

  const shouldAutoGenerateSlug = ref(true)
  watch(
    () => form.name,
    (newName) => {
      if (!shouldAutoGenerateSlug.value || !newName) return
      const newSlug = translit(String(newName || ''))
      if (form.slug !== newSlug) {
        form.slug = newSlug
      }
    }
  )

  watch(
    () => form.slug,
    (val) => {
      // Нормализация slug и управление автогенерацией
      const normalized = translit(String(val || ''))
      if (normalized !== val) {
        form.slug = normalized
        return
      }
      // Если slug совпадает с автогенерируемым из name — продолжаем автогенерировать
      const auto = translit(String(form.name || ''))
      shouldAutoGenerateSlug.value = !(normalized && normalized !== auto)
    }
  )

  const resetForm = () => {
    Object.assign(form, {
      name: '',
      slug: '',
      price: null,
      salePrice: null,
      status: true,
      quantity: 100,
      description: '',
      metaTitle: '',
      metaDescription: '',
      h1: '',
      sortOrder: 1,
      optionsJson: [],
      ...initialData,
    })
    Object.keys(errors).forEach((key) => delete (errors as any)[key])
    shouldAutoGenerateSlug.value = true
  }

  return {
    form,
    errors,
    isValidating,
    priceInput,
    salePriceInput,
    quantityInput,
    sortOrderInput,
    validateField,
    validateForm,
    resetForm,
  }
}


