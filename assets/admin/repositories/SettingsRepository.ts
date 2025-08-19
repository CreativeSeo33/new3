import type { ApiResource } from '@admin/types/api'
import { BaseRepository } from './BaseRepository'

export interface Setting extends ApiResource {
  id: number
  name: string | null
  value: string | null
}

export class SettingsRepository extends BaseRepository<Setting> {
  constructor() {
    // HttpClient already prefixes with /api
    super('/admin/settings')
  }
}


