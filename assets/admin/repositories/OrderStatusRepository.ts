import type { ApiResource, HydraCollection } from '@admin/types/api';
import { BaseRepository } from './BaseRepository';
import { adminCache } from '@admin/utils/persistentCache';

export interface OrderStatusDto extends ApiResource {
	id: number;
	name: string;
	sort: number;
}

export class OrderStatusRepository extends BaseRepository<OrderStatusDto> {
  constructor() {
    // Используем стандартный ресурс без admin‑префикса, чтобы не трогать stateful firewall
    super('/order_statuses');
  }

  // Редко меняется — кэшируем на 24 часа в persistent‑кэше
  async findAllCached(): Promise<HydraCollection<OrderStatusDto>> {
    const key = 'order-statuses:all';
    const version = 'v1';
    const ttl = 24 * 60 * 60 * 1000;
    const cached = adminCache.get<HydraCollection<OrderStatusDto>>(key, version, ttl);
    if (cached) return cached;
    const data = await this.findAll({ itemsPerPage: 1000, sort: { sort: 'asc', name: 'asc' } });
    adminCache.set(key, version, data);
    return data;
  }

  invalidatePersistentCache(): void {
    adminCache.clear('order-statuses:');
  }
}



