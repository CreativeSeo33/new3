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

  async syncForProduct(productIri: string, desiredCategoryIds: number[], desiredMainId: number | null): Promise<void> {
    const all = await this.findAll({ itemsPerPage: 1000, filters: { product: productIri } }) as any
    const rels = (all['hydra:member'] ?? all.member ?? []) as any[]
    const byCategory = new Map<number, any>()
    for (const r of rels) {
      const cid = Number(r.category?.split('/').pop() || (r as any).categoryId || 0)
      if (cid) byCategory.set(cid, r)
    }

    const desired = new Set<number>(desiredCategoryIds ?? [])
    const deletes: Promise<any>[] = []
    const updates: Promise<any>[] = []
    const creates: Promise<any>[] = []

    for (const [cid, r] of byCategory.entries()) {
      if (!desired.has(cid) && r?.id) {
        deletes.push(this.delete(r.id))
      }
    }

    for (const [cid, r] of byCategory.entries()) {
      if (desired.has(cid) && r?.id) {
        const shouldBeParent = desiredMainId != null && cid === desiredMainId
        if ((r.isParent ?? false) !== shouldBeParent) {
          updates.push(this.partialUpdate(r.id, { isParent: shouldBeParent }))
        }
      }
    }

    for (const cid of desired.values()) {
      if (!byCategory.has(cid)) {
        creates.push(this.create({
          product: productIri,
          category: `/api/categories/${cid}`,
          visibility: true,
          isParent: desiredMainId != null && cid === desiredMainId,
        }))
      }
    }

    await Promise.all([
      Promise.allSettled(deletes),
      Promise.allSettled(updates),
      Promise.allSettled(creates),
    ])
  }
}


