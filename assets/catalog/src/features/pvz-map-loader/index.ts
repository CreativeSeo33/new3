import { get } from '@shared/api/http';

export interface PvzPoint {
  id?: string | number | null;
  code?: string | null;
  name?: string | null;
  address?: string | null;
  city?: string | null;
  lat?: number | null;
  lng?: number | null;
  company?: string | null;
}

export interface PvzMapLoaderOptions {
  city?: string;
  cityId?: number;
  itemsPerPage?: number;
}

export function init(root: HTMLElement, opts: PvzMapLoaderOptions = {}): () => void {
  let destroyed = false;

  // Инициализируемся только внутри контента Fancybox, иначе выходим (избегаем дублирования в исходном hidden DOM)
  const inFancybox = !!root.closest('.fancybox__content, .fancybox__container, .fancybox__slide');
  if (!inFancybox) {
    return () => { destroyed = true; };
  }

  // Защита от повторной инициализации в том же корне
  if (root.dataset.pvzMapLoaderInit === '1') {
    return () => { destroyed = true; };
  }
  root.dataset.pvzMapLoaderInit = '1';

  // Найдём хост, куда будем монтировать карту, если компонента ещё нет
  const host = root.querySelector<HTMLElement>('[data-pvz-map-host]');
  let container: HTMLElement | null = root.querySelector<HTMLElement>('[data-controller="yandex-map"]');

  if (!container && host) {
    // Создадим контейнер, аналогичный шаблону YandexMap.html.twig
    const el = document.createElement('div');
    el.className = 'h-[520px] w-full';
    el.setAttribute('role', 'region');
    el.setAttribute('aria-label', 'Карта пунктов выдачи');
    el.setAttribute('data-controller', 'yandex-map');
    const apiKey = root.getAttribute('data-api-key') || '';
    if (apiKey) el.setAttribute('data-yandex-map-api-key-value', apiKey);
    el.setAttribute('data-yandex-map-zoom-value', '12');
    el.setAttribute('data-yandex-map-fit-bounds-value', 'true');
    host.appendChild(el);
    container = el;
  }

  if (!container) return () => {};

  function waitForMapApi(el: HTMLElement, timeoutMs = 6000, intervalMs = 100): Promise<any> {
    return new Promise((resolve) => {
      const started = Date.now();
      const timer = setInterval(() => {
        const api = (el as any).yandexMap;
        if (api && typeof api.setPoints === 'function') {
          clearInterval(timer);
          resolve(api);
          return;
        }
        if (Date.now() - started > timeoutMs) {
          clearInterval(timer);
          resolve(null);
        }
      }, intervalMs);
    });
  }

  // Определяем город из deliveryContext, если доступен в DOM как data-selected-code родителя
  const city = (document.querySelector('[data-testid="delivery-root"]')?.getAttribute('data-city-name')
    || opts.city
    || '').toString();
  const cityIdAttr = (document.querySelector('[data-testid="delivery-root"]')?.getAttribute('data-city-id') || '').toString();
  const cityId = Number.isFinite(Number(cityIdAttr)) && cityIdAttr !== '' ? Number(cityIdAttr) : (opts.cityId || 0);

  // Загружаем точки ПВЗ по публичному эндпоинту (кешируется на бэке)
  const params = new URLSearchParams();
  if (cityId > 0) params.set('cityId', String(cityId));
  else if (city.length > 0) params.set('city', city);
  if (opts.itemsPerPage) params.set('itemsPerPage', String(opts.itemsPerPage));

  void get<{ data: PvzPoint[]; total: number; page: number; itemsPerPage: number } | PvzPoint[]>(`/delivery/points?${params.toString()}`)
    .then(async (resp) => {
      if (destroyed) return;
      const list = Array.isArray(resp) ? resp : (resp?.data || []);
      const points = list
        .filter(p => p && p.lat != null && p.lng != null && !isNaN(Number(p.lat)) && !isNaN(Number(p.lng)))
        .map(p => ({
          id: p.id ?? p.code ?? undefined,
          lat: Number(p.lat),
          lon: Number(p.lng),
          title: p.name || p.company || 'ПВЗ',
          address: p.address || '',
        }));

      // Дождёмся готовности карты и установим точки
      const api: any = await waitForMapApi(container!);
      if (destroyed) return;
      if (api && typeof api.setPoints === 'function') {
        api.setPoints(points);
        setTimeout(() => {
          try { api.setPoints(points); } catch {}
        }, 250);
      }
    })
    .catch(() => {
      // опционально: показать сообщение об ошибке
    });

  return () => {
    destroyed = true;
  };
}


