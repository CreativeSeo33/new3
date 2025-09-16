import { post } from '@shared/api/http';

export interface CheckoutPayload {
  firstName?: string;
  phone?: string;
  email?: string;
  comment?: string;
  [key: string]: FormDataEntryValue | undefined;
}

export interface CheckoutResponse {
  redirectUrl?: string;
  error?: string;
}

export async function submitCheckout(
  submitUrl: string,
  data: Record<string, any>
): Promise<CheckoutResponse> {
  return post<CheckoutResponse>(submitUrl, data);
}


