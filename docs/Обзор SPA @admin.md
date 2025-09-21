### Обзор SPA `@admin`
- **Роутер**: `createWebHistory('/admin')`, ленивые импорты, layout с хедером/сайдбаром и слотами.
- **Глобальный лоадер**: оверлей в `App.vue` подписан на `uiLoading.isGlobalLoading`; включается/выключается из axios‑перехватчиков.

```25:41:assets/admin/services/http.ts
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
```

```45:56:assets/admin/services/http.ts
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
```

```61:71:assets/admin/services/http.ts
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
```

### CRUD‑слой
- **Паттерн Repository + composable**: каждый ресурс имеет репозиторий; UI использует `useCrud<T>` для списка/деталей/создания/изменения/удаления с Hydra‑совместимой распаковкой и локальной пагинацией.

```31:68:assets/admin/composables/useCrud.ts
  const fetchAll = async (options: CrudOptions = {}) => {
    try {
      setLoading(true);
      setError(null);
      // Determine effective pagination for this request
      const effectivePage = options.page ?? state.pagination.page;
      const effectiveItemsPerPage = options.itemsPerPage ?? state.pagination.itemsPerPage;

      const response = (await repository.findAll({
        page: effectivePage,
        itemsPerPage: effectiveItemsPerPage,
        ...options,
      })) as unknown as any;

      const items: T[] = Array.isArray(response)
        ? (response as T[])
        : ((response?.['hydra:member'] ?? response?.member ?? []) as T[]);
      const totalItems: number = Array.isArray(response)
        ? items.length
        : Number(
            response?.['hydra:totalItems'] ??
            response?.totalItems ??
            items.length,
          );

      state.items = items;
      state.totalItems = totalItems;
      // Update current pagination first, then compute total pages using effective itemsPerPage
      state.pagination.page = effectivePage;
      state.pagination.itemsPerPage = effectiveItemsPerPage;
      state.pagination.totalPages = Math.max(1, Math.ceil((state.totalItems || 0) / (state.pagination.itemsPerPage || 10)));
```

```117:135:assets/admin/composables/useCrud.ts
  async partialUpdate(id: string | number, data: Partial<T>): Promise<T> {
    const response = await this.http.patch<T>(`${this.resourcePath}/${id}`, data, {
      headers: { 'Content-Type': 'application/merge-patch+json' },
    } as any);
    this.invalidateCache();
    return response.data;
  }
```

- **Сохранение форм (пример Product)**: нормализация чисел, частичный PATCH на `/v2/products`, разбор валидационных ошибок 400/422.

```16:35:assets/admin/composables/useProductSave.ts
  const saveProduct = async (id: string, data: ProductFormModel, opts: SaveOpts = {}) => {
    saving.value = true
    error.value = null
    try {
      const payload: Partial<ProductDto> = {
        name: data.name || null,
        slug: data.slug || null,
        price: toInt(data.price),
        salePrice: toInt((data as any).salePrice),
        status: data.status ?? null,
        quantity: toInt(data.quantity),
        sortOrder: data.sortOrder ?? null,
        type: data.type || null,
        description: (data as any).description ?? null,
        metaTitle: data.metaTitle || null,
        metaDescription: data.metaDescription || null,
        h1: data.h1 || null,
        optionsJson: (data as any).optionsJson ?? null,
        optionAssignments: Array.isArray((data as any).optionAssignments)
```

```61:70:assets/admin/composables/useProductSave.ts
    } catch (err: any) {
      const status = err?.response?.status
      const violations = err?.response?.data?.violations || err?.response?.data?.detail?.violations || []
      if ((status === 400 || status === 422) && Array.isArray(violations) && violations.length && opts.onValidationError) {
        opts.onValidationError(violations as Violation[])
      }
      error.value = err instanceof Error ? err.message : 'Ошибка сохранения'
      return { success: false, error: error.value }
    } finally {
```

- **Дельта‑сохранение привязок опций**: компаратор снимков + POST на `/api/v2/products/{id}/options`.

```305:323:assets/admin/composables/useOptionAssignments.ts
  async function saveDelta(productId: string): Promise<{ success: boolean; message?: string }> {
    if (!productId || productId === 'new') return { success: false, message: 'Сначала сохраните товар' }
    const { removed, upsert } = getDelta()
    try {
      const res = await fetch(`/api/v2/products/${productId}/options`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({
          upsert,
          remove: removed.map(r => ({ option: r.option, value: r.value, sku: r.sku })),
        }),
      })
```

