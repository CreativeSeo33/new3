import type { ApiResource } from '@admin/types/api'
import { BaseRepository } from './BaseRepository'

export interface ReindexResult extends ApiResource {
  status: string
  count?: number
  seconds?: number
}

export class SearchRepository extends BaseRepository<ApiResource> {
  constructor() {
    super('/admin/search')
  }

  async reindexProducts(): Promise<ReindexResult> {
    const { data } = await this.http.postJson<ReindexResult>('/admin/search/reindex-products')
    return data
  }
}


