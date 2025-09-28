import { Component } from '@shared/ui/Component';
import { addToWishlist, removeFromWishlist, getWishlistCount } from '../api';

export interface WishlistToggleOptions { activeClass?: string }

export class WishlistToggle extends Component {
  private productId: number = 0;
  private activeClass: string = 'is-active';
  private labelEl: HTMLElement | null = null;

  init(): void {
    this.productId = Number(this.el.getAttribute('data-product-id') || 0);
    if (this.options && typeof this.options.activeClass === 'string') {
      this.activeClass = this.options.activeClass;
    }
    // Пытаемся найти текстовую метку внутри кнопки
    this.labelEl = this.el.querySelector('span');
    this.updateUi(this.el.classList.contains(this.activeClass));
    this.on('click', this.handleClick.bind(this));
  }

  private async handleClick(e: Event): Promise<void> {
    e.preventDefault();
    if (!this.productId) return;
    const isActive = this.el.classList.contains(this.activeClass);
    try {
      if (isActive) await removeFromWishlist(this.productId);
      else await addToWishlist(this.productId);
      this.el.classList.toggle(this.activeClass, !isActive);
      this.updateUi(!isActive);
      try {
        const count = await getWishlistCount().catch(() => undefined as unknown as number);
        window.dispatchEvent(new CustomEvent('wishlist:updated', { detail: { count } }));
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
  }

  destroy(): void {
    super.destroy();
  }
}


