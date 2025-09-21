import type { ApiResource, HydraCollection } from '@admin/types/api';
import { BaseRepository } from './BaseRepository';
import { adminCache } from '@admin/utils/persistentCache';

export interface CategoryDto extends ApiResource {
  id?: number;
  name: string | null;
  slug: string | null;
  visibility: boolean | null;
  parentCategoryId: number | null;
  metaTitle: string | null;
  metaDescription: string | null;
  metaKeywords: string | null;
  sortOrder: number | null;
  metaH1: string | null;
  description: string | null;
  navbarVisibility?: boolean | null;
  footerVisibility?: boolean | null;
}

export class CategoryRepository extends BaseRepository<CategoryDto> {
  constructor() {
    // Category endpoints (default Api Platform collection)
    super('/categories');
  }

  // Long-term cached fetch for full list used by trees etc.
  async findAllCached(options: { itemsPerPage?: number } = {}): Promise<HydraCollection<CategoryDto>> {
    const key = 'categories:all';
    const version = 'v1'; // bump if server shape changes
    const ttl = 24 * 60 * 60 * 1000; // 24h
    const cached = adminCache.get<HydraCollection<CategoryDto>>(key, version, ttl);
    if (cached) return cached;
    const data = await this.findAll({ itemsPerPage: options.itemsPerPage ?? 1000 });
    adminCache.set(key, version, data);
    return data;
  }

  invalidatePersistentCache(): void {
    adminCache.clear('categories:');
  }
}


