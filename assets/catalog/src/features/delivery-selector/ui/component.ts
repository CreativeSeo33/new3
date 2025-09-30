import { Component } from '@shared/ui/Component';
import { Spinner } from '@shared/ui/spinner';
import {
  getDeliveryContext,
  fetchPvzPoints,
  selectMethod,
  selectPvz,
  type DeliveryMethodCode,
  type DeliveryContextDto,
  type PvzPointDto
} from '../api';
import { getCart as fetchFullCart } from '@features/add-to-cart/api';
import { updateCartSummary } from '@shared/ui/updateCartSummary';

export interface DeliverySelectorOptions {}

type MapPoint = {
  id?: string | number;
  lat: number;
  lon: number;
  title?: string;
  address?: string;
};

export class DeliverySelector extends Component {
  private methodInputs: HTMLInputElement[] = [];
  private pvzBlock!: HTMLElement | null;
  private pvzSelect!: HTMLSelectElement | null;
  private pvzEmpty!: HTMLElement | null;
  private courierBlock!: HTMLElement | null;
  private addrInput!: HTMLInputElement | null;
  private shipCostEl!: HTMLElement | null;
  private totalEl!: HTMLElement | null;
  private pvzMapWrapper: HTMLElement | null = null;
  private pvzMapCanvas: HTMLElement | null = null;
  private pvzMapPoints: MapPoint[] = [];
  private abortController: AbortController | null = null;
  private currentCityName: string | null = null;
  private spinner: Spinner | null = null;

  constructor(el: HTMLElement, opts: DeliverySelectorOptions = {}) {
    super(el, opts);
    this.init();
  }

  init(): void {
    this.cacheDom();
    void this.bootstrap();
    this.bindEvents();
  }

  private cacheDom(): void {
    this.methodInputs = Array.from(this.el.querySelectorAll('input[name="deliveryMethod"]')) as HTMLInputElement[];
    this.pvzBlock = this.$('#pvz-block');
    this.pvzSelect = this.$('#pvz-select') as HTMLSelectElement | null;
    this.pvzEmpty = this.$('#pvz-empty');
    this.courierBlock = this.$('#courier-block');
    this.addrInput = this.$('#courier-address') as HTMLInputElement | null;
    this.pvzMapWrapper = this.$('[data-testid="delivery-pvz-map"]');
    this.pvzMapCanvas = this.pvzMapWrapper?.querySelector('[data-controller="yandex-map"]') as HTMLElement | null;

    if (this.pvzMapCanvas) {
      const initial = this.pvzMapCanvas.getAttribute('data-yandex-map-points-value');
      if (initial) {
        try {
          const parsed = JSON.parse(initial) as MapPoint[];
          if (Array.isArray(parsed)) {
            this.pvzMapPoints = parsed.filter((p) => this.isValidCoords(p.lat, p.lon));
          }
        } catch (_) {
          this.pvzMapPoints = [];
        }
      }
    }

    this.shipCostEl = document.getElementById('cart-shipping')
      || (document.querySelector('[data-cart-shipping]') as HTMLElement | null)
      || this.$('#ship-cost');
    this.totalEl = document.getElementById('cart-total')
      || (document.querySelector('[data-cart-total]') as HTMLElement | null)
      || this.$('#checkout-total');

    const spinnerEl = this.$('#delivery-spinner') as HTMLElement | null;
    if (spinnerEl) {
      try {
        this.spinner = new Spinner(spinnerEl, { overlay: true, visible: false });
      } catch (_) {
        // ignore spinner init errors
      }
    }
  }

  private async bootstrap(): Promise<void> {
    this.showSpinner();
    const ctx: DeliveryContextDto = await getDeliveryContext().catch(() => ({ methodCode: 'pvz', cityName: null }));
    const initMethod = (ctx.methodCode || 'pvz') as DeliveryMethodCode;
    this.currentCityName = ctx.cityName || null;
    this.methodInputs.forEach((r) => (r.checked = r.value === initMethod));
    this.toggleBlocks(initMethod);
    if (initMethod === 'pvz') {
      await this.loadPvz(this.currentCityName || '');
    }
    this.hideSpinner();
    this.revealBlocks();
    this.applyMapPoints();
  }

