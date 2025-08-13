import type { ApiResource } from '@admin/types/api';
import { BaseRepository } from './BaseRepository';

export interface ProductCategoryDto extends ApiResource {
  id?: number;
  product?: string; // IRI '/api/products/{id}'
  category?: string; // IRI '/api/categories/{id}'
  isParent?: boolean | null;
  position?: number | null;
  visibility?: boolean | null;
}

export class ProductCategoryRepository extends BaseRepository<ProductCategoryDto> {
  constructor() {
    super('/product_to_categories');
  }
}


