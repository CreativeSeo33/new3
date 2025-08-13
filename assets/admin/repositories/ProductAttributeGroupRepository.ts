import type { ApiResource } from '@admin/types/api'
import { BaseRepository } from './BaseRepository'

export interface ProductAttributeGroupDto extends ApiResource {
  id?: number
  product?: string | null
  attributeGroup?: string | null
}

export class ProductAttributeGroupRepository extends BaseRepository<ProductAttributeGroupDto> {
  constructor() {
    super('/product_attribute_groups')
  }
}


