import { Component } from '@shared/ui/Component';
import { addToWishlist, removeFromWishlist, getWishlistCount } from '../api';
import { Spinner } from '@shared/ui/spinner';

export interface WishlistToggleOptions { activeClass?: string }

export class WishlistToggle extends Component {
  private productId: number = 0;
  private activeClass: string = 'is-active';
  private labelEl: HTMLElement | null = null;
  private iconEl: SVGElement | null = null;
  private spinnerHost: HTMLElement | null = null;
  private spinner: Spinner | null = null;

  init(): void {
    this.productId = Number(this.el.getAttribute('data-product-id') || 0);
    if (this.options && typeof this.options.activeClass === 'string') {
      this.activeClass = this.options.activeClass;
    }
    // Пытаемся найти текстовую метку и/или иконку внутри кнопки
    this.labelEl = this.el.querySelector('span');
    this.iconEl = this.el.querySelector('svg');
    this.updateUi(this.el.classList.contains(this.activeClass));
    this.on('click', this.handleClick.bind(this));
  }

  private async handleClick(e: Event): Promise<void> {
    e.preventDefault();
    if (!this.productId) return;
    const isActive = this.el.classList.contains(this.activeClass);
    try {
      // Показать спиннер поверх кнопки (если не отключён data-spinner="false")
      const spinnerAttr = (this.el.getAttribute('data-spinner') || '').toLowerCase();
      const disableSpinner = spinnerAttr === 'false' || spinnerAttr === '0' || spinnerAttr === 'no';
      if (!disableSpinner) {
        try {
          this.spinnerHost = this.el.querySelector('.wishlist-spinner');
          if (!this.spinnerHost) {
            this.spinnerHost = document.createElement('div');
            this.spinnerHost.className = 'wishlist-spinner absolute inset-0 flex items-center justify-center';
            (this.el as HTMLElement).style.position = this.el.style.position || 'relative';
            this.el.appendChild(this.spinnerHost);
          }
          this.spinner = new Spinner(this.spinnerHost as HTMLElement, { overlay: false, visible: true, size: 'small' });
          this.spinner.show();
        } catch {}
      }

      if (isActive) await removeFromWishlist(this.productId);
      else await addToWishlist(this.productId);
      this.el.classList.toggle(this.activeClass, !isActive);
      this.updateUi(!isActive);
      try {
        const count = await getWishlistCount().catch(() => undefined as unknown as number);
        window.dispatchEvent(new CustomEvent('wishlist:updated', { detail: { count } }));
        window.dispatchEvent(new CustomEvent('wishlist:changed', { detail: { productId: this.productId, action: isActive ? 'removed' : 'added' } }));
        if (typeof count === 'number') {
          try {
            document.querySelectorAll<HTMLElement>('[data-wishlist-counter]').forEach(el => {
              el.textContent = String(count);
            });
          } catch {}
        }
      } catch {}
    } catch (err) {
      console.error('Wishlist toggle error', err);
    } finally {
      try { this.spinner?.hide(); } catch {}
    }
  }

  private updateUi(active: boolean): void {
    // Обновляем текст только для стандартной кнопки, чтобы не ломать кастомные подписи
    if (this.labelEl) {
      const current = (this.labelEl.textContent || '').trim();
      const isStandard = current === 'В избранное' || current === 'Товар в избранном';
      if (isStandard) {
        this.labelEl.textContent = active ? 'Товар в избранном' : 'В избранное';
      }
      // Цвет текста: красный когда в избранном
      this.labelEl.classList.toggle('text-red-600', active);
    }

    // Обновляем цвет иконки (если присутствует)
    if (this.iconEl) {
      this.iconEl.classList.toggle('text-red-600', active);
      this.iconEl.classList.toggle('text-gray-600', !active);
    }
  }

  destroy(): void {
    super.destroy();
  }
}


