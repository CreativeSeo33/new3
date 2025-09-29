import { Controller } from '@hotwired/stimulus';

declare global {
  interface Window {
    ymaps?: any;
    _ymapsLoading?: Promise<void>;
  }
}

export default class extends Controller {
  private initialized = false;
  private map: any | null = null;
  private clusterer: any | null = null;
  static values = {
    apiKey: String,
    lat: Number,
    lon: Number,
    zoom: { type: Number, default: 12 },
    points: Array,
    fitBounds: { type: Boolean, default: true },
    clusterOptions: Object,
  } as const;

  declare readonly apiKeyValue?: string;
  declare readonly latValue?: number;
  declare readonly lonValue?: number;
  declare readonly zoomValue: number;
  declare readonly pointsValue?: Array<{ id?: string | number; lat: number; lon: number; title?: string; address?: string }>;
  declare readonly fitBoundsValue: boolean;
  declare readonly clusterOptionsValue?: Record<string, unknown>;

  connect(): void {
    if ((this.element as any)._ymapInitialized === true) {
      return;
    }
    // Инициализируем карту только если контейнер видим (избегаем hidden-источника Fancybox)
    try {
      const el = this.element as HTMLElement;
      const rect = el.getBoundingClientRect();
      const cs = window.getComputedStyle(el);
      const isVisible = rect.width > 0 && rect.height > 0 && cs.display !== 'none' && cs.visibility !== 'hidden';
      if (!isVisible) {
        return;
      }
    } catch {}
    (this.element as any)._ymapInitialized = true;
    this.initialized = true;
    this.initializeMap();
  }

  disconnect(): void {
    try {
      if (this.map && typeof this.map.destroy === 'function') {
        this.map.destroy();
      }
    } catch {}
    this.map = null;
    this.clusterer = null;
    (this.element as any)._ymapInitialized = false;
    this.initialized = false;
  }

  async initializeMap(): Promise<void> {
    const apiKey = this.apiKeyValue || '';
    const lat = this.latValue ?? 55.751244;
    const lon = this.lonValue ?? 37.618423;
    const zoom = this.zoomValue ?? 12;

    await this.loadYandexMaps(apiKey);

    if (!window.ymaps) return;
    window.ymaps.ready(() => {
      const map = new window.ymaps.Map(this.element, {
        center: [lat, lon],
        zoom,
        controls: ['zoomControl', 'geolocationControl'],
      });
      this.map = map;

      const clustererOptions = Object.assign({
        preset: 'islands#invertedVioletClusterIcons',
        groupByCoordinates: false,
        clusterDisableClickZoom: false,
        clusterHideIconOnBalloonOpen: false,
        geoObjectHideIconOnBalloonOpen: false,
      }, this.clusterOptionsValue || {});
      const clusterer = new window.ymaps.Clusterer(clustererOptions);
      this.clusterer = clusterer;

      const points = Array.isArray(this.pointsValue) ? this.pointsValue : [];
      const placemarks = points.map((p) => new window.ymaps.Placemark([p.lat, p.lon], {
        balloonContent: p.address || p.title || 'ПВЗ',
        hintContent: p.title || p.address || 'ПВЗ',
      }));

      // События клика по метке -> отправка CustomEvent
      placemarks.forEach((pm, i) => {
        const data = points[i];
        pm.events.add('click', () => {
          this.dispatch('point-click', { detail: data });
        });
      });

      if (placemarks.length > 0) {
        clusterer.add(placemarks);
        map.geoObjects.add(clusterer);
        if (this.fitBoundsValue) {
          const bounds = clusterer.getBounds();
          if (bounds) {
            map.setBounds(bounds, { checkZoomRange: true });
          }
        }
      } else {
        // Если точек нет — добавим скрытую fallback-метку, которую будем убирать при первой установке точек
        const fallback = new window.ymaps.Placemark([lat, lon], {}, {
          preset: 'islands#blueCircleDotIconWithCaption',
          iconCaption: 'Центр города',
          visible: false,
        });
        (this.element as any)._fallbackPlacemark = fallback;
        map.geoObjects.add(fallback);
      }

      // Экспортируем упрощённый API на элемент
      (this.element as any).yandexMap = {
        focusPoint: (pointIdOrCoords: any) => {
          let coords: [number, number] | null = null;
          if (typeof pointIdOrCoords === 'object' && pointIdOrCoords && 'lat' in pointIdOrCoords) {
            coords = [Number(pointIdOrCoords.lat), Number(pointIdOrCoords.lon)];
          } else {
            const idx = points.findIndex(p => String(p.id) === String(pointIdOrCoords));
            if (idx >= 0) coords = [points[idx].lat, points[idx].lon];
          }
          if (coords) {
            map.setCenter(coords, Math.max(14, map.getZoom()));
          }
        },
        setPoints: (newPoints: Array<any>) => {
          // Удаляем fallback-метку, если есть
          const fb = (this.element as any)._fallbackPlacemark;
          if (fb) {
            try { map.geoObjects.remove(fb); } catch {}
            (this.element as any)._fallbackPlacemark = null;
          }
          clusterer.removeAll();
          const newPlacemarks = newPoints.map((p) => {
            const plat = Number((p as any).lat);
            const plon = Number((p as any).lon ?? (p as any).lng);
            const coords: [number, number] = [plat, plon];
            return new window.ymaps.Placemark(coords, {
              balloonContent: p.address || p.title || 'ПВЗ',
              hintContent: p.title || p.address || 'ПВЗ',
            });
          });
          clusterer.add(newPlacemarks);
          if (this.fitBoundsValue) {
            const bounds = clusterer.getBounds();
            if (bounds) map.setBounds(bounds, { checkZoomRange: true });
          }
        }
      };
    });
  }

  private loadYandexMaps(apiKey: string): Promise<void> {
    if (window.ymaps) {
      return Promise.resolve();
    }
    if (window._ymapsLoading) {
      return window._ymapsLoading;
    }
    window._ymapsLoading = new Promise<void>((resolve, reject) => {
      const script = document.createElement('script');
      const params = new URLSearchParams({ apikey: apiKey, lang: 'ru_RU' });
      script.src = `https://api-maps.yandex.ru/2.1/?${params.toString()}`;
      script.async = true;
      script.onload = () => resolve();
      script.onerror = () => reject(new Error('Yandex Maps failed to load'));
      document.head.appendChild(script);
    });
    return window._ymapsLoading;
  }
}


