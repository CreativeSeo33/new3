import { Component } from '@shared/ui/Component';
import { getWishlistCount } from '@features/wishlist/api';

export interface WishlistCounterOptions {}

export class WishlistCounter extends Component {
  private counterEl: HTMLElement | null = null;

  init(): void {
    this.counterEl = this.$('[data-wishlist-counter]');
    // Первичная инициализация: читаем значение из DOM (рендерится Twig через wishlist_count())
    // Не выполняем сетевой запрос при первой загрузке страницы
    const initial = this.counterEl?.textContent?.trim();
    if (initial && this.counterEl) {
      this.counterEl.textContent = initial;
    }
    window.addEventListener('wishlist:updated', (e: Event) => {
      const ce = e as CustomEvent;
      const count = typeof ce?.detail?.count === 'number' ? ce.detail.count : undefined;
      if (typeof count === 'number') {
        if (this.counterEl) this.counterEl.textContent = String(count);
      } else {
        this.refresh();
      }
    });
  }

  private refresh = async (): Promise<void> => {
    try {
      const count = await getWishlistCount();
      if (this.counterEl) this.counterEl.textContent = String(count);
    } catch {}
  };

  destroy(): void {
    // nothing to remove for custom inline listener in this simple impl
    super.destroy();
  }
}

export function init(root: HTMLElement): () => void {
  const c = new WishlistCounter(root);
  c.init();
  return () => c.destroy();
}


