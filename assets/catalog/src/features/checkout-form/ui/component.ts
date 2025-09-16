import { Component } from '@shared/ui/Component';
import { submitCheckout, saveCheckoutDraft } from '../api';
import { validatePhone } from '@shared/lib/phone';
import { getDeliveryContext, type DeliveryContextDto } from '@features/delivery-selector/api';

export interface CheckoutFormOptions {
  storageKey?: string;
  showAlerts?: boolean;
  country?: 'RU' | 'GENERIC';
}

export class CheckoutFormComponent extends Component {
  private form: HTMLFormElement | null = null;
  private submitButton: HTMLButtonElement | null = null;
  private storageKey: string = 'checkout_form';
  private phoneInput: HTMLInputElement | null = null;
  private handleInvalid = (ev: Event): void => {
    const target = ev.target as (HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement | null);
    if (target) {
      this.updateFieldErrorStyles(target);
    }
  };

  init(): void {
    this.form = this.$('form#checkout-form');
    this.submitButton = this.$('button#place-order') as HTMLButtonElement | null;

    if (this.options?.storageKey && typeof this.options.storageKey === 'string') {
      this.storageKey = this.options.storageKey;
    }

    this.hydrateForm();

    if (this.form) {
      const debouncedSave = this.debounce(async () => {
        const data = this.collectForm();
        this.writeCache(data);
        try { await saveCheckoutDraft({
          firstName: String(data.firstName || ''),
          phone: String(data.phone || ''),
          email: String(data.email || ''),
          comment: String(data.comment || ''),
        }); } catch (_) {}
      }, 400);

      this.on('input', debouncedSave as EventListener);
      this.on('change', debouncedSave as EventListener);
      // Живая подсветка при вводе/изменении
      const liveValidate = (ev: Event) => {
        const target = ev.target as (HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement | null);
        if (target && typeof target.checkValidity === 'function') {
          this.updateFieldErrorStyles(target);
        }
      };
      this.on('input', liveValidate as EventListener);
      this.on('change', liveValidate as EventListener);
      // Перехватываем submit, чтобы сработала HTML5-валидация
      this.form.addEventListener('submit', this.handleSubmit as EventListener);
      // Ловим native invalid для дочерних полей (capture=true, т.к. событие не всплывает)
      this.form.addEventListener('invalid', this.handleInvalid as EventListener, true);

      this.phoneInput = this.form.elements.namedItem('phone') as HTMLInputElement | null;
      if (this.phoneInput) {
        const onPhoneInput = () => {
          this.phoneInput!.setCustomValidity('');
          const ok = validatePhone(this.phoneInput!.value, this.options?.country ?? 'RU').valid;
          this.phoneInput!.classList.toggle('ring-1', !ok);
          this.phoneInput!.classList.toggle('ring-red-500', !ok);
          this.phoneInput!.classList.toggle('border-red-500', !ok);
          if (ok) {
            this.phoneInput!.removeAttribute('aria-invalid');
          } else {
            this.phoneInput!.setAttribute('aria-invalid', 'true');
          }
        };
        this.phoneInput.addEventListener('input', onPhoneInput);
        this.phoneInput.addEventListener('blur', onPhoneInput);
      }
    }
    // Предподсветка полей доставки по клику на кнопку сабмита (адрес/ПВЗ вне формы)
    if (this.submitButton) {
      this.submitButton.addEventListener('click', () => { this.validateCourierAddressDom(false); this.validatePvzDom(false); }, true);
    }
  }

  private readCache(): Record<string, any> {
    try {
      const raw = sessionStorage.getItem(this.storageKey) || '{}';
      return JSON.parse(raw);
    } catch {
      return {};
    }
  }

  private writeCache(data: Record<string, any>): void {
    try {
      sessionStorage.setItem(this.storageKey, JSON.stringify(data));
    } catch {}
  }

