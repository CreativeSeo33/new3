import type { ApiResource, HydraCollection } from '@admin/types/api';
import { BaseRepository } from './BaseRepository';
import { adminCache } from '@admin/utils/persistentCache';

export interface Attribute extends ApiResource {
  id: number;
  name: string | null;
  sortOrder?: number | null;
  // Api Platform ожидает IRI для связи
  attributeGroup?: string | null;
}

export class AttributeRepository extends BaseRepository<Attribute> {
  constructor() {
    // baseURL '/api' из HttpClient, поэтому ресурс без префикса '/api'
    super('/attributes');
  }

  async findAllCached(): Promise<HydraCollection<Attribute>> {
    const key = 'attributes:all:sorted';
    const version = 'v1';
    const ttl = 24 * 60 * 60 * 1000; // 24h
    const cached = adminCache.get<HydraCollection<Attribute>>(key, version, ttl);
    if (cached) return cached;
    const data = await this.findAll({ itemsPerPage: 1000, sort: { sortOrder: 'asc', name: 'asc' } });
    adminCache.set(key, version, data);
    return data;
  }
}


