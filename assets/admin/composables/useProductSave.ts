import { ref } from 'vue'
import type { ProductFormModel } from '@admin/types/product'
import { ProductRepository, type ProductDto } from '@admin/repositories/ProductRepository'

export function useProductSave() {
  const saving = ref(false)
  const error = ref<string | null>(null)
  const repo = new ProductRepository()

  const saveProduct = async (id: string, data: ProductFormModel) => {
    saving.value = true
    error.value = null
    try {
      const toInt = (v: unknown): number | null => {
        if (v === null || v === undefined || v === '') return null
        const s = String(v).trim().replace(',', '.')
        const n = Number(s)
        return Number.isFinite(n) ? Math.trunc(n) : null
      }

      const payload: Partial<ProductDto> = {
        name: data.name || null,
        slug: data.slug || null,
        price: toInt(data.price),
        salePrice: toInt((data as any).salePrice),
        status: data.status ?? null,
        quantity: toInt(data.quantity),
        sortOrder: data.sortOrder ?? null,
        description: (data as any).description ?? null,
        metaTitle: data.metaTitle || null,
        metaDescription: data.metaDescription || null,
        h1: data.h1 || null,
        optionsJson: (data as any).optionsJson ?? null,
      }

      let result: ProductDto
      if (id && id !== 'new') {
        result = await repo.partialUpdate(id, payload)
      } else {
        result = await repo.create(payload)
      }
      return { success: true, result }
    } catch (err: any) {
      error.value = err instanceof Error ? err.message : 'Ошибка сохранения'
      return { success: false, error: error.value }
    } finally {
      saving.value = false
    }
  }

  return { saving, error, saveProduct }
}


