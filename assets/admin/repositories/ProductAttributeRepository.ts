import type { ApiResource } from '@admin/types/api'
import { BaseRepository } from './BaseRepository'

export interface ProductAttributeDto extends ApiResource {
  id?: number
  productAttributeGroup?: string | null
  attribute?: string | null
  text?: string | null
}

export class ProductAttributeRepository extends BaseRepository<ProductAttributeDto> {
  constructor() {
    super('/product_attributes')
  }
}