### Доступ к API
- **HttpClient (axios)**: базовый URL из `VITE_API_URL` или `/api`; `Authorization: Bearer` из `localStorage`; Accept `application/ld+json`; нормализация ошибок Hydra; исключение лоадера для SSE.

```87:95:assets/admin/services/http.ts
  private normalizeError(error: any): Error {
    if (error.response?.data?.['hydra:description']) {
      return new Error(error.response.data['hydra:description']);
    }
    if (typeof error.response?.data === 'string') {
      return new Error(error.response.data);
    }
    return new Error(error.message || 'Network error');
  }
```

- **Аутентификация**: простой login → токен в `localStorage`; интерцептор подхватывает.

```3:8:assets/admin/services/auth.ts
export async function login(name: string, password: string): Promise<string> {
	const response = await httpClient.post<{ token: string }>('/login', { name, password });
	const token = response.data.token;
	localStorage.setItem('token', token);
	return token;
}
```

- **Fetch вне axios**: часть админ‑эндпоинтов дергается напрямую (медиа, конфиг), спиннер при этом не активируется.

```165:176:assets/admin/components/forms/ProductPhotos.vue
async function fetchFolderTree() {
  const res = await fetch('/api/admin/media/tree', { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
  if (!res.ok) throw new Error('Не удалось получить дерево каталогов')
  const ct = res.headers.get('content-type') || ''
  if (!ct.includes('application/json')) {
    const txt = await res.text()
    throw new Error('Получен не-JSON ответ. Возможно, требуется авторизация или произошла ошибка. ' + txt.slice(0, 120))
  }
```

### Кэширование
- **In‑memory TTL кэш (на запрос)**: единый Map в `BaseRepository` с TTL (по умолчанию 60s), ключи — абсолютные URL; инвалидация при мутациях.

```4:12:assets/admin/repositories/BaseRepository.ts
// Simple in-memory GET cache with TTL and request de-duplication
const GET_CACHE = new Map<string, { expiresAt: number; promise: Promise<any> }>();
const DEFAULT_TTL_MS = 60_000; // 60s
const CONFIG_TTL_MS = 24 * 60_000; // 24 min default for config; will be overridden by headers anyway
```

```69:84:assets/admin/repositories/BaseRepository.ts
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
```

```117:141:assets/admin/repositories/BaseRepository.ts
  async create(data: Partial<T>): Promise<T> {
    const response = await this.http.post<T>(this.resourcePath, data);
    this.invalidateCache();
    return response.data;
  }
  ...
  async delete(id: string | number): Promise<void> {
    await this.http.delete(`${this.resourcePath}/${id}`);
    this.invalidateCache();
  }
```

- **Persistent кэш (24h) в `localStorage`**: для справочников и «тяжелых» коллекций через `adminCache` с версионированием.

```28:37:assets/admin/utils/persistentCache.ts
  get<T>(key: string, version: string, maxAgeMs: number, serializer: CacheSerializer<T> = defaultSerializer): T | null {
    try {
      const raw = localStorage.getItem(this.k(key))
      if (!raw) return null
      const parsed = JSON.parse(raw) as StoredPayload
      if (!parsed || typeof parsed !== 'object') return null
      if (parsed.v !== version) return null
      if (Date.now() - parsed.t > maxAgeMs) return null
      return serializer.deserialize(parsed.d)
```

```18:29:assets/admin/repositories/OptionRepository.ts
  async findAllCached(options: { itemsPerPage?: number } = {}): Promise<HydraCollection<Option>> {
    const key = 'options:all';
    const version = 'v1'; // bump if server shape changes
    const ttl = 24 * 60 * 60 * 1000; // 24h
    const cached = adminCache.get<HydraCollection<Option>>(key, version, ttl);
    if (cached) return cached;
    const data = await this.findAll({ itemsPerPage: options.itemsPerPage ?? 500, sort: { sortOrder: 'asc', name: 'asc' } });
    adminCache.set(key, version, data);
    return data;
  }
```

```32:46:assets/admin/repositories/OptionValueRepository.ts
  async findByOptionCached(optionIri: string, options: { itemsPerPage?: number } = {}): Promise<HydraCollection<OptionValue>> {
    const idPart = String(optionIri).split('/').pop() || String(optionIri)
    const key = `option_values:by_option:${idPart}`
    const version = 'v1'
    const ttl = 24 * 60 * 60 * 1000 // 24h
    const cached = adminCache.get<HydraCollection<OptionValue>>(key, version, ttl)
    if (cached) return cached
    const data = await this.findAll({
      itemsPerPage: options.itemsPerPage ?? 1000,
      sort: { sortOrder: 'asc', value: 'asc' },
      filters: { optionType: optionIri },
    })
    adminCache.set(key, version, data)
    return data
  }
```