  private collectForm(): Record<string, any> {
    if (!(this.form instanceof HTMLFormElement)) return {};
    const fd = new FormData(this.form);
    return Object.fromEntries(fd.entries());
  }

  private hydrateForm(): void {
    if (!(this.form instanceof HTMLFormElement)) return;
    const data = this.readCache();
    const fields = ['firstName', 'phone', 'email', 'comment'];
    for (const name of fields) {
      const el = (this.form.elements.namedItem(name) as HTMLInputElement | HTMLTextAreaElement | null);
      if (el && typeof el.value !== 'undefined' && data[name] !== undefined) {
        el.value = String(data[name] ?? '');
      }
    }
  }

  private handleSubmit = async (e: Event): Promise<void> => {
    e.preventDefault();
    if (!(this.form instanceof HTMLFormElement)) return;

    // Проверка телефона до HTML5 reportValidity
    const phoneEl = this.form.elements.namedItem('phone') as HTMLInputElement | null;
    if (phoneEl) {
      const v = validatePhone(phoneEl.value, this.options?.country ?? 'RU');
      if (!v.valid) {
        phoneEl.setCustomValidity(v.error || 'Некорректный телефон');
        this.updateFieldErrorStyles(phoneEl);
        this.form.reportValidity();
        return;
      } else {
        phoneEl.setCustomValidity('');
        this.updateFieldErrorStyles(phoneEl);
      }
    }

    // HTML5 валидация
    if (!this.form.checkValidity()) {
      this.highlightInvalidFields();
      this.form.reportValidity();
      // Продолжим доп. проверки доставки ниже, но оформление прервём
    }

    // Валидация доставки перед оформлением
    interface FullDeliveryContext extends DeliveryContextDto {
      cityId?: number;
      pickupPointId?: string | number;
      address?: string | null;
    }
    const ctx = (await getDeliveryContext().catch(() => ({}))) as FullDeliveryContext;
    // DOM-валидации для доставки (адрес/ПВЗ) — не зависят от принадлежности к форме
    if (!this.validateCourierAddressDom(true) || !this.validatePvzDom(true)) {
      return;
    }

    const submitUrl = this.el.dataset.submitUrl || '';
    if (!submitUrl) return;

    const payload = this.collectForm();
    // Пробросим cityId, если он есть в контексте
    if (typeof (ctx as any).cityId === 'number' && (ctx as any).cityId > 0) {
      (payload as any).cityId = (ctx as any).cityId;
    }

    if (this.submitButton) this.submitButton.disabled = true;
    try {
      const res = await submitCheckout(submitUrl, payload);
      try { sessionStorage.removeItem(this.storageKey); } catch {}

      const redirectUrl = res?.redirectUrl || '/';
      window.location.href = redirectUrl;
    } catch (e: any) {
      if (this.options?.showAlerts !== false) {
        const message = e instanceof Error ? e.message : 'Не удалось оформить заказ';
        alert(message);
      }
    } finally {
      if (this.submitButton) this.submitButton.disabled = false;
    }
  };

  destroy(): void {
    if (this.form) {
      this.form.removeEventListener('submit', this.handleSubmit as EventListener);
      this.form.removeEventListener('invalid', this.handleInvalid as EventListener, true);
    }
    super.destroy();
  }

  private updateFieldErrorStyles(el: HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement): void {
    const valid = typeof el.checkValidity === 'function' ? el.checkValidity() : true;
    el.classList.toggle('border-red-500', !valid);
    el.classList.toggle('ring-1', !valid);
    el.classList.toggle('ring-red-500', !valid);
    if (!valid) {
      el.setAttribute('aria-invalid', 'true');
    } else {
      el.removeAttribute('aria-invalid');
    }
  }

