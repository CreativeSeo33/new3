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
  } as const;

  declare readonly apiKeyValue?: string;
  declare readonly latValue?: number;
  declare readonly lonValue?: number;
  declare readonly zoomValue: number;
  declare readonly pointsValue?: Array<{ lat: number; lon: number; title?: string }>;

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

      const clusterer = new window.ymaps.Clusterer({
        preset: 'islands#invertedVioletClusterIcons',
        groupByCoordinates: false,
        clusterDisableClickZoom: false,
        clusterHideIconOnBalloonOpen: false,
        geoObjectHideIconOnBalloonOpen: false,
      });

      const points = Array.isArray(this.pointsValue) ? this.pointsValue : [];
      const placemarks = points.map((p) => new window.ymaps.Placemark([p.lat, p.lon], {
        balloonContent: p.title || 'ПВЗ',
      }));

      if (placemarks.length > 0) {
        clusterer.add(placemarks);
        map.geoObjects.add(clusterer);
        const bounds = clusterer.getBounds();
        if (bounds) {
          map.setBounds(bounds, { checkZoomRange: true });
        }
      } else {
        // Если точек нет — поставим метку центра
        map.geoObjects.add(new window.ymaps.Placemark([lat, lon], {}, {
          preset: 'islands#blueCircleDotIconWithCaption',
          iconCaption: 'Центр города',
        }));
      }
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


