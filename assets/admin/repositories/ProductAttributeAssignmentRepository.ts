import type { ApiResource } from '@admin/types/api'
import { BaseRepository } from './BaseRepository'

export interface ProductAttributeAssignmentDto extends ApiResource {
  id?: number
  product: string
  attribute: string
  attributeGroup?: string | null
  dataType: 'string' | 'text' | 'int' | 'decimal' | 'bool' | 'json' | 'date'
  stringValue?: string | null
  textValue?: string | null
  intValue?: number | null
  decimalValue?: string | null
  boolValue?: boolean | null
  dateValue?: string | null
  jsonValue?: any[] | Record<string, any> | null
  unit?: string | null
  position?: number
  sortOrder?: number | null
}

export class ProductAttributeAssignmentRepository extends BaseRepository<ProductAttributeAssignmentDto> {
  constructor() {
    super('/product_attribute_assignments')
  }
}


