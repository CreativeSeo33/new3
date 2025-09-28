import { WishlistToggle, type WishlistToggleOptions } from './ui/toggle';

export function init(root: HTMLElement, opts: WishlistToggleOptions = {}): () => void {
  const c = new WishlistToggle(root, opts);
  c.init();
  return () => c.destroy();
}

export { addToWishlist, removeFromWishlist, getWishlist, getWishlistCount } from './api';
export { WishlistToggle };
export type { WishlistToggleOptions };