  private highlightInvalidFields(): void {
    if (!(this.form instanceof HTMLFormElement)) return;
    const invalid = Array.from(this.form.querySelectorAll(':invalid')) as Array<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>;
    let firstInvalid: HTMLElement | null = null;
    // Сначала очистим у валидных
    const allFields = Array.from(this.form.querySelectorAll('input, textarea, select')) as Array<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>;
    for (const el of allFields) {
      const isInvalid = invalid.includes(el);
      el.classList.toggle('border-red-500', isInvalid);
      el.classList.toggle('ring-1', isInvalid);
      el.classList.toggle('ring-red-500', isInvalid);
      if (isInvalid) {
        el.setAttribute('aria-invalid', 'true');
        if (!firstInvalid) firstInvalid = el;
      } else {
        el.removeAttribute('aria-invalid');
      }
    }
    if (firstInvalid) {
      try { firstInvalid.focus(); } catch {}
    }
  }

  // Возвращает true, если всё ок; false, если ошибка (и блокируем сабмит)
  private validateCourierAddressDom(blockOnError: boolean): boolean {
    const courierBlock = document.getElementById('courier-block');
    const methodChecked = (this.el.querySelector('input[name="deliveryMethod"]:checked') as HTMLInputElement | null)?.value;
    const isCourier = (methodChecked === 'courier') || (!!courierBlock && !courierBlock.classList.contains('hidden'));
    if (!isCourier) return true;
    const addrInput = document.getElementById('courier-address') as HTMLInputElement | null;
    const errEl = document.getElementById('addr-error');
    const value = (addrInput?.value ?? '').trim();
    if (!addrInput) return true;
    if (value.length === 0) {
      addrInput.classList.add('border-red-500', 'ring-1', 'ring-red-500');
      addrInput.setAttribute('aria-invalid', 'true');
      if (errEl) errEl.classList.remove('hidden');
      try { addrInput.focus(); } catch {}
      const clearErr = () => {
        if (addrInput.value.trim().length > 0) {
          addrInput.classList.remove('border-red-500', 'ring-1', 'ring-red-500');
          addrInput.removeAttribute('aria-invalid');
          if (errEl) errEl.classList.add('hidden');
        }
      };
      addrInput.addEventListener('input', clearErr, { once: true });
      addrInput.addEventListener('change', clearErr, { once: true });
      return !blockOnError;
    } else {
      addrInput.classList.remove('border-red-500', 'ring-1', 'ring-red-500');
      addrInput.removeAttribute('aria-invalid');
      if (errEl) errEl.classList.add('hidden');
      return true;
    }
  }

  // Возвращает true, если всё ок; false, если ошибка (и блокируем сабмит)
  private validatePvzDom(blockOnError: boolean): boolean {
    const pvzBlock = document.getElementById('pvz-block');
    const methodChecked = (this.el.querySelector('input[name="deliveryMethod"]:checked') as HTMLInputElement | null)?.value;
    const isPvz = (methodChecked === 'pvz') || (!!pvzBlock && !pvzBlock.classList.contains('hidden'));
    if (!isPvz) return true;
    const sel = document.getElementById('pvz-select') as HTMLSelectElement | null;
    if (!sel) return true;
    const value = (sel.value || '').trim();
    if (value.length === 0) {
      sel.classList.add('border-red-500', 'ring-1', 'ring-red-500');
      try { sel.focus(); } catch {}
      const clear = () => {
        if ((sel.value || '').trim().length > 0) {
          sel.classList.remove('border-red-500', 'ring-1', 'ring-red-500');
        }
      };
      sel.addEventListener('change', clear, { once: true });
      sel.addEventListener('input', clear, { once: true });
      return !blockOnError;
    } else {
      sel.classList.remove('border-red-500', 'ring-1', 'ring-red-500');
      return true;
    }
  }

  private debounce<T extends (...args: any[]) => any>(fn: T, delayMs: number): (...args: Parameters<T>) => void {
    let t: number | undefined;
    return (...args: Parameters<T>) => {
      if (t) window.clearTimeout(t);
      t = window.setTimeout(() => { fn(...args); }, delayMs);
    };
  }
}


