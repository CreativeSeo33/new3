// ai:http-client usage
import { get } from '@shared/api/http';

export interface FacetValue {
  code: string;
  label: string;
  count: number;
}

export interface FacetResponse {
  facets: Record<string, { type: 'option' | 'attribute' | 'range'; values?: FacetValue[]; min?: number | null; max?: number | null }>;
}

export async function getFacets(params: { category: number; filters?: Record<string, string | number | boolean> } ): Promise<FacetResponse> {
  const q = new URLSearchParams();
  q.set('category', String(params.category));
  if (params.filters) {
    for (const [k, v] of Object.entries(params.filters)) q.set(k, String(v));
  }
  return get<FacetResponse>(`/api/catalog/facets?${q.toString()}`);
}


