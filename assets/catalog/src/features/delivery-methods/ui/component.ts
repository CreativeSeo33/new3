import { Component } from '@shared/ui/Component';
import { fetchDeliveryTypes, fetchDeliveryContext, selectDeliveryMethod, type DeliveryTypeDto } from '../api';

export interface DeliveryMethodsOptions {}

export class DeliveryMethods extends Component {
  private currentCode: string | null = null;

  constructor(el: HTMLElement, opts: DeliveryMethodsOptions = {}) {
    super(el, opts);
    this.init();
  }

  async init(): Promise<void> {
    try {
      const selectedAttr = this.dataset.str('selectedCode', '');
      const selectedFromAttr = selectedAttr.length > 0 ? selectedAttr : null;

      const ssrRadios = this.$$('#delivery-methods-list input[type="radio"][name="delivery-method"]');
      const hasSSRList = ssrRadios.length > 0;

      const ctxPromise: Promise<Record<string, any>> = selectedFromAttr
        ? Promise.resolve({ methodCode: selectedFromAttr })
        : fetchDeliveryContext();

      const typesPromise: Promise<DeliveryTypeDto[] | null> = hasSSRList
        ? Promise.resolve<DeliveryTypeDto[] | null>(null)
        : fetchDeliveryTypes();

      const [ctx, types] = await Promise.all([ctxPromise, typesPromise]);

      const selectedFromCtx = (ctx && typeof (ctx as any).methodCode === 'string') ? (ctx as any).methodCode : null;

      // Если API не запрашивали или вернуло пусто, не затираем SSR-рендер
      if (!types || !Array.isArray(types) || types.length === 0) {
        if (selectedFromCtx) {
          ssrRadios.forEach((r: any) => { r.checked = (r.value === selectedFromCtx); });
          this.currentCode = selectedFromCtx;
        } else {
          const firstChecked = this.$('#delivery-methods-list input[type="radio"][name="delivery-method"]:checked') as HTMLInputElement | null;
          this.currentCode = firstChecked?.value ?? null;
        }
        this.attachHandlers();
        return;
      }

      const selected = selectedFromCtx
        || (types.find(t => t.default === true || (t as any).isDefault === true)?.code ?? types[0]?.code ?? null);

      this.currentCode = selected;
      this.render(types, selected);
      this.attachHandlers();
    } catch (e) {
      // Фолбэк: не перерисовываем список, оставляем SSR и просто вешаем обработчики
      this.attachHandlers();
      this.renderError('Не удалось загрузить способы доставки');
    }
  }

  private render(types: DeliveryTypeDto[], selectedCode: string | null): void {
    const list = this.$('#delivery-methods-list');
    if (!list) return;
    // Если пусто — ничего не меняем, чтобы не затирать SSR
    if (!Array.isArray(types) || types.length === 0) return;
    list.innerHTML = types.map(t => {
      const checked = t.code === selectedCode ? 'checked' : '';
      const label = t.name || t.code;
      return `
        <label class="flex items-center gap-2 cursor-pointer">
          <input type="radio" name="delivery-method" value="${t.code}" ${checked}>
          <span>${label}</span>
        </label>
      `;
    }).join('');
  }

  private renderError(message: string): void {
    const err = this.$('#delivery-methods-error');
    if (!err) return;
    err.textContent = message;
    err.classList.remove('hidden');
  }

  private async onChange(e: Event): Promise<void> {
    const target = e.target as HTMLInputElement | null;
    if (!target || target.name !== 'delivery-method') return;
    const code = target.value;
    if (!code || code === this.currentCode) return;
    try {
      const data = await selectDeliveryMethod(code);
      this.currentCode = code;
      const shippingCents = (data as any)?.shipping?.cost ?? 0;
      const totalCents = (data as any)?.total ?? (data as any)?.totals?.total ?? null;

      const shippingEl = document.querySelector('#cart-shipping');
      if (shippingEl) {
        shippingEl.textContent = this.formatAmount(shippingCents);
      }
      const totalEl = document.querySelector('#cart-total');
      if (totalEl && totalCents !== null) {
        totalEl.textContent = this.formatAmount(totalCents);
      }
    } catch (e) {
      this.renderError('Ошибка при выборе доставки');
    }
  }

  private formatAmount(cents: number | null | undefined): string {
    const value = Math.round(Number(cents || 0));
    return new Intl.NumberFormat('ru-RU').format(value) + ' руб.';
  }

  private attachHandlers(): void {
    const list = this.$('#delivery-methods-list');
    if (!list) return;
    list.addEventListener('change', this.onChange.bind(this));
  }
}