- **Кэш конфигурации**: сначала `window.APP_CONFIG`, затем persistent, затем сеть.

```17:26:assets/admin/services/config.ts
export async function getPaginationConfig(scope: string = 'default'): Promise<PaginationConfig> {
  // 1) Try from injected global config (fastest, no network)
  const win = window as any
  const fromWindow = win?.APP_CONFIG?.pagination?.[scope]
  if (fromWindow && Array.isArray(fromWindow.itemsPerPageOptions)) {
    return {
      itemsPerPageOptions: fromWindow.itemsPerPageOptions.map((n: any) => Number(n)).filter((n: number) => Number.isFinite(n) && n > 0),
      defaultItemsPerPage: Number(fromWindow.defaultItemsPerPage) || 10,
    }
```

### Сильные стороны
- **Четкая стратификация**: UI (Vue) ↔ Repositories ↔ HttpClient.
- **Hydra‑совместимость** и универсальный `useCrud`.
- **Двухуровневое кэширование** (in‑memory + persistent) с явной инвалидизацией на мутациях.
- **Глобальный UX‑лоадер** из перехватчиков, с защитой от мерцания (порог поддерживается).

### Риски и улучшения
- **Разнородный HTTP**: `fetch` в компонентах обходится без перехватчиков и лоадера. Рекомендация: обернуть fetch‑эндпоинты в тонкие репозитории на `HttpClient`, чтобы получить единый лог/авто‑лоадер/обработку 401.
- **Гварды аутентификации**: в роутере есть `meta.requiresAuth`, но глобальных guards нет. Если админ‑зона защищена только беком — ок; иначе добавить проверку токена/редирект.
- **Инвалидация persistent‑кэша**: у репозиториев есть методы `invalidatePersistentCache` (напр. для атрибутов/групп), но их стоит вызывать после create/update/delete соответствующих сущностей, иначе TTL‑задержки возможны.
- **Accept для JSON**: по умолчанию `application/ld+json`; для «чистых» JSON‑эндпоинтов иногда нужен иной Accept — сейчас это решается ручным `fetch`. Унификация через репозиторий с кастомным Accept упростит поддержку.

### Мини‑правила для Cursor (предложение к расширению `assets/admin/.cursor/rules/admin_local.mdc`)
```md
---
alwaysApply: false
description: Локальные правила для Admin SPA (Vue/Tailwind)
---

# Admin SPA (patterns)

## HTTP/Repositories
- Все вызовы сети — через `@admin/services/http` (axios). Не использовать `fetch` в компонентах.
- Для новых ресурсов создавать `Repository extends BaseRepository<T>`; мутации должны вызывать `invalidateCache()`.
- Для справочников использовать persistent‑кэш (`adminCache`) с версией и TTL, и метод `invalidatePersistentCache()`.

## CRUD/UI
- Списки/детали — через `useCrud<T>(repository)`. Hydra распаковка: `hydra:member`, `hydra:totalItems`.
- Пагинация/сортировки — параметры запроса; значения по умолчанию брать из `getPaginationConfig(scope)`.

## Лоадер/UX
- Глобальные запросы показывают оверлей (`uiLoading`), исключая SSE. Не блокировать UI при фоновых запросах.

## Ошибки/Авторизация
- Ошибки нормализуются `HttpClient.normalizeError`. 401 — сброс токена.
- Если нужна клиентская проверка доступа — добавить глобальные route guards для `meta.requiresAuth`.

## Опции/Дельты
- Привязки опций товаров сохранять дельтами через отдельный эндпоинт; сетевые префетчи ограничивать и кешировать.
```

- При желании, добавлю эти пункты в существующий `admin_local.mdc`.

- **Итог**:
  - CRUD: универсальный `useCrud` + репозитории с Hydra; частичные PATCH + нормализация и валидации.
  - API: единый axios‑клиент с токеном, лоадером и SSE‑исключениями; локально есть прямые fetch‑вызовы.
  - Кэш: in‑memory (60s) для GET + persistent (24h) для справочников/конфига; инвалидация на мутациях, требуются явные вызовы для persistent‑кэша.