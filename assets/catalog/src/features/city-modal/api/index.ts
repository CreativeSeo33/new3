import { get, post } from '@shared/api/http';

export interface CityItem {
  id?: number;
  name: string;
  // KLADR код из бекенда (CityModal.fiasId): строка до 13 символов
  fiasId?: string;
}

export interface HydraCollection<T> {
  'hydra:member'?: T[];
  member?: T[];
}

export async function fetchCities(): Promise<CityItem[]> {
  const data = await get<HydraCollection<CityItem>>('/api/city_modals?order[sort]=asc', {
    headers: { 'Accept': 'application/ld+json' }
  });
  return (data.member || (data as any)['hydra:member'] || []) as CityItem[];
}

export async function selectCity(item: CityItem, extraHeaders: Record<string, string> = {}): Promise<void> {
  // Бэкенд поддерживает CartWriteGuard в мягком режиме, но пробросим If-Match при наличии
  await post('/api/delivery/select-city', {
    cityName: item.name
  }, {
    headers: { 'Accept': 'application/json', ...extraHeaders }
  });
}


