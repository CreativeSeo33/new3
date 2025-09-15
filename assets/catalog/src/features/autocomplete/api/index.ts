import { get } from '@shared/api/http';

export interface FiasItem {
  id: number;
  offname?: string;
  shortname?: string;
  level?: number;
}

export interface SuggestionItem<T = any> {
  id?: string | number;
  label: string;
  value: string;
  raw?: T;
}

function normalizeHydra<T = any>(data: any): T[] {
  if (Array.isArray(data)) return data as T[];
  if (data && Array.isArray(data['hydra:member'])) return data['hydra:member'] as T[];
  if (data && Array.isArray(data.items)) return data.items as T[];
  return [] as T[];
}

export async function fetchFiasCities(
  query: string,
  options: { limit?: number; shortname?: string; level?: number; signal?: AbortSignal } = {}
): Promise<SuggestionItem<FiasItem>[]> {
  const { limit = 10, shortname = 'г.', level, signal } = options;
  const params = new URLSearchParams();
  params.set('offname', query);
  params.set('shortname', shortname);
  if (typeof level === 'number') {
    params.set('level', String(level));
  }
  params.set('itemsPerPage', String(limit));

  const data = await get<any>(`/api/fias?${params.toString()}`, { headers: { Accept: 'application/json' }, signal: signal as any } as any);
  const list = normalizeHydra<FiasItem>(data);
  return list.map((it) => ({
    id: (it as any).id,
    label: [it.offname, it.shortname].filter(Boolean).join(' '),
    value: it.offname || '',
    raw: it
  }));
}