  private bindEvents(): void {
    // Change method
    for (const radio of this.methodInputs) {
      radio.addEventListener('change', async (e: Event) => {
        const value = (e.target as HTMLInputElement).value as DeliveryMethodCode;
        try {
          this.showSpinner();
          await selectMethod({ methodCode: value });
          this.toggleBlocks(value);
          if (value === 'pvz') {
            await this.loadPvz(this.currentCityName || '');
          } else {
            this.updatePvzMapFromApi([]);
          }
          // Берём источник истины из полной корзины
          try {
            const cart = await fetchFullCart();
            updateCartSummary(cart);
          } catch (_) {}
        } catch (e) {
          // ignore
        } finally {
          this.hideSpinner();
        }
      });
    }

    // Select PVZ
    this.pvzSelect?.addEventListener('change', async () => {
      const pvzCode = this.pvzSelect?.value || '';
      if (!pvzCode) return;
      try {
        this.showSpinner();
        await selectPvz(pvzCode);
        try {
          const cart = await fetchFullCart();
          updateCartSummary(cart);
        } catch (_) {}
      } catch (e) {
        alert('Ошибка');
      } finally {
        this.hideSpinner();
      }
    });

    // Courier address
    this.addrInput?.addEventListener('blur', async () => {
      const address = (this.addrInput?.value || '').trim();
      const errEl = this.$('#addr-error');
      if (errEl) errEl.classList.toggle('hidden', !!address);
      if (!address) return;
      try {
        this.showSpinner();
        await selectMethod({ methodCode: 'courier', address });
        try {
          const cart = await fetchFullCart();
          updateCartSummary(cart);
        } catch (_) {}
      } catch (e) {
        alert('Ошибка');
      } finally {
        this.hideSpinner();
      }
    });
  }

  private toggleBlocks(method: DeliveryMethodCode): void {
    const isPvz = method === 'pvz';
    this.pvzBlock?.classList.toggle('hidden', !isPvz);
    this.courierBlock?.classList.toggle('hidden', isPvz);
    if (isPvz) {
      this.applyMapPoints();
    }
  }

  private async loadPvz(cityName: string): Promise<void> {
    if (!this.pvzSelect || !this.pvzEmpty) return;
    this.pvzSelect.innerHTML = '';
    this.pvzEmpty.classList.add('hidden');
    if (!cityName) {
      this.pvzEmpty.textContent = 'Город не выбран';
      this.pvzEmpty.classList.remove('hidden');
      this.updatePvzMapFromApi([]);
      return;
    }

    if (this.abortController) this.abortController.abort();
    this.abortController = new AbortController();

    try {
      this.showSpinner();
      const list = await fetchPvzPoints(cityName);
      if (!Array.isArray(list) || list.length === 0) {
        this.pvzEmpty.classList.remove('hidden');
        this.updatePvzMapFromApi([]);
        return;
      }
      for (const p of list) {
        const opt = document.createElement('option');
        opt.value = p.code;
        opt.textContent = p.address || p.name || p.code;
        this.pvzSelect.appendChild(opt);
      }
      this.updatePvzMapFromApi(list);
    } catch (_) {
      this.pvzEmpty.classList.remove('hidden');
      this.updatePvzMapFromApi([]);
    } finally {
      this.hideSpinner();
    }
  }

  destroy(): void {
    if (this.abortController) this.abortController.abort();
    super.destroy();
  }

  private formatRub(value: number): string {
    const numeric = Number(value);
    const safe = Number.isFinite(numeric) ? Math.round(numeric) : 0;
    try {
      return new Intl.NumberFormat('ru-RU', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
      }).format(safe) + ' руб.';
    } catch (_) {
      return String(safe) + ' руб.';
    }
  }

  private showSpinner(): void {
    try { this.spinner?.show(); } catch (_) {}
  }

  private hideSpinner(): void {
    try { this.spinner?.hide(); } catch (_) {}
  }

  private revealBlocks(): void {
    if (this.pvzBlock) {
      this.pvzBlock.classList.remove('opacity-0');
    }
    if (this.courierBlock) {
      this.courierBlock.classList.remove('opacity-0');
    }
  }

  private updatePvzMapFromApi(list: PvzPointDto[]): void {
    const points = Array.isArray(list)
      ? list
          .map<MapPoint | null>((item) => {
            const lat = item.lat ?? (item as unknown as { latitude?: number | null }).latitude ?? null;
            const lonRaw = item.lon ?? item.lng ?? (item as unknown as { longitude?: number | null }).longitude ?? null;
            if (!this.isValidCoords(lat, lonRaw)) {
              return null;
            }
            const p: MapPoint = {
              lat: Number(lat),
              lon: Number(lonRaw)
            };
            if (item.code) p.id = item.code;
            else if (item.name) p.id = item.name;
            if (item.name != null) p.title = item.name;
            if (item.address != null) p.address = item.address;
            return p;
          })
          .filter((p): p is MapPoint => p !== null)
      : [];

    this.pvzMapPoints = points;
    this.applyMapPoints();
  }

  private applyMapPoints(): void {
    if (!this.pvzBlock || this.pvzBlock.classList.contains('hidden')) {
      return;
    }
    this.trySetMapPoints(this.pvzMapPoints);
  }

  private trySetMapPoints(points: MapPoint[], attempt = 0): void {
    const mapEl = this.pvzMapCanvas;
    if (!mapEl) return;
    const mapApi = (mapEl as any).yandexMap;
    if (mapApi && typeof mapApi.setPoints === 'function') {
      mapApi.setPoints(points);
      return;
    }
    if (attempt >= 10) return;
    window.setTimeout(() => this.trySetMapPoints(points, attempt + 1), 200);
  }

  private isValidCoords(lat: unknown, lon: unknown): lat is number {
    const latNum = Number(lat);
    const lonNum = Number(lon);
    return Number.isFinite(latNum) && Number.isFinite(lonNum);
  }
}


