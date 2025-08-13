import type { ApiResource } from '@admin/types/api';
import { BaseRepository } from './BaseRepository';

export interface User extends ApiResource {
  id: number;
  name: string | null;
  roles: string[];
  plainPassword?: string;
}

export class UserRepository extends BaseRepository<User> {
  constructor() {
    // baseURL '/api' из HttpClient, поэтому ресурс без префикса '/api'
    super('/users');
  }
}



