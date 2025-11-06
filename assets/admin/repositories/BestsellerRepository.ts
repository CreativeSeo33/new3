import type { ApiResource } from '@admin/types/api'
import { BaseRepository } from './BaseRepository'

export interface BestsellerDto extends ApiResource {
  id?: number
  product: string
  sortOrder?: number
}

export class BestsellerRepository extends BaseRepository<BestsellerDto> {
  constructor() {
    super('/bestsellers')
  }
}


