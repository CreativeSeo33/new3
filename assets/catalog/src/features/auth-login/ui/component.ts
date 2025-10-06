import { Component } from '@shared/ui/Component';
import { login } from '../api';

export interface AuthLoginOptions {
  showErrors?: boolean;
}

export class AuthLoginComponent extends Component {
  private submitHandler = this.handleSubmit.bind(this);
  private clickHandler = this.handleSubmit.bind(this);
  private formEl: HTMLFormElement | null = null;
  private submitBtnEl: HTMLButtonElement | null = null;

  constructor(el: HTMLElement, opts: AuthLoginOptions = {}) {
    super(el, opts);
    this.init();
  }

  init(): void {
    const form = this.$('form');
    if (!form) return;
    this.formEl = form as HTMLFormElement;
    this.formEl.noValidate = true;
    this.formEl.addEventListener('submit', this.submitHandler, { capture: true });
    this.submitBtnEl = this.formEl.querySelector('[type="submit"], button:not([type])') as HTMLButtonElement | null;
    if (this.submitBtnEl) {
      this.submitBtnEl.addEventListener('click', this.clickHandler, { capture: true });
    }
  }

  private async handleSubmit(event: Event): Promise<void> {
    try { event.preventDefault(); } catch {}
    try { (event as any).stopImmediatePropagation?.(); } catch {}
    try { event.stopPropagation(); } catch {}

    const form = this.formEl;
    if (!form) return;
    const email = (form.querySelector('[name="email"]') as HTMLInputElement | null)?.value?.trim() || '';
    const password = (form.querySelector('[name="password"]') as HTMLInputElement | null)?.value || '';

    const submitBtn = this.submitBtnEl || (form.querySelector('[type="submit"], button:not([type])') as HTMLButtonElement | null);
    if (submitBtn) submitBtn.disabled = true;

    try {
      await login({ email, password });
      // Если в URL пришёл verify_token, отправим подтверждение и редиректнем на главную
      const params = new URLSearchParams(location.search);
      const verifyToken = params.get('verify_token');
      const verifyEmail = params.get('email') || email;
      if (verifyToken) {
        try {
          await fetch('/api/customer/auth/email/verify', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ token: verifyToken, email: verifyEmail }),
          });
        } catch {}
      }
      // Редирект: учитываем параметр next, безопасно только на относительные пути
      const next = params.get('next');
      const target = next && next.startsWith('/') ? next : '/account';
      location.href = target;
    } catch (error) {
      if (this.options.showErrors !== false) {
        const box = this.$('[data-error]');
        if (box) {
          box.removeAttribute('hidden');
          try { box.classList.remove('hidden'); } catch {}
          box.textContent = 'Неверные учётные данные';
        }
      }
    } finally {
      if (submitBtn) submitBtn.disabled = false;
    }
  }

  destroy(): void {
    if (this.formEl) {
      this.formEl.removeEventListener('submit', this.submitHandler);
      this.formEl = null;
    }
    super.destroy();
  }
}


