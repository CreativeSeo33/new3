import { Component } from '@shared/ui/Component';
import { submitCheckout } from '../api';

export interface CheckoutFormOptions {
  storageKey?: string;
  showAlerts?: boolean;
}

export class CheckoutFormComponent extends Component {
  private form: HTMLFormElement | null = null;
  private submitButton: HTMLButtonElement | null = null;
  private storageKey: string = 'checkout_form';

  init(): void {
    this.form = this.$('form#checkout-form');
    this.submitButton = this.$('button#place-order') as HTMLButtonElement | null;

    if (this.options?.storageKey && typeof this.options.storageKey === 'string') {
      this.storageKey = this.options.storageKey;
    }

    this.hydrateForm();

    if (this.form) {
      this.on('input', () => this.writeCache(this.collectForm()));
      this.on('change', () => this.writeCache(this.collectForm()));
    }

    if (this.submitButton) {
      this.submitButton.addEventListener('click', this.handleSubmitClick);
    }
  }

  private readCache(): Record<string, any> {
    try {
      const raw = localStorage.getItem(this.storageKey) || '{}';
      return JSON.parse(raw);
    } catch {
      return {};
    }
  }

  private writeCache(data: Record<string, any>): void {
    try {
      localStorage.setItem(this.storageKey, JSON.stringify(data));
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

  private handleSubmitClick = async (): Promise<void> => {
    if (!(this.form instanceof HTMLFormElement)) return;

    const submitUrl = this.el.dataset.submitUrl || '';
    if (!submitUrl) return;

    const payload = this.collectForm();

    if (this.submitButton) this.submitButton.disabled = true;
    try {
      const res = await submitCheckout(submitUrl, payload);
      try { localStorage.removeItem(this.storageKey); } catch {}

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
    if (this.submitButton) {
      this.submitButton.removeEventListener('click', this.handleSubmitClick);
    }
    super.destroy();
  }
}


