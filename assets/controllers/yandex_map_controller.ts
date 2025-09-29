import { Controller } from '@hotwired/stimulus';

declare global {
  interface Window {
    ymaps?: any;
    _ymapsLoading?: Promise<void>;
  }
}

export default class extends Controller {
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
    this.initializeMap();
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

      const clustererOptions = Object.assign({
        preset: 'islands#invertedVioletClusterIcons',
        groupByCoordinates: false,
        clusterDisableClickZoom: false,
        clusterHideIconOnBalloonOpen: false,
        geoObjectHideIconOnBalloonOpen: false,
      }, this.clusterOptionsValue || {});
      const clusterer = new window.ymaps.Clusterer(clustererOptions);

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
        // Если точек нет — поставим метку центра
        map.geoObjects.add(new window.ymaps.Placemark([lat, lon], {}, {
          preset: 'islands#blueCircleDotIconWithCaption',
          iconCaption: 'Центр города',
        }));
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
          clusterer.removeAll();
          const newPlacemarks = newPoints.map((p) => new window.ymaps.Placemark([p.lat, p.lon], {
            balloonContent: p.address || p.title || 'ПВЗ',
            hintContent: p.title || p.address || 'ПВЗ',
          }));
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


