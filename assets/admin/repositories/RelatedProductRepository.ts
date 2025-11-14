import type { ApiResource } from '@admin/types/api'
import { BaseRepository } from './BaseRepository'
import type { ProductDto } from './ProductRepository'

export interface RelatedProductDto extends ApiResource {
  id?: number
  product: string | ProductDto // IRI или вложенный объект
  relatedProduct: string | ProductDto // IRI или вложенный объект
  sortOrder?: number
}

export class RelatedProductRepository extends BaseRepository<RelatedProductDto> {
  constructor() {
    super('/related_products')
  }
}

