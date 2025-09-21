import axios, { type AxiosInstance, type AxiosRequestConfig, type AxiosResponse } from 'axios';
import { uiLoading } from '../shared/uiLoading'

function isStreamRequest(cfg: AxiosRequestConfig): boolean {
  const headers: any = cfg.headers as any
  let accept: string | undefined
  if (headers) {
    if (typeof headers.get === 'function') {
      accept = headers.get('Accept') || headers.get('accept')
    } else {
      accept = headers['Accept'] || headers['accept']
    }
  }
  const url = cfg.url || ''
  return (
    accept === 'text/event-stream' ||
    (cfg as any).responseType === 'stream' ||
    /\/(\.well-known\/mercure|hub)(\?|$)/.test(url || '')
  )
}

export class HttpClient {
  private instance: AxiosInstance;

  constructor(baseURL: string = '/api') {
    // baseURL из env при наличии, иначе '/api'
    let resolvedBaseURL = baseURL
    try {
      const env = (import.meta as any)?.env
      if (env?.VITE_API_URL) resolvedBaseURL = env.VITE_API_URL as string
    } catch {}

    this.instance = axios.create({
      baseURL: resolvedBaseURL,
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/ld+json',
      },
    });

    this.setupInterceptors();
  }

  private setupInterceptors(): void {
    this.instance.interceptors.request.use(
      (config) => {
        const token = localStorage.getItem('token');
        if (token) {
          if (config.headers && typeof (config.headers as any).set === 'function') {
            (config.headers as any).set('Authorization', `Bearer ${token}`);
          } else {
            (config.headers ||= {} as any).Authorization = `Bearer ${token}`;
          }
        }
        if (!isStreamRequest(config)) uiLoading.startGlobalLoading()
        return config;
      },
      (error) => Promise.reject(error),
    );

    this.instance.interceptors.response.use(
      (response) => {
        if (!isStreamRequest(response.config)) uiLoading.stopGlobalLoading()
        return response
      },
      (error) => {
        if (error?.config && !isStreamRequest(error.config)) {
          // По умолчанию при ошибке спиннер оставляем видимым, но для 409 — скрываем
          if (error?.response?.status === 409) {
            uiLoading.stopGlobalLoading()
          }
        }
        if (error.response?.status === 401) {
          this.handleUnauthorized();
        }
        return Promise.reject(this.normalizeError(error));
      },
    );
  }

  private handleUnauthorized(): void {
    localStorage.removeItem('token');
    // перенаправление при необходимости
    // window.location.href = '/login';
  }

  private normalizeError(error: any): Error {
    if (error.response?.data?.['hydra:description']) {
      return new Error(error.response.data['hydra:description']);
    }
    if (typeof error.response?.data === 'string') {
      return new Error(error.response.data);
    }
    return new Error(error.message || 'Network error');
  }

  async get<T>(url: string, config?: AxiosRequestConfig): Promise<AxiosResponse<T>> {
    return this.instance.get(url, config);
  }

  async post<T>(url: string, data?: any, config?: AxiosRequestConfig): Promise<AxiosResponse<T>> {
    return this.instance.post(url, data, config);
  }

  async put<T>(url: string, data?: any, config?: AxiosRequestConfig): Promise<AxiosResponse<T>> {
    return this.instance.put(url, data, config);
  }

  async patch<T>(url: string, data?: any, config?: AxiosRequestConfig): Promise<AxiosResponse<T>> {
    return this.instance.patch(url, data, config);
  }

  async delete<T>(url: string, config?: AxiosRequestConfig): Promise<AxiosResponse<T>> {
    return this.instance.delete(url, config);
  }

  // Convenience helpers for endpoints returning plain JSON (non-Hydra)
  private withAccept(config: AxiosRequestConfig | undefined, accept: string): AxiosRequestConfig {
    const headers: any = (config?.headers && typeof (config.headers as any).set !== 'function')
      ? { ...(config?.headers as any) }
      : {} as any
    headers['Accept'] = accept
    const next: AxiosRequestConfig = { ...(config || {}), headers }
    return next
  }

  async getJson<T>(url: string, config?: AxiosRequestConfig): Promise<AxiosResponse<T>> {
    return this.instance.get(url, this.withAccept(config, 'application/json'))
  }

  async postJson<T>(url: string, data?: any, config?: AxiosRequestConfig): Promise<AxiosResponse<T>> {
    return this.instance.post(url, data, this.withAccept(config, 'application/json'))
  }

  async putJson<T>(url: string, data?: any, config?: AxiosRequestConfig): Promise<AxiosResponse<T>> {
    return this.instance.put(url, data, this.withAccept(config, 'application/json'))
  }

  async patchJson<T>(url: string, data?: any, config?: AxiosRequestConfig): Promise<AxiosResponse<T>> {
    return this.instance.patch(url, data, this.withAccept(config, 'application/json'))
  }

  async deleteJson<T>(url: string, config?: AxiosRequestConfig): Promise<AxiosResponse<T>> {
    return this.instance.delete(url, this.withAccept(config, 'application/json'))
  }
}

export const httpClient = new HttpClient('/api');


