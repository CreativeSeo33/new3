import { Component } from '@shared/ui/Component';
import { formatPrice } from '@shared/lib/formatPrice';
import type { CartUpdatedDetail } from '@shared/types/events';

/**
 * Summary данные корзины для быстрого обновления
 */
interface CartSummaryData {
  version: number;
  itemsCount: number;
  subtotal: number;
  discountTotal: number;
  total: number;
}

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
  private handleCartUpdate(e: CustomEvent<CartUpdatedDetail | CartSummaryData>): void {
    const data = e.detail;
    this.updateDisplay(data);
  }

  /**
   * Обновляет отображение счетчика корзины
   */
  private updateDisplay(data?: CartUpdatedDetail | CartSummaryData): void {
    try {
      let count = this.counterOptions.defaultCount || 0;
      let total = this.counterOptions.defaultTotal || 0;
      let currency = this.counterOptions.currency || 'RUB';

      if (data) {
        // Определяем тип данных и извлекаем нужную информацию
        if ('items' in data) {
          // Полные данные корзины
          count = (data.items || []).length;
          total = (data.total || 0) / 100; // Предполагаем, что total в копейках
          currency = data.currency || 'RUB';
        } else if ('itemsCount' in data) {
          // Summary данные
          count = data.itemsCount;
          total = (data.total || 0) / 100; // Предполагаем, что total в копейках
          currency = 'RUB'; // Для summary данных используем RUB по умолчанию
        }
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

  /**
   * Быстро обновляет счетчик с использованием summary данных
   */
  async updateFromSummary(): Promise<void> {
    try {
      const response = await fetch('/api/cart', {
        headers: {
          'Prefer': 'return=representation; profile="cart.summary"'
        }
      });

      if (!response.ok) {
        throw new Error('Failed to fetch cart summary');
      }

      const summaryData: CartSummaryData = await response.json();
      this.updateDisplay(summaryData);
    } catch (error) {
      console.error('Error updating cart counter from summary:', error);
      // Fallback: пробуем получить полную корзину
      try {
        const response = await fetch('/api/cart');
        if (response.ok) {
          const fullData = await response.json();
          this.updateDisplay(fullData);
        }
      } catch (fallbackError) {
        console.error('Fallback update also failed:', fallbackError);
      }
    }
  }

  /**
   * Обновляет счетчик на основе delta данных (оптимизированный способ)
   */
  updateFromDelta(deltaData: { totals: { itemsCount: number; subtotal: number; discountTotal: number; total: number } }): void {
    const summaryLikeData: CartSummaryData = {
      version: 0, // Не важен для отображения
      itemsCount: deltaData.totals.itemsCount,
      subtotal: deltaData.totals.subtotal,
      discountTotal: deltaData.totals.discountTotal,
      total: deltaData.totals.total
    };
    this.updateDisplay(summaryLikeData);
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
