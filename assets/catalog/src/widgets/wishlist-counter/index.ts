import { Component } from '@shared/ui/Component';
import { getWishlistCount } from '@features/wishlist/api';

export interface WishlistCounterOptions {}

export class WishlistCounter extends Component {
  private counterEl: HTMLElement | null = null;

  init(): void {
    this.counterEl = this.$('[data-wishlist-counter]');
    this.refresh();
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
      let count = await getWishlistCount();
      if (count === 0) {
        try { count = await getWishlistCount(); } catch {}
      }
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
  return () => c.destroy();
}


