import type { ApiResource } from '@admin/types/api';
import { BaseRepository } from './BaseRepository';

export interface OptionValue extends ApiResource {
  id: number;
  value: string | null;
  sortOrder?: number | null;
  // Api Platform ожидает IRI для связи
  optionType?: string | null;
}

export class OptionValueRepository extends BaseRepository<OptionValue> {
  constructor() {
    // baseURL '/api' из HttpClient, поэтому ресурс без префикса '/api'
    super('/option_values');
  }
}



