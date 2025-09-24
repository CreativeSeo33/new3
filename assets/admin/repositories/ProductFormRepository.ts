import { httpClient } from '@admin/services/http'
import { adminCache } from '@admin/utils/persistentCache'

export type ProductFormBootstrap = {
  product: any
  categories: {
    treeVersion: string
    tree: Array<{ id: number; label: string; children?: any[] }>
    selectedCategoryIds: number[]
    mainCategoryId: number | null
  }
  options: {
    version: string
    list: Array<{ id: number; name: string }>
    valuesByOption: Record<string, Array<{ id: number; value: string }>>
  }
  photos: Array<{ id: number; imageUrl: string; sortOrder: number }>
  dictVersions: { categories: string; options: string }
  flags: { isVariableWithoutVariations: boolean }
}

export type ProductFormSyncPayload = {
  product: {
    name: string
    slug: string
    price: number | null
    salePrice: number | null
    status: boolean
    quantity: number | null
    sortOrder: number | null
    type: string
    description: string
    metaTitle: string
    metaDescription: string
    h1: string
  }
  categories: { selectedCategoryIds: number[]; mainCategoryId: number | null }
  optionAssignments: Array<{
    option: string
    value: string | null
    height: number | null
    bulbsCount: number | null
    sku: string | null
    originalSku?: string | null
    price: number | null
    setPrice?: boolean | null
    salePrice?: number | null
    lightingArea: number | null
    sortOrder?: number | null
    quantity?: number | null
    attributes?: Record<string, any> | null
  }>
  photosOrder?: number[]
}

export class ProductFormRepository {
  async fetchForm(id?: string | number): Promise<ProductFormBootstrap> {
    const url = id ? `/admin/products/${id}/form` : '/admin/products/form'
    const { data } = await httpClient.getJson<ProductFormBootstrap>(url)
    // Warm persistent cache for categories/options using versions
    try {
      const treeVersion = data?.categories?.treeVersion
      const tree = data?.categories?.tree
      if (treeVersion && Array.isArray(tree)) {
        adminCache.set('categories:tree', treeVersion, { treeVersion, tree })
      }
      const optVersion = data?.options?.version
      const optList = data?.options?.list
      const valuesByOption = data?.options?.valuesByOption
      if (optVersion && Array.isArray(optList) && valuesByOption && typeof valuesByOption === 'object') {
        adminCache.set('options:list', optVersion, optList)
        adminCache.set('options:valuesByOption', optVersion, valuesByOption)
      }
    } catch {}
    return data
  }

  async sync(id: string | number, payload: ProductFormSyncPayload): Promise<ProductFormBootstrap> {
    const { data } = await httpClient.postJson<ProductFormBootstrap>(`/admin/products/${id}/sync`, payload)
    // Warm cache from response
    try {
      const treeVersion = data?.categories?.treeVersion
      const tree = data?.categories?.tree
      if (treeVersion && Array.isArray(tree)) {
        adminCache.set('categories:tree', treeVersion, { treeVersion, tree })
      }
      const optVersion = data?.options?.version
      const optList = data?.options?.list
      const valuesByOption = data?.options?.valuesByOption
      if (optVersion && Array.isArray(optList) && valuesByOption && typeof valuesByOption === 'object') {
        adminCache.set('options:list', optVersion, optList)
        adminCache.set('options:valuesByOption', optVersion, valuesByOption)
      }
    } catch {}
    return data
  }
}


