export interface ApiResource {
  '@id'?: string;
  '@type'?: string;
  id?: string | number;
  [key: string]: any;
}

export interface HydraView {
  '@id': string;
  '@type': string;
  'hydra:first'?: string;
  'hydra:last'?: string;
  'hydra:next'?: string;
  'hydra:previous'?: string;
}

export interface HydraCollection<T = any> {
  '@context': string;
  '@id': string;
  '@type': 'hydra:Collection';
  'hydra:member': T[];
  'hydra:totalItems': number;
  'hydra:view'?: HydraView;
}

export interface CrudOptions {
  page?: number;
  itemsPerPage?: number;
  filters?: Record<string, any>;
  sort?: Record<string, 'asc' | 'desc'>;
}

export interface CrudPaginationState {
  page: number;
  itemsPerPage: number;
  totalPages: number;
}

export interface CrudState<T> {
  items: T[];
  item: T | null;
  totalItems: number;
  loading: boolean;
  error: string | null;
  pagination: CrudPaginationState;
}


