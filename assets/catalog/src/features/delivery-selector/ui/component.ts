import { Component } from '@shared/ui/Component';
import { Spinner } from '@shared/ui/spinner';
import { getDeliveryContext, fetchPvzPoints, selectMethod, selectPvz, type DeliveryMethodCode, type DeliveryContextDto } from '../api';

export interface DeliverySelectorOptions {}

export class DeliverySelector extends Component {
  private methodInputs: HTMLInputElement[] = [];
  private pvzBlock!: HTMLElement | null;
  private pvzSelect!: HTMLSelectElement | null;
  private pvzEmpty!: HTMLElement | null;
  private courierBlock!: HTMLElement | null;
  private addrInput!: HTMLInputElement | null;
  private shipCostEl!: HTMLElement | null;
  private totalEl!: HTMLElement | null;
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
    this.shipCostEl = this.$('#ship-cost');
    this.totalEl = document.getElementById('checkout-total');

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
  }

  private bindEvents(): void {
    // Change method
    for (const radio of this.methodInputs) {
      radio.addEventListener('change', async (e: Event) => {
        const value = (e.target as HTMLInputElement).value as DeliveryMethodCode;
        try {
          this.showSpinner();
          const data = await selectMethod({ methodCode: value });
          this.toggleBlocks(value);
          if (value === 'pvz') {
            await this.loadPvz(this.currentCityName || '');
          }
          if (this.shipCostEl) this.shipCostEl.textContent = (data.shippingCost / 100).toFixed(2);
          if (this.totalEl) this.totalEl.textContent = (data.total / 100).toFixed(2) + ' ₽';
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
        const data = await selectPvz(pvzCode);
        if (this.shipCostEl) this.shipCostEl.textContent = (data.shippingCost / 100).toFixed(2);
        if (this.totalEl) this.totalEl.textContent = (data.total / 100).toFixed(2) + ' ₽';
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
        const data = await selectMethod({ methodCode: 'courier', address });
        if (this.shipCostEl) this.shipCostEl.textContent = (data.shippingCost / 100).toFixed(2);
        if (this.totalEl) this.totalEl.textContent = (data.total / 100).toFixed(2) + ' ₽';
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
  }

  private async loadPvz(cityName: string): Promise<void> {
    if (!this.pvzSelect || !this.pvzEmpty) return;
    this.pvzSelect.innerHTML = '';
    this.pvzEmpty.classList.add('hidden');
    if (!cityName) {
      this.pvzEmpty.textContent = 'Город не выбран';
      this.pvzEmpty.classList.remove('hidden');
      return;
    }

    if (this.abortController) this.abortController.abort();
    this.abortController = new AbortController();

    try {
      this.showSpinner();
      const list = await fetchPvzPoints(cityName);
      if (!Array.isArray(list) || list.length === 0) {
        this.pvzEmpty.classList.remove('hidden');
        return;
      }
      for (const p of list) {
        const opt = document.createElement('option');
        opt.value = p.code;
        opt.textContent = p.address || p.name || p.code;
        this.pvzSelect.appendChild(opt);
      }
    } catch (_) {
      this.pvzEmpty.classList.remove('hidden');
    } finally {
      this.hideSpinner();
    }
  }

  destroy(): void {
    if (this.abortController) this.abortController.abort();
    super.destroy();
  }

  private showSpinner(): void {
    try { this.spinner?.show(); } catch (_) {}
  }

  private hideSpinner(): void {
    try { this.spinner?.hide(); } catch (_) {}
  }
}


