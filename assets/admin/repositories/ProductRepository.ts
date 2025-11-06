import type { ApiResource, HydraCollection } from '@admin/types/api';
import { BaseRepository } from './BaseRepository';

export interface ProductDto extends ApiResource {
  id?: number;
  code?: string | null;
  name: string | null;
  slug: string | null;
  price: number | null;
  salePrice?: number | null;
  effectivePrice?: number | null;
  status: boolean | null;
  quantity: number | null;
  sortOrder?: number | null;
  type?: 'simple' | 'variable' | 'variable_no_prices' | null;
  description?: string | null;
  metaTitle: string | null;
  metaDescription: string | null;
  metaKeywords?: string | null;
  h1: string | null;
  manufacturerId?: number | null;
  manufacturerName?: string | null;
  image?: Array<{ id: number; imageUrl: string; sortOrder: number }>;
  firstImageUrl?: string | null;
  createdAt?: string | null;
  categoryNames?: string[];
  optionsJson?: any[] | null;
  optionsCount?: number | null;
  optionAssignments?: Array<{
    option: string;
    optionLabel?: string | null;
    value: string | null;
    valueLabel?: string | null;
    height: number | null;
    bulbsCount: number | null;
    sku: string | null;
    originalSku?: string | null;
    price: number | null;
    setPrice?: boolean | null;
    salePrice?: number | null;
    lightingArea: number | null;
    sortOrder?: number | null;
    quantity?: number | null;
    attributes?: Record<string, any> | null;
  }> | null;
}

export class ProductRepository extends BaseRepository<ProductDto> {
  constructor() {
    // v2 ProductResource endpoints
    super('/v2/products');
  }

  async searchProducts(query: string, limit: number = 10): Promise<HydraCollection<ProductDto>> {
    const normalized = (query ?? '').trim();
    if (normalized.length < 3) {
      throw new Error('Минимальная длина запроса — 3 символа');
    }
    return this.findAll({
      filters: { name: normalized },
      itemsPerPage: limit,
    });
  }
}


