import type { ApiResource } from '@admin/types/api';
import { BaseRepository } from './BaseRepository';

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
}


