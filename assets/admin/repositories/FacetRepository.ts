import { BaseRepository } from './BaseRepository';
import type { ApiResource } from '@admin/types/api';

export interface FacetAvailableDto {
  categoryId: number | null;
  attributes: Array<{ code: string; name: string; type: string; min: number | null; max: number | null }>;
  options: Array<{ code: string; name: string; values: Array<{ code: string; label: string }> }>;
  price: { min: number | null; max: number | null };
  updatedAt: string;
}

export interface FacetConfigDto extends ApiResource {
  scope: 'CATEGORY' | 'GLOBAL';
  categoryId: number | null;
  attributes: Array<{ id?: number; code: string; label?: string | null; enabled: boolean; widget: 'checkbox' | 'range'; operator?: 'OR' | 'AND'; order?: number | null; bins?: number | [number, number][] }>;
  options: Array<{ id?: number; code: string; label?: string | null; enabled: boolean; widget: 'checkbox' | 'range'; order?: number | null; bins?: number | [number, number][] }>;
  showZeros: boolean;
  collapsedByDefault: boolean;
  valuesLimit: number;
  valuesSort: 'popularity' | 'alpha' | 'manual';
}

export class FacetRepository extends BaseRepository<FacetConfigDto> {
  constructor() { super('/facets/config'); }

  async getAvailable(categoryId: number | null): Promise<FacetAvailableDto> {
    const q = categoryId === null ? '' : `?category=${categoryId}`;
    const { data } = await this.http.getJson<FacetAvailableDto>(`/admin/facets/available${q}`);
    return data;
  }

  async getConfig(category: number | 'global'): Promise<FacetConfigDto> {
    const q = typeof category === 'number' ? `?category=${category}` : '?category=global';
    const { data } = await this.http.getJson<FacetConfigDto>(`/admin/facets/config${q}`);
    return data;
  }

  async saveConfig(payload: FacetConfigDto): Promise<{ status: string; id: number }> {
    const { data } = await this.http.putJson<{ status: string; id: number }>('/admin/facets/config', payload);
    return data;
  }

  async reindex(category: number | 'all', payload?: { attributes?: string[]; options?: string[] }): Promise<{ status: string; categoryId?: number }> {
    const q = typeof category === 'number' ? `?category=${category}` : '?category=all';
    const body = {
      attributes: payload?.attributes ?? [],
      options: payload?.options ?? [],
    };
    const { data } = await this.http.postJson<{ status: string; categoryId?: number }>(`/admin/facets/reindex${q}`, body);
    return data;
  }
}


