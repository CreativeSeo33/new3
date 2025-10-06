import { post } from '@shared/api/http';

export interface LoginRequest {
  email: string;
  password: string;
}

export interface LoginResponse {
  user?: {
    id: number;
    email: string;
    roles: string[];
    isVerified?: boolean;
  };
  status?: string;
}

export async function login(data: LoginRequest): Promise<LoginResponse> {
  // Сервер устанавливает httpOnly cookies (__Host-acc/__Host-ref) и отдаёт JSON
  return post<LoginResponse>('/api/customer/auth/login', data);
}


