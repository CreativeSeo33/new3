import { httpClient, type HttpClient } from '@admin/services/http';
import type { ApiResource, CrudOptions, HydraCollection } from '@admin/types/api';

export abstract class BaseRepository<T extends ApiResource> {
  protected http: HttpClient;
  protected resourcePath: string;

  constructor(resourcePath: string, httpClientInstance: HttpClient = httpClient) {
    this.http = httpClientInstance;
    this.resourcePath = resourcePath;
  }

  protected buildQueryString(options: CrudOptions = {}): string {
    const params = new URLSearchParams();

    if (options.page) params.append('page', options.page.toString());
    if (options.itemsPerPage) params.append('itemsPerPage', options.itemsPerPage.toString());

    if (options.filters) {
      Object.entries(options.filters).forEach(([key, value]) => {
        if (value !== null && value !== undefined && value !== '') {
          params.append(key, String(value));
        }
      });
    }

    if (options.sort) {
      Object.entries(options.sort).forEach(([field, direction]) => {
        params.append(`order[${field}]`, direction);
      });
    }

    return params.toString();
  }

  async findAll(options: CrudOptions = {}): Promise<HydraCollection<T>> {
    const queryString = this.buildQueryString(options);
    const url = queryString ? `${this.resourcePath}?${queryString}` : this.resourcePath;
    const response = await this.http.get<HydraCollection<T>>(url);
    return response.data;
  }

  async findById(id: string | number): Promise<T> {
    const response = await this.http.get<T>(`${this.resourcePath}/${id}`);
    return response.data;
  }

  async create(data: Partial<T>): Promise<T> {
    const response = await this.http.post<T>(this.resourcePath, data);
    return response.data;
  }

  async update(id: string | number, data: Partial<T>): Promise<T> {
    const response = await this.http.put<T>(`${this.resourcePath}/${id}`, data);
    return response.data;
  }

  async partialUpdate(id: string | number, data: Partial<T>): Promise<T> {
    const response = await this.http.patch<T>(`${this.resourcePath}/${id}`, data, {
      headers: { 'Content-Type': 'application/merge-patch+json' },
    } as any);
    return response.data;
  }

  async delete(id: string | number): Promise<void> {
    await this.http.delete(`${this.resourcePath}/${id}`);
  }

  async bulkDelete(ids: Array<string | number>): Promise<void> {
    await Promise.all(ids.map((id) => this.delete(id)));
  }
}


