// ai:http-client exports=getWishlist,getWishlistCount,addToWishlist,removeFromWishlist
import { get, post, del } from '@shared/api/http';

export interface WishlistItemDto { id: number; name: string; price: number; image?: string | null; slug?: string | null }
export interface WishlistListDto { count: number; items: WishlistItemDto[] }

export async function getWishlist(): Promise<WishlistListDto> {
  return get<WishlistListDto>('/api/wishlist', { headers: { Accept: 'application/json' } });
}

export async function getWishlistCount(): Promise<number> {
  const res = await get<{ count: number }>('/api/wishlist/count', { headers: { Accept: 'application/json' } });
  return typeof res?.count === 'number' ? res.count : 0;
}

export async function addToWishlist(productId: number): Promise<void> {
  await post('/api/wishlist/items', { productId }, { headers: { Accept: 'application/json' } });
}

export async function removeFromWishlist(productId: number): Promise<void> {
  await del(`/api/wishlist/items/${productId}`, { headers: { Accept: 'application/json' } });
}


