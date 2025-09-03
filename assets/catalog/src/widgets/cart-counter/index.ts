import { Component } from '@shared/ui/Component';
import { formatPrice } from '@shared/lib/formatPrice';
import type { CartUpdatedDetail } from '@shared/types/events';

/**
 * Опции для виджета счетчика корзины
 */
interface CartCounterOptions {
  defaultCount?: number;
  defaultTotal?: number;
  currency?: string;
}

/**
 * Виджет счетчика корзины в шапке
 */
export class CartCounter extends Component {
  private counterEl: HTMLElement | null;
  private totalEl: HTMLElement | null;
  private labelEl: HTMLElement | null;
  private counterOptions: CartCounterOptions;

  constructor(el: HTMLElement, opts: CartCounterOptions = {}) {
    super(el, opts);

    this.counterOptions = opts;
    this.counterEl = this.$('[data-cart-counter]');
    this.totalEl = this.$('[data-cart-total]');
    this.labelEl = this.$('[data-cart-label]');

    this.init();
  }

  init(): void {
    // Слушаем событие обновления корзины
    window.addEventListener('cart:updated' as any, this.handleCartUpdate.bind(this));

    // Инициализируем с текущими данными
    this.updateDisplay();
  }

  /**
   * Обработчик обновления корзины
   */
  private handleCartUpdate(e: CustomEvent<CartUpdatedDetail>): void {
    const data = e.detail;
    this.updateDisplay(data);
  }

  /**
   * Обновляет отображение счетчика корзины
   */
  private updateDisplay(data?: CartUpdatedDetail): void {
    try {
      let count = this.counterOptions.defaultCount || 0;
      let total = this.counterOptions.defaultTotal || 0;
      let currency = this.counterOptions.currency || 'RUB';

      if (data) {
        count = (data.items || []).length;
        total = (data.total || 0) / 100; // Предполагаем, что total в копейках
        currency = data.currency || 'RUB';
      }

      // Обновляем счетчик товаров
      if (this.counterEl) {
        this.counterEl.textContent = String(count);
      }

      // Обновляем общую сумму
      if (this.totalEl) {
        this.totalEl.textContent = formatPrice(total, false);
      }

      // Обновляем лейбл
      if (this.labelEl) {
        this.labelEl.textContent = `${count} товар(ов)`;
      }
    } catch (error) {
      console.error('Error updating cart counter:', error);
    }
  }

  /**
   * Получает текущий счетчик товаров
   */
  getCurrentCount(): number {
    return parseInt(this.counterEl?.textContent || '0', 10);
  }

  /**
   * Получает текущую сумму
   */
  getCurrentTotal(): number {
    const text = this.totalEl?.textContent || '0';
    return parseFloat(text.replace(/[^\d.,]/g, '').replace(',', '.')) || 0;
  }

  destroy(): void {
    // Удаляем слушатель событий
    window.removeEventListener('cart:updated' as any, this.handleCartUpdate.bind(this));

    super.destroy();
  }
}

/**
 * Инициализирует виджет счетчика корзины
 */
export function init(
  root: HTMLElement,
  opts: CartCounterOptions = {}
): () => void {
  const counter = new CartCounter(root, opts);
  return () => counter.destroy();
}
