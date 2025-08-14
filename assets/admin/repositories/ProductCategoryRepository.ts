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

  // Для relations кэш лучше не использовать во избежание устаревших данных
  protected override getFromCache<TCached>(_url: string): Promise<TCached> | null {
    return null;
  }
}


