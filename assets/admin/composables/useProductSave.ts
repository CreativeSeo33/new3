import { ref } from 'vue'
import type { ProductFormModel } from '@admin/types/product'
import { ProductRepository, type ProductDto } from '@admin/repositories/ProductRepository'
import { toInt } from '@admin/utils/num'

type Violation = { propertyPath?: string; message?: string }
interface SaveOpts {
	onValidationError?: (violations: Violation[]) => void
}

export function useProductSave() {
	const saving = ref(false)
	const error = ref<string | null>(null)
	const repo = new ProductRepository()

	const saveProduct = async (id: string, data: ProductFormModel, opts: SaveOpts = {}) => {
		saving.value = true
		error.value = null
		try {
			const isVariable = (data as any)?.type === 'variable'

			// строгая фильтрация optionAssignments
			const filteredAssignments = Array.isArray((data as any).optionAssignments)
				? ((data as any).optionAssignments as any[])
					.filter((r) => r && typeof r === 'object')
					.map((r) => ({
						option: r.option || null,
						value: r.value || null,
						height: toInt(r.height),
						bulbsCount: toInt(r.bulbsCount),
						sku: r.sku ?? null,
						originalSku: r.originalSku ?? null,
						price: toInt(r.price),
						setPrice: r.setPrice ?? false,
						salePrice: toInt(r.salePrice),
						lightingArea: toInt(r.lightingArea),
						sortOrder: toInt(r.sortOrder),
						quantity: toInt(r.quantity),
						attributes: r.attributes ?? null,
					}))
					// оставляем только валидные пары опция+значение
					.filter((r) => typeof r.option === 'string' && r.option && typeof r.value === 'string' && r.value)
				: null

			const payload: Partial<ProductDto> = {
				name: data.name || null,
				slug: data.slug || null,
				price: toInt(data.price),
				salePrice: toInt((data as any).salePrice),
				status: data.status ?? null,
				quantity: toInt(data.quantity),
				sortOrder: data.sortOrder ?? null,
				type: data.type || null,
				description: (data as any).description ?? null,
				metaTitle: data.metaTitle || null,
				metaDescription: data.metaDescription || null,
				h1: data.h1 || null,
				optionsJson: (data as any).optionsJson ?? null,
				// для simple товаров не отправляем optionAssignments вовсе
				optionAssignments: isVariable ? (filteredAssignments && filteredAssignments.length ? filteredAssignments : []) : undefined,
			}

			let result: ProductDto
			if (id && id !== 'new') {
				result = await repo.partialUpdate(id, payload)
			} else {
				result = await repo.create(payload)
			}
			return { success: true, result }
		} catch (err: any) {
			const status = err?.response?.status
			const violations = err?.response?.data?.violations || err?.response?.data?.detail?.violations || []
			if ((status === 400 || status === 422) && Array.isArray(violations) && violations.length && opts.onValidationError) {
				opts.onValidationError(violations as Violation[])
			}
			error.value = err instanceof Error ? err.message : 'Ошибка сохранения'
			return { success: false, error: error.value }
		} finally {
			saving.value = false
		}
	}

	return { saving, error, saveProduct }
}


