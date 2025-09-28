import { WishlistToggle, type WishlistToggleOptions } from './ui/toggle';
import { getWishlistCount } from './api';

export function init(root: HTMLElement, opts: WishlistToggleOptions = {}): () => void {
  const c = new WishlistToggle(root, opts);
  c.init();
  // Если это список избранного — реактивно удаляем карточку и пересчитываем счётчик
  try {
    const removeOnUnwish = root.getAttribute('data-remove-on-unwish') === 'true';
    if (removeOnUnwish) {
      const grid = document.querySelector('[data-wishlist-grid]') as HTMLElement | null;
      const card = root.closest('[data-wishlist-card]') as HTMLElement | null;
      if (grid && card) {
        const onChanged = async (e: Event) => {
          const ce = e as CustomEvent;
          if (ce.detail?.action === 'removed') {
            // Этот обработчик подписан на каждую кнопку, проверим, что удаляется именно наш товар
            if (typeof ce.detail.productId === 'number' && ce.detail.productId === Number(root.getAttribute('data-product-id'))) {
              card.remove();
              try {
                const count = await getWishlistCount();
                window.dispatchEvent(new CustomEvent('wishlist:updated', { detail: { count } }));
              } catch {}
              if (grid.children.length === 0) {
                const empty = document.createElement('div');
                empty.className = 'mt-6 text-gray-500';
                empty.textContent = 'Список пуст. Добавьте товары из карточки товара.';
                grid.replaceWith(empty);
              }
              window.removeEventListener('wishlist:changed', onChanged as EventListener);
            }
          }
        };
        window.addEventListener('wishlist:changed', onChanged as EventListener);
      }
    }
  } catch {}
  return () => c.destroy();
}

export { addToWishlist, removeFromWishlist, getWishlist, getWishlistCount } from './api';
export { WishlistToggle };
export type { WishlistToggleOptions };


