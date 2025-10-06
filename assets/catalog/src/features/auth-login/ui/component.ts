import { Component } from '@shared/ui/Component';
import { login } from '../api';

export interface AuthLoginOptions {
  showErrors?: boolean;
}

export class AuthLoginComponent extends Component {
  private submitHandler = this.handleSubmit.bind(this);

  constructor(el: HTMLElement, opts: AuthLoginOptions = {}) {
    super(el, opts);
    this.init();
  }

  init(): void {
    const form = this.$('form');
    if (!form) return;
    this.on('submit', this.submitHandler);
  }

  private async handleSubmit(event: Event): Promise<void> {
    event.preventDefault();

    const form = event.currentTarget as HTMLFormElement;
    const email = (form.querySelector('[name="email"]') as HTMLInputElement | null)?.value?.trim() || '';
    const password = (form.querySelector('[name="password"]') as HTMLInputElement | null)?.value || '';

    const submitBtn = form.querySelector('[type="submit"]') as HTMLButtonElement | null;
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
      // Редирект в личный кабинет
      location.href = '/account';
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
    const form = this.$('form');
    if (form) this.off('submit', this.submitHandler);
    super.destroy();
  }
}


