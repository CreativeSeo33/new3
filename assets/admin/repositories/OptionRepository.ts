import type { ApiResource, HydraCollection } from '@admin/types/api';
import { BaseRepository } from './BaseRepository';
import { adminCache } from '@admin/utils/persistentCache';

export interface Option extends ApiResource {
  id: number;
  name: string | null;
  sortOrder?: number | null;
  code?: string | null;
}

export class OptionRepository extends BaseRepository<Option> {
  constructor() {
    // baseURL '/api' из HttpClient, поэтому ресурс без префикса '/api'
    super('/options');
  }

  // Долгосрочное кеширование списка опций (24 часа)
  async findAllCached(options: { itemsPerPage?: number } = {}): Promise<HydraCollection<Option>> {
    const key = 'options:all';
    const version = 'v1'; // bump if server shape changes
    const ttl = 24 * 60 * 60 * 1000; // 24h
    const cached = adminCache.get<HydraCollection<Option>>(key, version, ttl);
    if (cached) return cached;
    const data = await this.findAll({ itemsPerPage: options.itemsPerPage ?? 500, sort: { sortOrder: 'asc', name: 'asc' } });
    adminCache.set(key, version, data);
    return data;
  }

  invalidatePersistentCache(): void {
    adminCache.clear('options:');
  }
}



