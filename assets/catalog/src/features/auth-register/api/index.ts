import { post } from '@shared/api/http';

export interface RegisterRequest {
  email: string;
  password: string;
}

export interface RegisterResponse {
  status: string;
}

export async function register(data: RegisterRequest): Promise<RegisterResponse> {
  // Сервер возвращает общий ответ без утечки конкретики
  return post<RegisterResponse>('/api/customer/auth/register', data);
}


