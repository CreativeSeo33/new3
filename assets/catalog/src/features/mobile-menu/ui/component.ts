import { Component } from '@shared/ui/Component';

export interface MobileMenuOptions {}

export class MobileMenuComponent extends Component {
  private toggleBtn: HTMLElement | null = null;
  private closeBtn: HTMLElement | null = null;
  private menu: HTMLElement | null = null;

  init(): void {
    this.toggleBtn = document.getElementById('mobile-menu-toggle');
    this.closeBtn = document.getElementById('mobile-menu-close');
    this.menu = document.getElementById('mobile-menu');

    if (this.toggleBtn && this.menu) {
      this.toggleBtn.addEventListener('click', this.openMenu);
    }
    if (this.closeBtn && this.menu) {
      this.closeBtn.addEventListener('click', this.closeMenu);
    }
    if (this.menu) {
      this.menu.addEventListener('click', this.onMenuBackdrop);
    }
  }

  private openMenu = (): void => {
    if (!this.menu) return;
    this.menu.classList.remove('hidden');
    document.body.classList.add('overflow-hidden');
  };

  private closeMenu = (): void => {
    if (!this.menu) return;
    this.menu.classList.add('hidden');
    document.body.classList.remove('overflow-hidden');
  };

  private onMenuBackdrop = (e: Event): void => {
    if (!this.menu) return;
    if (e.target === this.menu) this.closeMenu();
  };

  destroy(): void {
    if (this.toggleBtn) this.toggleBtn.removeEventListener('click', this.openMenu);
    if (this.closeBtn) this.closeBtn.removeEventListener('click', this.closeMenu);
    if (this.menu) this.menu.removeEventListener('click', this.onMenuBackdrop);
    super.destroy();
  }
}


