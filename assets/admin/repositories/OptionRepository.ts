import type { ApiResource } from '@admin/types/api';
import { BaseRepository } from './BaseRepository';

export interface Option extends ApiResource {
  id: number;
  name: string | null;
  sortOrder?: number | null;
  code?: string | null;
}

export class OptionRepository extends BaseRepository<Option> {
  constructor() {
    // baseURL '/api' из HttpClient, поэтому ресурс без префикса '/api'
    super('/options');
  }
}



