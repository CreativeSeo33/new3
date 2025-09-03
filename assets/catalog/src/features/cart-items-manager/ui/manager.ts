import { Component } from '@shared/ui/Component';
import { updateCartItemQuantity, removeCartItem, getCart } from '../api';
import { formatPrice } from '@shared/lib/formatPrice';
import type { Cart } from '@shared/types/api';

export interface CartItemsManagerOptions {
  formatPrice?: (price: number) => string;
}

export class CartItemsManager extends Component {
  protected options: CartItemsManagerOptions;

  constructor(el: HTMLElement, opts: CartItemsManagerOptions = {}) {
    super(el, opts);

    this.options = {
      formatPrice: formatPrice,
      ...opts
    };

    this.init();
  }

  init(): void {
    // Делегируем обработчики событий на контейнер
    this.on('change', this.handleQuantityChange.bind(this), { passive: true });
    this.on('click', this.handleRemoveClick.bind(this), { passive: true });
  }

  /**
   * Обработчик изменения количества товара
   */
  private async handleQuantityChange(e: Event): Promise<void> {
    const target = e.target as HTMLInputElement;

    if (!target.classList.contains('qty-input')) {
      return;
    }

    const itemId = target.dataset.itemId;
    const qty = parseInt(target.value, 10);

    if (!itemId || qty <= 0) {
      return;
    }

    // Показываем загрузку
    target.disabled = true;
    const originalValue = target.value;

    try {
      const cartData = await updateCartItemQuantity(itemId, qty);

      // Находим строку товара и обновляем данные
      const row = target.closest('tr') as HTMLTableRowElement;
      if (row) {
        this.updateRowData(row, cartData, itemId);
      }

      // Обновляем общую сумму
      this.updateTotal(cartData.total);

      // Отправляем событие обновления корзины
      this.dispatchCartUpdatedEvent(cartData);

    } catch (error: any) {
      console.error('Error updating item quantity:', error);

      // Восстанавливаем оригинальное значение
      target.value = originalValue;

      // Показываем ошибку
      this.showError('Не удалось обновить количество товара');
    } finally {
      target.disabled = false;
    }
  }

  /**
   * Обработчик клика по кнопке удаления
   */
  private async handleRemoveClick(e: Event): Promise<void> {
    const target = e.target as HTMLElement;

    if (!target.classList.contains('remove')) {
      return;
    }

    const itemId = target.dataset.itemId;
    if (!itemId) {
      return;
    }

    // Подтверждаем удаление
    if (!confirm('Вы уверены, что хотите удалить этот товар из корзины?')) {
      return;
    }

    // Показываем загрузку
    const originalText = target.textContent;
    target.textContent = 'Удаление...';
    target.setAttribute('disabled', 'true');

    try {
      const cartData = await removeCartItem(itemId);

      // Удаляем строку из DOM
      const row = target.closest('tr') as HTMLTableRowElement;
      if (row) {
        row.remove();
      }

      // Обновляем общую сумму
      this.updateTotal(cartData.total);

      // Отправляем событие обновления корзины
      this.dispatchCartUpdatedEvent(cartData);

      // Если корзина пуста, перезагружаем страницу
      if (cartData.items.length === 0) {
        window.location.reload();
      }

    } catch (error) {
      console.error('Error removing item:', error);
      this.showError('Не удалось удалить товар из корзины');
    } finally {
      target.textContent = originalText;
      target.removeAttribute('disabled');
    }
  }

  /**
   * Обновляет данные строки товара
   */
  private updateRowData(row: HTMLTableRowElement, cartData: Cart, itemId: string): void {
    const item = cartData.items.find((i: any) => i.id.toString() === itemId);
    if (!item) {
      return;
    }

    // Обновляем цену (с учетом опций)
    const priceCell = row.querySelector('td:nth-child(2)') as HTMLTableCellElement;
    if (priceCell) {
      priceCell.textContent = this.options.formatPrice!(item.effectiveUnitPrice || item.unitPrice);
    }

    // Обновляем сумму строки
    const rowTotalCell = row.querySelector('.row-total') as HTMLTableCellElement;
    if (rowTotalCell) {
      rowTotalCell.textContent = this.options.formatPrice!(item.rowTotal);
    }
  }

  /**
   * Обновляет общую сумму корзины
   */
  private updateTotal(total: number): void {
    const totalEl = document.getElementById('cart-total');
    if (totalEl) {
      totalEl.textContent = this.options.formatPrice!(total);
    }
  }

  /**
   * Отправляет событие обновления корзины
   */
  private dispatchCartUpdatedEvent(data: Cart): void {
    window.dispatchEvent(new CustomEvent('cart:updated', { detail: data }));
  }

  /**
   * Показывает сообщение об ошибке
   */
  private showError(message: string): void {
    // В будущем можно заменить на toast уведомления
    alert(message);
  }

  /**
   * Публичный метод для программного обновления количества
   */
  public async updateQuantity(itemId: string | number, qty: number): Promise<void> {
    const input = this.$(`[data-item-id="${itemId}"].qty-input`) as HTMLInputElement;
    if (input) {
      input.value = qty.toString();
      input.dispatchEvent(new Event('change', { bubbles: true }));
    }
  }

  /**
   * Публичный метод для программного удаления товара
   */
  public async removeItem(itemId: string | number): Promise<void> {
    const button = this.$(`[data-item-id="${itemId}"].remove`) as HTMLElement;
    if (button) {
      button.click();
    }
  }

  destroy(): void {
    // Очистка ресурсов происходит в базовом классе Component
    super.destroy();
  }
}
