import type { ApiResource, HydraCollection } from '@admin/types/api';
import { BaseRepository } from './BaseRepository';
import { adminCache } from '@admin/utils/persistentCache';

export interface DeliveryType extends ApiResource {
  id: number;
  name: string;
  code: string;
  active: boolean;
  sortOrder: number;
  'default': boolean;
}

export class DeliveryTypeRepository extends BaseRepository<DeliveryType> {
  constructor() {
    super('/delivery_types');
  }

  // Редко меняется — кешируем на 24 часа в persistent‑кэше
  async findAllCached(): Promise<HydraCollection<DeliveryType>> {
    const key = 'delivery-types:all';
    const version = 'v1';
    const ttl = 24 * 60 * 60 * 1000;
    const cached = adminCache.get<HydraCollection<DeliveryType>>(key, version, ttl);
    if (cached) return cached;
    const data = await this.findAll({ itemsPerPage: 1000, sort: { sortOrder: 'asc', name: 'asc' } });
    adminCache.set(key, version, data);
    return data;
  }
}


