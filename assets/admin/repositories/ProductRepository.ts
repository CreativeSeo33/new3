import type { ApiResource } from '@admin/types/api';
import { BaseRepository } from './BaseRepository';

export interface ProductDto extends ApiResource {
  id?: number;
  name: string | null;
  slug: string | null;
  price: number | null;
  salePrice?: number | null;
  effectivePrice?: number | null;
  status: boolean | null;
  quantity: number | null;
  sortOrder?: number | null;
  description?: string | null;
  metaTitle: string | null;
  metaDescription: string | null;
  metaKeywords?: string | null;
  h1: string | null;
  manufacturerId?: number | null;
  manufacturerName?: string | null;
}

export class ProductRepository extends BaseRepository<ProductDto> {
  constructor() {
    // v2 ProductResource endpoints
    super('/v2/products');
  }
}


