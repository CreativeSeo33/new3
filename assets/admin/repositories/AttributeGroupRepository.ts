import type { ApiResource, HydraCollection } from '@admin/types/api'
import { BaseRepository } from './BaseRepository'
import { adminCache } from '@admin/utils/persistentCache'

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

  async findAllCached(force: boolean = false): Promise<HydraCollection<AttributeGroupDto>> {
    const key = 'attribute_groups:all:sorted'
    const version = 'v1'
    const ttl = 24 * 60 * 60 * 1000
    if (!force) {
      const cached = adminCache.get<HydraCollection<AttributeGroupDto>>(key, version, ttl)
      if (cached) return cached
    }
    const data = await this.findAll({ itemsPerPage: 1000, sort: { sortOrder: 'asc', name: 'asc' } })
    adminCache.set(key, version, data)
    return data
  }

  invalidatePersistentCache(): void {
    adminCache.clear('attribute_groups:')
  }
}


