import type { ApiResource } from '@admin/types/api';
import { BaseRepository } from './BaseRepository';

export interface City extends ApiResource {
  id: number;
  city: string | null;
  address?: string | null;
  postalCode?: string | null;
  region?: string | null;
  population?: number | null;
  kladrId?: string | number | null;
}

export class CityRepository extends BaseRepository<City> {
  constructor() {
    // baseURL '/api' из HttpClient, поэтому ресурс без префикса '/api'
    super('/cities');
  }
}




