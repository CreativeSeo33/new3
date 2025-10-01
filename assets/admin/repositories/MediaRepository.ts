import { httpClient } from '@admin/services/http'

export class MediaRepository {
  async fetchFolderTree(): Promise<{ tree: Array<{ name: string; path: string; children?: any[] }> }> {
    const { data } = await httpClient.getJson<{ tree: any[] }>('/admin/media/tree')
    return { tree: Array.isArray(data?.tree) ? data.tree : [] }
  }

  async fetchImages(dir: string): Promise<{ items: Array<{ name: string; relative: string; url: string }>; dir: string }> {
    const qs = dir ? `?dir=${encodeURIComponent(dir)}` : ''
    const { data } = await httpClient.getJson<{ items: any[]; dir: string }>(`/admin/media/list${qs}`)
    return { items: Array.isArray(data?.items) ? data.items : [], dir: data?.dir || '' }
  }

  async attachProductImages(productId: string | number, relatives: string[]): Promise<void> {
    await httpClient.postJson(`/admin/media/product/${productId}/images`, { items: relatives })
  }

  async deleteProductImage(imageId: number): Promise<void> {
    await httpClient.deleteJson(`/admin/media/product-image/${imageId}`)
  }

  async reorderProductImages(productId: string | number, orderIds: number[]): Promise<{ items?: Array<{ id: number; sortOrder: number }> }> {
    const { data } = await httpClient.postJson<{ items?: Array<{ id: number; sortOrder: number }> }>(`/admin/media/product/${productId}/images/reorder`, { order: orderIds })
    return data || {}
  }

  async fetchProductImages(productId: string | number): Promise<Array<{ id: number; imageUrl: string; sortOrder: number }>> {
    const { data } = await httpClient.getJson<{ items: Array<{ id: number; imageUrl: string; sortOrder: number }> }>(`/admin/media/product/${productId}/images`)
    return Array.isArray(data?.items) ? data.items : []
  }

  async warmProductImages(productId: string | number): Promise<{ warmed?: number; errors?: number }> {
    const { data } = await httpClient.postJson<{ warmed?: number; errors?: number }>(`/admin/media/product/${productId}/images/warmup`, {})
    return data || {}
  }
}


