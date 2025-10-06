import { Component } from '@shared/ui/Component';
import { register } from '../api';

export interface AuthRegisterOptions {
  showErrors?: boolean;
}

export class AuthRegisterComponent extends Component {
  private submitHandler = this.handleSubmit.bind(this);

  constructor(el: HTMLElement, opts: AuthRegisterOptions = {}) {
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
      await register({ email, password });
      const successBox = this.$('[data-success]');
      if (successBox) {
        successBox.removeAttribute('hidden');
        try { successBox.classList.remove('hidden'); } catch {}
        successBox.textContent = 'Письмо отправлено. Проверьте почту.';
      }
      // UX: через 2 секунды предложим войти
      setTimeout(() => { location.href = '/auth/login'; }, 2000);
    } catch (error) {
      if (this.options.showErrors !== false) {
        const box = this.$('[data-error]');
        if (box) {
          box.removeAttribute('hidden');
          try { box.classList.remove('hidden'); } catch {}
          box.textContent = 'Не удалось выполнить регистрацию';
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


