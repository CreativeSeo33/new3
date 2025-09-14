import type { ApiResource } from '@admin/types/api'
import { BaseRepository } from './BaseRepository'

export interface PvzPrice extends ApiResource {
  id: number
  city: string
  srok?: string | null
  cost?: number | null
  free?: number | null
  cityId?: number | null
}

export class PvzPriceRepository extends BaseRepository<PvzPrice> {
  constructor() {
    // Используем admin uriTemplate для коллекции
    super('/admin/pvz-prices')
  }
}


