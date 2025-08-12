import type { ApiResource } from '@admin/types/api';
import { BaseRepository } from './BaseRepository';

export interface Attribute extends ApiResource {
  id: number;
  name: string | null;
}

export class AttributeRepository extends BaseRepository<Attribute> {
  constructor() {
    // baseURL '/api' из HttpClient, поэтому ресурс без префикса '/api'
    super('/attributes');
  }
}


