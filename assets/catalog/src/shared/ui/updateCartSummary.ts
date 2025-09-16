// ai:base-component name=updateCartSummary purpose=DOM/events
import type { Cart } from '@shared/types/api';
import { formatPrice } from '@shared/lib/formatPrice';

/**
 * Единая функция обновления блоков суммы корзины на странице.
 * Источник истины — объект Cart с бэкенда (/api/cart).
 */
export function updateCartSummary(cart: Cart): void {
  try {
    // subtotal
    const subtotalEl = document.getElementById('cart-subtotal')
      || (document.querySelector('[data-cart-subtotal]') as HTMLElement | null);
    if (subtotalEl && isFiniteNumber(cart.subtotal)) {
      subtotalEl.textContent = formatPrice(cart.subtotal);
    }

    // shipping cost
    const shippingEl = document.getElementById('cart-shipping')
      || (document.querySelector('[data-cart-shipping]') as HTMLElement | null);
    const shippingCost = cart?.shipping?.cost;
    if (shippingEl) {
      if (isFiniteNumber(shippingCost)) {
        shippingEl.textContent = formatPrice(shippingCost as number);
      } else {
        shippingEl.textContent = 'Расчет менеджером';
      }
    }

    // shipping term
    const termEl = document.getElementById('cart-shipping-term')
      || (document.querySelector('[data-cart-shipping-term]') as HTMLElement | null);
    const term = (cart as any)?.shipping?.data?.term;
    if (termEl) {
      if (typeof term === 'string' && term.length > 0) {
        termEl.textContent = term;
        termEl.classList.remove('hidden');
      } else {
        termEl.textContent = '';
        termEl.classList.add('hidden');
      }
    }

    // total = subtotal + shipping
    const totalEl = document.getElementById('cart-total')
      || (document.querySelector('[data-cart-total]') as HTMLElement | null);
    if (totalEl) {
      const subtotal = isFiniteNumber(cart.subtotal) ? (cart.subtotal as number) : 0;
      const ship = isFiniteNumber(shippingCost) ? (shippingCost as number) : 0;
      totalEl.textContent = formatPrice(subtotal + ship);
    }
  } catch (_) {
    // молча игнорируем для безопасности UI
  }
}

function isFiniteNumber(value: unknown): value is number {
  return typeof value === 'number' && Number.isFinite(value);
}


