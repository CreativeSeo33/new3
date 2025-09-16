import { get, post } from '@shared/api/http';

export interface DeliveryTypeDto {
  id?: number;
  code: string;
  name: string;
  active?: boolean;
  default?: boolean;
  isDefault?: boolean; // на случай иного поля
}

export interface CartFullPayload {
  currency: string;
  subtotal: number;
  total: number;
  shipping?: { method?: string | null; cost?: number | null };
}

export async function fetchDeliveryContext(): Promise<Record<string, any>> {
  return get<Record<string, any>>('/api/delivery/context');
}

export async function fetchDeliveryTypes(): Promise<DeliveryTypeDto[]> {
  const res = await get<any>('/api/delivery_types');
  if (Array.isArray(res)) return res as DeliveryTypeDto[];
  if (Array.isArray(res?.['hydra:member'])) return res['hydra:member'] as DeliveryTypeDto[];
  return [];
}

export async function selectDeliveryMethod(methodCode: string): Promise<CartFullPayload> {
  return post<CartFullPayload>('/api/delivery/select-method', { methodCode }, { showCartSpinner: true });
}


