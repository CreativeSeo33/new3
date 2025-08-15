import type { ApiResource } from '@admin/types/api';
import { BaseRepository } from './BaseRepository';

export interface DeliveryType extends ApiResource {
  id: number;
  name: string;
  code: string;
  active: boolean;
  sortOrder: number;
  'default': boolean;
}

export class DeliveryTypeRepository extends BaseRepository<DeliveryType> {
  constructor() {
    super('/delivery_types');
  }
}


