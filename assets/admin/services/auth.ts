import { httpClient } from './http';

export async function login(name: string, password: string): Promise<string> {
	const response = await httpClient.post<{ token: string }>('/login', { name, password });
	const token = response.data.token;
	localStorage.setItem('token', token);
	return token;
}

export function logout(): void {
	localStorage.removeItem('token');
}

export function getToken(): string | null {
	return localStorage.getItem('token');
}


