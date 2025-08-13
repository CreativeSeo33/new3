import type { ApiResource } from '@admin/types/api'
import { BaseRepository } from './BaseRepository'

export interface AttributeGroupDto extends ApiResource {
  id?: number
  name: string | null
  sortOrder: number | null
}

export class AttributeGroupRepository extends BaseRepository<AttributeGroupDto> {
  constructor() {
    // Api Platform по умолчанию создаёт путь /attribute_groups
    super('/attribute_groups')
  }
}


