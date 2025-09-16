import { get, post } from '@shared/api/http';

export type DeliveryMethodCode = 'pvz' | 'courier';

export interface DeliveryContextDto {
  methodCode?: DeliveryMethodCode | null;
  cityName?: string | null;
}

export interface SelectCityResponseDto {
  total?: number | null;
}

export interface SelectMethodRequestDto {
  methodCode: DeliveryMethodCode;
  address?: string;
}

export interface SelectMethodResponseDto {
  shippingCost: number; // in cents
  total: number; // in cents
}

export interface PvzPointDto {
  code: string;
  name?: string | null;
  address?: string | null;
}

export async function getDeliveryContext(): Promise<DeliveryContextDto> {
  return get<DeliveryContextDto>('/api/delivery/context', {
    headers: { Accept: 'application/json' },
  });
}

export async function fetchPvzPoints(cityName: string): Promise<PvzPointDto[]> {
  if (!cityName) return [];
  const params = new URLSearchParams();
  params.set('city', cityName);
  return get<PvzPointDto[]>(`/api/delivery/pvz-points?${params.toString()}`, {
    headers: { Accept: 'application/json' },
  });
}

export async function selectCity(cityName: string, cityId?: number | null): Promise<SelectCityResponseDto> {
  return post<SelectCityResponseDto>(
    '/api/delivery/select-city',
    { cityName, cityId },
    { headers: { Accept: 'application/json' } }
  );
}

export async function selectMethod(payload: SelectMethodRequestDto): Promise<SelectMethodResponseDto> {
  return post<SelectMethodResponseDto>(
    '/api/delivery/select-method',
    payload,
    { headers: { Accept: 'application/json' } }
  );
}

export async function selectPvz(pvzCode: string): Promise<SelectMethodResponseDto> {
  return post<SelectMethodResponseDto>(
    '/api/delivery/select-pvz',
    { pvzCode },
    { headers: { Accept: 'application/json' } }
  );
}


