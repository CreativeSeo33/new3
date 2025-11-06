import type { ApiResource } from '@admin/types/api'
import { BaseRepository } from './BaseRepository'
import type { ProductDto } from './ProductRepository'

export interface BestsellerDto extends ApiResource {
  id?: number
  product: string | ProductDto // IRI или вложенный объект
  sortOrder?: number
}

export class BestsellerRepository extends BaseRepository<BestsellerDto> {
  constructor() {
    super('/bestsellers')
  }
}


