import { httpClient, type HttpClient } from '@admin/services/http';
import type { ApiResource, CrudOptions, HydraCollection } from '@admin/types/api';

// Simple in-memory GET cache with TTL and request de-duplication
const GET_CACHE = new Map<string, { expiresAt: number; promise: Promise<any> }>();
const DEFAULT_TTL_MS = 60_000; // 60s
const CONFIG_TTL_MS = 24 * 60_000; // 24 min default for config; will be overridden by headers anyway

export abstract class BaseRepository<T extends ApiResource> {
  protected http: HttpClient;
  protected resourcePath: string;

  constructor(resourcePath: string, httpClientInstance: HttpClient = httpClient) {
    this.http = httpClientInstance;
    this.resourcePath = resourcePath;
  }

  // Global cache invalidation helper for external mutations
  static invalidateCachePrefix(prefix: string): void {
    const absPrefix = prefix.startsWith('/') ? prefix : `/${prefix}`;
    for (const key of GET_CACHE.keys()) {
      if (key.startsWith(absPrefix)) GET_CACHE.delete(key);
    }
  }

  protected buildAbsoluteUrl(relative: string): string {
    // Ensures consistent cache keys
    return relative.startsWith('/') ? relative : `/${relative}`;
  }

  protected getFromCache<TCached>(url: string): Promise<TCached> | null {
    const absolute = this.buildAbsoluteUrl(url);
    const entry = GET_CACHE.get(absolute);
    if (!entry) return null;
    if (Date.now() > entry.expiresAt) {
      GET_CACHE.delete(absolute);
      return null;
    }
    return entry.promise as Promise<TCached>;
  }

  protected setCache(url: string, promise: Promise<any>, ttlMs: number = DEFAULT_TTL_MS): void {
    const absolute = this.buildAbsoluteUrl(url);
    GET_CACHE.set(absolute, { expiresAt: Date.now() + ttlMs, promise });
  }

  protected invalidateCache(prefix: string = this.resourcePath): void {
    const absPrefix = this.buildAbsoluteUrl(prefix);
    for (const key of GET_CACHE.keys()) {
      if (key.startsWith(absPrefix)) GET_CACHE.delete(key);
    }
  }

  // Hook for subclasses to invalidate persistent caches (e.g., localStorage-based caches)
  // Default is no-op; repositories using adminCache should override this.
  // Called after any mutation (create/update/patch/delete/bulkDelete).
  // eslint-disable-next-line @typescript-eslint/no-empty-function
  protected invalidatePersistentCache(): void {}

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
    const cached = this.getFromCache<HydraCollection<T>>(url);
    if (cached) return cached;
    const promise = this.http
      .get<HydraCollection<T>>(url)
      .then((r) => r.data)
      .catch((e) => {
        // drop failed entries to allow retry
        GET_CACHE.delete(this.buildAbsoluteUrl(url));
        throw e;
      });
    this.setCache(url, promise);
    return promise;
  }

  // Lightweight GET for arbitrary endpoints with local cache (used for config)
  async getCached<R = any>(relativeUrl: string, ttlMs: number = CONFIG_TTL_MS): Promise<R> {
    const cached = this.getFromCache<R>(relativeUrl);
    if (cached) return cached;
    const abs = this.buildAbsoluteUrl(relativeUrl);
    const promise = this.http
      .getJson<R>(relativeUrl)
      .then((r) => r.data as R)
      .catch((e) => {
        GET_CACHE.delete(abs);
        throw e;
      });
    this.setCache(relativeUrl, promise, ttlMs);
    return promise;
  }

  async findById(id: string | number): Promise<T> {
    const url = `${this.resourcePath}/${id}`;
    const cached = this.getFromCache<T>(url);
    if (cached) return cached;
    const promise = this.http
      .get<T>(url)
      .then((r) => r.data)
      .catch((e) => {
        GET_CACHE.delete(this.buildAbsoluteUrl(url));
        throw e;
      });
    this.setCache(url, promise);
    return promise;
  }

  async create(data: Partial<T>): Promise<T> {
    const response = await this.http.post<T>(this.resourcePath, data);
    this.invalidateCache();
    this.invalidatePersistentCache();
    return response.data;
  }

  async update(id: string | number, data: Partial<T>): Promise<T> {
    const response = await this.http.put<T>(`${this.resourcePath}/${id}`, data);
    this.invalidateCache();
    this.invalidatePersistentCache();
    return response.data;
  }

  async partialUpdate(id: string | number, data: Partial<T>): Promise<T> {
    const response = await this.http.patch<T>(`${this.resourcePath}/${id}`, data, {
      headers: { 'Content-Type': 'application/merge-patch+json' },
    } as any);
    this.invalidateCache();
    this.invalidatePersistentCache();
    return response.data;
  }

  async delete(id: string | number): Promise<void> {
    await this.http.delete(`${this.resourcePath}/${id}`);
    this.invalidateCache();
    this.invalidatePersistentCache();
  }

  async bulkDelete(ids: Array<string | number>): Promise<void> {
    await Promise.all(ids.map((id) => this.delete(id)));
    this.invalidateCache();
    this.invalidatePersistentCache();
  }
}


