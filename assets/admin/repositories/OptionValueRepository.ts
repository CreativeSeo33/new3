import type { ApiResource, HydraCollection } from '@admin/types/api';
import { BaseRepository } from './BaseRepository';
import { adminCache } from '@admin/utils/persistentCache';

export interface OptionValue extends ApiResource {
  id: number;
  value: string | null;
  sortOrder?: number | null;
  // Api Platform ожидает IRI для связи
  optionType?: string | null;
}

export class OptionValueRepository extends BaseRepository<OptionValue> {
  constructor() {
    // baseURL '/api' из HttpClient, поэтому ресурс без префикса '/api'
    super('/option_values');
  }

  // Долгосрочное кеширование списка значений опций (24 часа)
  async findAllCached(options: { itemsPerPage?: number } = {}): Promise<HydraCollection<OptionValue>> {
    const key = 'option_values:all';
    const version = 'v1'; // bump if server shape changes
    const ttl = 24 * 60 * 60 * 1000; // 24h
    const cached = adminCache.get<HydraCollection<OptionValue>>(key, version, ttl);
    if (cached) return cached;
    const data = await this.findAll({ itemsPerPage: options.itemsPerPage ?? 1000, sort: { sortOrder: 'asc', value: 'asc' } });
    adminCache.set(key, version, data);
    return data;
  }

  // Кешированная выборка значений по конкретной опции (24 часа)
  async findByOptionCached(optionIri: string, options: { itemsPerPage?: number } = {}): Promise<HydraCollection<OptionValue>> {
    const idPart = String(optionIri).split('/').pop() || String(optionIri)
    const key = `option_values:by_option:${idPart}`
    const version = 'v1'
    const ttl = 24 * 60 * 60 * 1000 // 24h
    const cached = adminCache.get<HydraCollection<OptionValue>>(key, version, ttl)
    if (cached) return cached
    const data = await this.findAll({
      itemsPerPage: options.itemsPerPage ?? 1000,
      sort: { sortOrder: 'asc', value: 'asc' },
      filters: { optionType: optionIri },
    })
    adminCache.set(key, version, data)
    return data
  }

  invalidatePersistentCache(): void {
    adminCache.clear('option_values:');
  }
}



