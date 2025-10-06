import { Component } from '@shared/ui/Component';
import { passwordRequest, passwordConfirm } from '../api';

export class AuthPasswordComponent extends Component {
  private submitHandler = this.handleSubmit.bind(this);

  init(): void {
    const form = this.$('form');
    if (!form) return;
    this.on('submit', this.submitHandler);
  }

  private async handleSubmit(event: Event): Promise<void> {
    event.preventDefault();
    const form = event.currentTarget as HTMLFormElement;
    const mode = this.el.dataset.mode || 'request';
    const email = (form.querySelector('[name="email"]') as HTMLInputElement | null)?.value?.trim() || '';
    const token = (form.querySelector('[name="token"]') as HTMLInputElement | null)?.value?.trim() || '';
    const password = (form.querySelector('[name="password"]') as HTMLInputElement | null)?.value || '';
    const submitBtn = form.querySelector('[type="submit"]') as HTMLButtonElement | null;
    if (submitBtn) submitBtn.disabled = true;

    try {
      if (mode === 'confirm') {
        await passwordConfirm({ email, token, password });
        const ok = this.$('[data-success]');
        if (ok) { ok.removeAttribute('hidden'); try { ok.classList.remove('hidden'); } catch {} }
      } else {
        await passwordRequest({ email });
        const ok = this.$('[data-success]');
        if (ok) { ok.removeAttribute('hidden'); try { ok.classList.remove('hidden'); } catch {} }
      }
    } catch (e) {
      const err = this.$('[data-error]');
      if (err) { err.removeAttribute('hidden'); try { err.classList.remove('hidden'); } catch {} }
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


