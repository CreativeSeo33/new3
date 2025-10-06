import { post } from '@shared/api/http';

export interface PasswordRequestPayload {
  email: string;
}

export interface PasswordConfirmPayload {
  email: string;
  token: string;
  password: string;
}

export async function passwordRequest(data: PasswordRequestPayload): Promise<{ status: string }> {
  return post<{ status: string }>(
    '/api/customer/auth/password/request',
    data,
  );
}

export async function passwordConfirm(data: PasswordConfirmPayload): Promise<{ status: string }> {
  return post<{ status: string }>(
    '/api/customer/auth/password/confirm',
    data,
  );
}


