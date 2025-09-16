import { Component } from '@shared/ui/Component';
import { submitCheckout, saveCheckoutDraft } from '../api';
import { validatePhone } from '@shared/lib/phone';

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
      // Перехватываем submit, чтобы сработала HTML5-валидация
      this.form.addEventListener('submit', this.handleSubmit as EventListener);

      this.phoneInput = this.form.elements.namedItem('phone') as HTMLInputElement | null;
      if (this.phoneInput) {
        const onPhoneInput = () => {
          this.phoneInput!.setCustomValidity('');
          const ok = validatePhone(this.phoneInput!.value, this.options?.country ?? 'RU').valid;
          this.phoneInput!.classList.toggle('ring-1', !ok);
          this.phoneInput!.classList.toggle('ring-red-500', !ok);
        };
        this.phoneInput.addEventListener('input', onPhoneInput);
        this.phoneInput.addEventListener('blur', onPhoneInput);
      }
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
        this.form.reportValidity();
        return;
      } else {
        phoneEl.setCustomValidity('');
      }
    }

    // HTML5 валидация
    if (!this.form.checkValidity()) {
      this.form.reportValidity();
      return;
    }

    const submitUrl = this.el.dataset.submitUrl || '';
    if (!submitUrl) return;

    const payload = this.collectForm();

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
    if (this.form) this.form.removeEventListener('submit', this.handleSubmit as EventListener);
    super.destroy();
  }

  private debounce<T extends (...args: any[]) => any>(fn: T, delayMs: number): (...args: Parameters<T>) => void {
    let t: number | undefined;
    return (...args: Parameters<T>) => {
      if (t) window.clearTimeout(t);
      t = window.setTimeout(() => { fn(...args); }, delayMs);
    };
  }
}


