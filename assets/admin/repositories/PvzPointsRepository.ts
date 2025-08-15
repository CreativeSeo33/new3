import type { ApiResource } from '@admin/types/api'
import { BaseRepository } from './BaseRepository'

export interface PvzPoint extends ApiResource {
  id: number
  city?: string | null
  region?: string | null
  address?: string | null
  cityCode?: string | null
}

export class PvzPointsRepository extends BaseRepository<PvzPoint> {
  constructor() {
    super('/admin/pvz-points')
  }
}


