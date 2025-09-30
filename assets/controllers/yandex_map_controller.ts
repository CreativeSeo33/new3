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
  private visibilityTimer: number | null = null;
  private points: Array<{ id?: string | number; lat: number; lon: number; title?: string; address?: string }> = [];
  private placemarkById: Map<string, any> = new Map();
  private pointById: Map<string, { id?: string | number; lat: number; lon: number; title?: string; address?: string }> = new Map();
  private selectedPointId: string | null = null;
  private handleElementClick = (e: Event): void => {
    const target = e.target as HTMLElement | null;
    if (!target) return;
    // Делегирование клика по кнопке "Выбрать" внутри balloon
    if (target.matches('.ymap-select-btn')) {
      e.preventDefault();
      const pointId = target.getAttribute('data-point-id') || '';
      const data = this.points.find(p => String(p.id ?? '') === String(pointId)) || { id: pointId } as any;
      this.dispatch('point-select', { detail: data });
      this.selectPointById(pointId);
    }
  };
  static values = {
    apiKey: String,
    lat: Number,
    lon: Number,
    zoom: { type: Number, default: 12 },
    points: Array,
    fitBounds: { type: Boolean, default: true },
    clusterOptions: Object,
    selectedId: String,
  } as const;

  declare readonly apiKeyValue?: string;
  declare readonly latValue?: number;
  declare readonly lonValue?: number;
  declare readonly zoomValue: number;
  declare readonly pointsValue?: Array<{ id?: string | number; lat: number; lon: number; title?: string; address?: string }>;
  declare readonly fitBoundsValue: boolean;
  declare readonly clusterOptionsValue?: Record<string, unknown>;
  declare readonly selectedIdValue?: string;

  connect(): void {
    if ((this.element as any)._ymapInitialized === true) {
      return;
    }
    if (!this.isElementVisible()) {
      this.scheduleInitWhenVisible();
      return;
    }
    (this.element as any)._ymapInitialized = true;
    this.initialized = true;
    this.initializeMap();
  }

  disconnect(): void {
    try {
      if (this.visibilityTimer !== null) {
        clearTimeout(this.visibilityTimer);
        this.visibilityTimer = null;
      }
      try { this.element.removeEventListener('click', this.handleElementClick); } catch {}
      if (this.map && typeof this.map.destroy === 'function') {
        this.map.destroy();
      }
    } catch {}
    this.map = null;
    this.clusterer = null;
    (this.element as any)._ymapInitialized = false;
    this.initialized = false;
  }

  private isElementVisible(): boolean {
    try {
      const el = this.element as HTMLElement;
      const rect = el.getBoundingClientRect();
      const cs = window.getComputedStyle(el);
      const hasSize = rect.width > 0 && rect.height > 0;
      const isShown = cs.display !== 'none' && cs.visibility !== 'hidden' && cs.opacity !== '0';
      return hasSize && isShown;
    } catch {
      return true;
    }
  }

  private scheduleInitWhenVisible(): void {
    if (this.visibilityTimer !== null) return;
    const check = () => {
      if (this.isElementVisible()) {
        this.visibilityTimer = null;
        if ((this.element as any)._ymapInitialized === true) return;
        (this.element as any)._ymapInitialized = true;
        this.initialized = true;
        this.initializeMap();
        return;
      }
      this.visibilityTimer = window.setTimeout(check, 250);
    };
    this.visibilityTimer = window.setTimeout(check, 250);
  }

  async initializeMap(): Promise<void> {
    const apiKey = this.apiKeyValue || '';
    const lat = this.latValue ?? 55.751244;
    const lon = this.lonValue ?? 37.618423;
    const zoom = this.zoomValue ?? 12;

    await this.loadYandexMaps(apiKey);

    if (!window.ymaps) return;
    window.ymaps.ready(() => {
      try { this.element.addEventListener('click', this.handleElementClick); } catch {}
      // Инициализация выбранной точки до создания меток
      const preselected = (this.selectedIdValue || '').trim();
      this.selectedPointId = preselected !== '' ? preselected : null;
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
      this.points = points;
      this.placemarkById = new Map();
      this.pointById = new Map();
      const placemarks = points.map((p, i) => {
        const pid = String(p.id ?? i);
        this.pointById.set(pid, p);
        const pm = new window.ymaps.Placemark([p.lat, p.lon], {
          balloonContent: this.renderBalloonHtml(p, pid === this.selectedPointId),
          hintContent: p.title || p.address || 'ПВЗ',
        }, {
          preset: this.getPlacemarkPreset(pid)
        });
        this.placemarkById.set(pid, pm);
        return pm;
      });

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
        // Если выбран ПВЗ — показываем его, без fitBounds
        if (this.selectedPointId) {
          const target = this.pointById.get(String(this.selectedPointId))
            || this.points.find(p => String(p.id ?? '') === String(this.selectedPointId));
          if (target) {
            const coords: [number, number] = [Number(target.lat), Number(target.lon)];
            try { map.setCenter(coords, Math.max(14, map.getZoom())); } catch {}
          }
        } else if (this.fitBoundsValue) {
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
            coords = [Number((pointIdOrCoords as any).lat), Number((pointIdOrCoords as any).lon)];
          } else {
            const idx = this.points.findIndex(p => String(p.id) === String(pointIdOrCoords));
            if (idx >= 0) coords = [this.points[idx].lat, this.points[idx].lon];
          }
          if (coords) {
            map.setCenter(coords, Math.max(14, map.getZoom()));
          }
        },
        selectPoint: (pointId: string | number) => {
          this.selectPointById(String(pointId));
          // Центрируем на выбранной точке
          const pid = String(pointId);
          const target = this.pointById.get(pid) || this.points.find(p => String(p.id ?? '') === pid);
          if (target) {
            try { this.map?.setCenter([Number(target.lat), Number(target.lon)], Math.max(14, this.map.getZoom())); } catch {}
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
          this.points = newPoints.slice();
          this.placemarkById = new Map();
          this.pointById = new Map();
          const newPlacemarks = newPoints.map((p, i) => {
            const plat = Number((p as any).lat);
            const plon = Number((p as any).lon ?? (p as any).lng);
            const coords: [number, number] = [plat, plon];
            const pid = String((p as any).id ?? i);
            this.pointById.set(pid, p);
            const pm = new window.ymaps.Placemark(coords, {
              balloonContent: this.renderBalloonHtml(p, pid === this.selectedPointId),
              hintContent: p.title || p.address || 'ПВЗ',
            }, {
              preset: this.getPlacemarkPreset(pid)
            });
            this.placemarkById.set(pid, pm);
            return pm;
          });
          newPlacemarks.forEach((pm, i) => {
            const data = newPoints[i];
            pm.events.add('click', () => {
              this.dispatch('point-click', { detail: data });
            });
          });
          clusterer.add(newPlacemarks);
          // Если выбран ПВЗ — показываем его, без fitBounds
          if (this.selectedPointId) {
            const target = this.pointById.get(String(this.selectedPointId))
              || newPoints.find((p: any) => String(p.id ?? '') === String(this.selectedPointId));
            if (target) {
              const coords: [number, number] = [Number((target as any).lat), Number((target as any).lon ?? (target as any).lng)];
              try { map.setCenter(coords, Math.max(14, map.getZoom())); } catch {}
            }
          } else if (this.fitBoundsValue) {
            const bounds = clusterer.getBounds();
            if (bounds) map.setBounds(bounds, { checkZoomRange: true });
          }
          this.updatePlacemarkVisuals();
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

  private renderBalloonHtml(p: { id?: string | number; title?: string; address?: string }, selected: boolean): string {
    const title = this.escapeHtml(p.title || p.address || 'ПВЗ');
    const addr = p.address ? `<div class=\"text-xs text-gray-600\">${this.escapeHtml(p.address)}</div>` : '';
    const pid = p.id != null ? String(p.id) : '';
    const label = selected ? 'Выбран' : 'Выбрать';
    const disabledAttr = selected ? ' disabled aria-disabled=\"true\"' : '';
    const btnClass = selected
      ? 'ymap-select-btn inline-flex items-center mt-2 px-3 py-1.5 rounded bg-gray-300 text-gray-600 cursor-not-allowed'
      : 'ymap-select-btn inline-flex items-center mt-2 px-3 py-1.5 rounded bg-black text-white hover:bg-gray-800';
    const btn = pid ? `<button type=\"button\" class=\"${btnClass}\" data-point-id=\"${this.escapeHtml(pid)}\"${disabledAttr}>${label}</button>` : '';
    return `<div class=\"text-sm\"><div class=\"font-medium\">${title}</div>${addr}${btn}</div>`;
  }

  private escapeHtml(input: unknown): string {
    const str = String(input ?? '');
    return str
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  private getPlacemarkPreset(pid: string): string {
    return this.selectedPointId && pid === this.selectedPointId
      ? 'islands#redIcon'
      : 'islands#blueIcon';
  }

  private selectPointById(pointId: string): void {
    if (!pointId) return;
    this.selectedPointId = String(pointId);
    this.updatePlacemarkVisuals();
  }

  private updatePlacemarkVisuals(): void {
    try {
      this.placemarkById.forEach((pm, pid) => {
        const p = this.pointById.get(pid) || { id: pid } as any;
        const selected = !!this.selectedPointId && pid === this.selectedPointId;
        try { pm.options.set('preset', this.getPlacemarkPreset(pid)); } catch {}
        try { pm.properties.set('balloonContent', this.renderBalloonHtml(p, selected)); } catch {}
      });
    } catch {}
  }
}


