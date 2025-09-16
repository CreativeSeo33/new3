import { Component } from '@shared/ui/Component';
import { updateCartItemQuantity, removeCartItem, getCart, getCartSummary } from '../api';
import { formatPrice } from '@shared/lib/formatPrice';
import { updateCartSummary } from '@shared/ui/updateCartSummary';
import type { Cart, CartDelta } from '@shared/types/api';
import { getCart as getFullCart } from '@features/add-to-cart/api';

export interface CartItemsManagerOptions {
  formatPrice?: (price: number) => string;
  useFullMode?: boolean; // Принудительно использовать полный режим вместо delta
  debugMode?: boolean; // Включить подробное логирование для отладки
  suppressItemNotFoundErrors?: boolean; // Подавлять ошибки "товар не найден"
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
    this.showSpinner();
    target.disabled = true;
    const originalValue = target.value;

    try {
      let resultData;

      // Проверяем, нужно ли использовать полный режим
      if (this.options.useFullMode) {
        const fullCartData = await getFullCart();
        resultData = {
          version: 0, // Не важно для отображения
          changedItems: fullCartData.items.map(item => ({
            id: Number(item.id),
            qty: item.qty,
            rowTotal: item.rowTotal,
            effectiveUnitPrice: item.effectiveUnitPrice
          })),
          removedItemIds: [],
          totals: {
            itemsCount: fullCartData.items.length,
            subtotal: fullCartData.subtotal,
            discountTotal: fullCartData.discountTotal,
            total: fullCartData.total
          }
        } as CartDelta;
      } else {
        // Используем оптимизированную функцию с delta ответом
        resultData = await updateCartItemQuantity(itemId, qty);

        // Проверяем, что получили корректные delta данные
        if (!resultData || typeof resultData !== 'object') {
          throw new Error('Invalid delta response received');
        }
      }

      // Находим строку товара и обновляем данные на основе delta
      const row = target.closest('tr') as HTMLTableRowElement;
      if (row) {
        this.updateRowDataFromDelta(row, resultData, itemId);
      }

      // Получаем полную корзину и обновляем все данные (субтотал, доставку, итого)
      this.updateAllFromFullCartAsync(resultData);

    } catch (error: any) {
      // Error updating item quantity

      // Восстанавливаем оригинальное значение
      target.value = originalValue;

      // Показываем ошибку
      this.showError('Не удалось обновить количество товара');

      // В случае ошибки пытаемся получить полную корзину для восстановления состояния
      try {
        const fullCartData = await getFullCart();
        // Обновляем UI с полными данными
        const row = target.closest('tr') as HTMLTableRowElement;
        if (row) {
          this.updateRowData(row, fullCartData, itemId);
        }
        this.updateTotalsFromFullCart(fullCartData);
        this.dispatchCartUpdatedEvent(fullCartData);
      } catch (fallbackError) {
        // Fallback update also failed
      }
    } finally {
      target.disabled = false;
      this.hideSpinner();
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
      console.warn('Remove button clicked but no itemId found');
      return;
    }

    // Проверяем, что ID является числом
    const numericId = parseInt(itemId, 10);
    if (isNaN(numericId) || numericId <= 0) {
      console.error('Invalid item ID:', itemId);
      this.showError('Некорректный ID товара');
      return;
    }

    if (this.options.debugMode) {
      console.log('Remove button clicked for item:', numericId, {
        itemId: itemId,
        button: target,
        timestamp: new Date().toISOString()
      });
    } else {
      console.log('Remove button clicked for item:', numericId);
    }

    // Выполняем удаление сразу без подтверждения
    await this.performRemoveItem(numericId, target);
  }



  /**
   * Выполняет удаление товара из корзины
   */
  private async performRemoveItem(numericId: number, button: HTMLElement): Promise<void> {
    // Показываем загрузку
    this.showSpinner();
    const originalText = button.textContent;
    button.textContent = 'Удаление...';
    button.setAttribute('disabled', 'true');

    try {
      console.log('Starting removal of item:', numericId);
      let resultData;

      // Проверяем, нужно ли использовать полный режим
      if (this.options.useFullMode) {
        console.log('Using full mode for cart removal (debug mode)');
        const fullCartData = await getFullCart();
        resultData = {
          version: 0, // Не важно для отображения
          changedItems: [],
          removedItemIds: [numericId],
          totals: {
            itemsCount: fullCartData.items.length,
            subtotal: fullCartData.subtotal,
            discountTotal: fullCartData.discountTotal,
            total: fullCartData.total
          }
        } as CartDelta;
      } else {
        // Используем оптимизированную функцию с delta ответом
        console.log('Sending remove request for item:', numericId);
        resultData = await removeCartItem(numericId);
        console.log('Item removal successful, delta data:', resultData);

        // Проверяем, что получили корректные delta данные
        if (!resultData || typeof resultData !== 'object' || !resultData.totals) {
          console.error('Invalid delta response received for removal:', resultData);
          throw new Error('Invalid delta response received for removal');
        }

        console.log('Delta data validation passed for item removal');
      }

      // Удаляем строку из DOM
      const row = button.closest('tr') as HTMLTableRowElement;
      if (row) {
        row.remove();
      }

      // Получаем полную корзину и обновляем все данные (асинхронно, не блокируем UI)
      this.updateAllFromFullCartAsync(resultData);

      // Если корзина пуста (проверяем по delta данным), перезагружаем страницу
      if (resultData.totals.itemsCount === 0) {
        window.location.reload();
        return;
      }

    } catch (error) {
      console.error('Error removing item:', error);
      console.error('Error details:', (error as any)?.message, (error as any)?.stack);

      // Проверяем, является ли ошибка "товар не найден"
      const errorMessage = (error as any)?.message || '';
      const responseData = (error as any)?.response?.data;
      const responseStatus = (error as any)?.response?.status || (error as any)?.status;

      const isItemNotFound = errorMessage.includes('cart_item_not_found') ||
                            errorMessage.includes('HTTP 404') ||
                            responseData?.error === 'cart_item_not_found' ||
                            responseStatus === 404;

      console.log('Error analysis:', {
        errorMessage,
        responseData,
        responseStatus,
        isItemNotFound
      });

      if (isItemNotFound) {
        console.warn('Item not found, it may have been already removed. Refreshing cart...', {
          itemId: numericId,
          error: error,
          userId: (window as any).userId || 'unknown'
        });

        // Показываем более понятное сообщение пользователю (если не подавлено)
        if (!this.options.suppressItemNotFoundErrors) {
          this.showError('Товар был удален из корзины ранее');
        }

        // Обновляем корзину, так как товар мог быть удален другим способом
        try {
          console.log('Refreshing cart data after item not found error...');
          const fullCartData = await getFullCart();
          console.log('Cart refreshed successfully, new item count:', fullCartData.items.length);

          this.updateTotal(fullCartData.total);
          this.dispatchCartUpdatedEvent(fullCartData);

          // Если товар все еще отображается в UI, скрываем его
          const row = button.closest('tr') as HTMLTableRowElement;
          if (row) {
            console.log('Removing item row from UI after 404 error');
            row.remove();
          }

          this.updateTotalsFromFullCart(fullCartData);

          // Показываем уведомление об успешном обновлении
          setTimeout(() => {
            this.showError('Корзина обновлена');
          }, 1500);

        } catch (refreshError) {
          console.error('Failed to refresh cart after item not found:', refreshError);
          this.showError('Не удалось обновить корзину');
        }
      } else {
        console.error('Unexpected error during item removal:', error);
        this.showError('Не удалось удалить товар из корзины');
      }
    } finally {
      button.textContent = originalText;
      button.removeAttribute('disabled');
      this.hideSpinner();
    }
  }


  /**
   * Обновляет данные строки товара
   */
  private updateRowData(row: HTMLTableRowElement, cartData: Cart, itemId: string): void {
    const item = cartData.items.find((i: any) => i.id === itemId);
    if (!item) {
      console.warn('Item not found in cart data:', itemId);
      return;
    }

    console.log('Updating row data for item:', itemId, {
      effectiveUnitPrice: item.effectiveUnitPrice,
      unitPrice: item.unitPrice,
      rowTotal: item.rowTotal
    });

    // Обновляем цену (колонка 3 - Цена)
    const priceCell = row.querySelector('td:nth-child(3)') as HTMLTableCellElement;
    const priceValue = item.effectiveUnitPrice || item.unitPrice;
    console.log('Price update debug:', {
      itemId,
      effectiveUnitPrice: item.effectiveUnitPrice,
      unitPrice: item.unitPrice,
      priceValue,
      priceValueType: typeof priceValue,
      isValid: priceValue !== undefined && priceValue !== null && !isNaN(priceValue)
    });

    if (priceCell && priceValue !== undefined && priceValue !== null && !isNaN(priceValue)) {
      const newPrice = this.options.formatPrice!(priceValue);
      console.log('Updating price cell from:', priceCell.textContent, 'to:', newPrice);
      priceCell.textContent = newPrice;
    } else {
      console.warn('Invalid price value for item:', itemId, { effectiveUnitPrice: item.effectiveUnitPrice, unitPrice: item.unitPrice });
    }

    // Обновляем сумму строки (колонка 5 - Сумма)
    const rowTotalCell = row.querySelector('td:nth-child(5)') as HTMLTableCellElement;
    if (rowTotalCell && item.rowTotal !== undefined && item.rowTotal !== null && !isNaN(item.rowTotal)) {
      const newRowTotal = this.options.formatPrice!(item.rowTotal);
      console.log('Updating row total cell from:', rowTotalCell.textContent, 'to:', newRowTotal);
      rowTotalCell.textContent = newRowTotal;
    } else {
      console.warn('Invalid rowTotal for item:', itemId, item.rowTotal);
    }
  }

  /**
   * Обновляет все суммы корзины на основе полных данных
   */
  private updateTotalsFromFullCart(cartData: Cart): void {
    // Единое обновление суммарных блоков
    updateCartSummary(cartData);
  }

  /**
   * Обновляет общую сумму корзины
   */
  private updateTotal(total: number): void {
    let totalEl = document.querySelector('[data-cart-total]');

    if (!totalEl) {
      // Fallback на ID если data-атрибут не найден
      totalEl = document.getElementById('cart-total');
    }

    if (totalEl && total !== undefined && total !== null && !isNaN(total)) {
      totalEl.textContent = this.options.formatPrice!(total);
    } else {
      console.warn('Invalid total value:', total, 'or element not found:', !!totalEl);
    }
  }

  /**
   * Отправляет событие обновления корзины
   */
  private dispatchCartUpdatedEvent(data: Cart): void {
    // Добавляем count для совместимости со Stimulus контроллером
    const eventDetail = {
      ...data,
      count: data.totalItemQuantity || (data.items || []).reduce((sum, item) => sum + (item.qty || 0), 0)
    };
    window.dispatchEvent(new CustomEvent('cart:updated', { detail: eventDetail }));
  }

  /**
   * Обновляет данные строки товара на основе delta ответа
   */
  private updateRowDataFromDelta(row: HTMLTableRowElement, deltaData: CartDelta, itemId: string): void {
    // Проверяем, что deltaData и changedItems существуют
    if (!deltaData || !deltaData.changedItems || !Array.isArray(deltaData.changedItems)) {
      console.warn('Invalid delta data received:', deltaData);
      return;
    }

    // Находим измененный товар в delta данных
    const changedItem = deltaData.changedItems.find((item: { id: number; qty: number; rowTotal: number; effectiveUnitPrice: number }) => {
      return item.id === Number(itemId);
    });

    if (!changedItem) {
      console.warn('Changed item not found in delta data:', itemId, deltaData.changedItems);
      return;
    }

    // Обновляем количество
    const qtyInput = row.querySelector('.qty-input') as HTMLInputElement;
    if (qtyInput) {
      qtyInput.value = changedItem.qty.toString();
    }

    // Обновляем цену (колонка 3 - Цена)
    const priceCell = row.querySelector('td:nth-child(3)') as HTMLTableCellElement;
    console.log('Delta price update debug:', {
      itemId,
      effectiveUnitPrice: changedItem.effectiveUnitPrice,
      rowTotal: changedItem.rowTotal,
      qty: changedItem.qty,
      effectiveUnitPriceType: typeof changedItem.effectiveUnitPrice,
      isValid: changedItem.effectiveUnitPrice !== undefined && changedItem.effectiveUnitPrice !== null && !isNaN(changedItem.effectiveUnitPrice)
    });

    if (priceCell && changedItem.effectiveUnitPrice !== undefined && changedItem.effectiveUnitPrice !== null && !isNaN(changedItem.effectiveUnitPrice)) {
      const formattedPrice = this.options.formatPrice!(changedItem.effectiveUnitPrice);
      console.log('Setting price cell to:', formattedPrice, 'from value:', changedItem.effectiveUnitPrice);
      priceCell.textContent = formattedPrice;
    } else {
      console.warn('Invalid effectiveUnitPrice for item:', itemId, changedItem.effectiveUnitPrice);
    }

    // Обновляем сумму строки
    const rowTotalCell = row.querySelector('.row-total') as HTMLTableCellElement;
    if (rowTotalCell && changedItem.rowTotal !== undefined && changedItem.rowTotal !== null && !isNaN(changedItem.rowTotal)) {
      rowTotalCell.textContent = this.options.formatPrice!(changedItem.rowTotal);
    } else {
      console.warn('Invalid rowTotal for item:', itemId, changedItem.rowTotal);
    }
  }

  /**
   * Обновляет субтотал на основе delta данных
   */
  private updateSubtotalFromDelta(deltaData: CartDelta): void {
    // Проверяем, что deltaData и totals существуют
    if (!deltaData || !deltaData.totals) {
      console.warn('Invalid delta data for subtotal update:', deltaData);
      return;
    }

    // Обновляем субтотал
    const subtotalEl = document.getElementById('cart-subtotal');
    if (subtotalEl && deltaData.totals.subtotal !== undefined && deltaData.totals.subtotal !== null && !isNaN(deltaData.totals.subtotal)) {
      subtotalEl.textContent = this.options.formatPrice!(deltaData.totals.subtotal);
    }
  }

  /**
   * Обновляет общую сумму на основе delta данных
   */
  private updateTotalFromDelta(deltaData: CartDelta): void {
    // Проверяем, что deltaData и totals существуют
    if (!deltaData || !deltaData.totals) {
      console.warn('Invalid delta data for totals update:', deltaData);
      return;
    }

    // Обновляем субтотал
    this.updateSubtotalFromDelta(deltaData);

    // Рассчитываем итого на клиенте: субтотал + стоимость доставки
    // Сначала получаем стоимость доставки из DOM
    const shippingEl = document.getElementById('cart-shipping');
    let shippingCost = 0;

    if (shippingEl && shippingEl.textContent) {
      // Парсим стоимость доставки из текста (убираем форматирование)
      const shippingText = shippingEl.textContent.replace(/[^\d.,]/g, '').replace(',', '.');
      shippingCost = parseFloat(shippingText) || 0;
    }

    // Рассчитываем итого
    const subtotal = deltaData.totals.subtotal || 0;
    const calculatedTotal = subtotal + shippingCost;

    // Обновляем итоговую сумму
    let totalEl = document.querySelector('[data-cart-total]');

    if (!totalEl) {
      // Fallback на ID если data-атрибут не найден
      totalEl = document.getElementById('cart-total');
    }


    if (totalEl) {
      totalEl.textContent = this.options.formatPrice!(calculatedTotal);
    }
  }

  /**
   * Асинхронно получает полную корзину и обновляет все данные + отправляет события
   */
  private async updateAllFromFullCartAsync(deltaData: CartDelta): Promise<void> {
    try {
      // Получаем полную корзину асинхронно (один запрос вместо двух)
      const fullCartData = await getFullCart();

      // Обновляем субтотал из полной корзины
      let subtotalEl = document.querySelector('[data-cart-subtotal]');

      if (!subtotalEl) {
        // Fallback на ID если data-атрибут не найден
        subtotalEl = document.getElementById('cart-subtotal');
      }


      if (subtotalEl && fullCartData.subtotal !== undefined && fullCartData.subtotal !== null && !isNaN(fullCartData.subtotal)) {
        const newText = this.options.formatPrice!(fullCartData.subtotal);
        subtotalEl.textContent = newText;
      } else {
        console.warn('Subtotal not updated:', {
          elementExists: !!subtotalEl,
          subtotal: fullCartData.subtotal,
          isValid: fullCartData.subtotal !== undefined && fullCartData.subtotal !== null && !isNaN(fullCartData.subtotal)
        });
      }

      // Обновляем стоимость доставки
      let shippingEl = document.querySelector('[data-cart-shipping]');

      if (!shippingEl) {
        // Fallback на ID если data-атрибут не найден
        shippingEl = document.getElementById('cart-shipping');
      }


      if (shippingEl && fullCartData.shipping?.cost !== undefined && fullCartData.shipping?.cost !== null && !isNaN(fullCartData.shipping.cost)) {
        shippingEl.textContent = this.options.formatPrice!(fullCartData.shipping.cost);
      } else {
        console.warn('Shipping element not found or invalid data:', {
          elementExists: !!shippingEl,
          shippingCost: fullCartData.shipping?.cost,
          isValid: fullCartData.shipping?.cost !== undefined && fullCartData.shipping?.cost !== null && !isNaN(fullCartData.shipping.cost)
        });
      }

      // Обновляем срок доставки
      let shippingTermEl = document.querySelector('[data-cart-shipping-term]');

      if (!shippingTermEl) {
        // Fallback на ID если data-атрибут не найден
        shippingTermEl = document.getElementById('cart-shipping-term');
      }


      if (shippingTermEl && fullCartData.shipping?.data?.term) {
        shippingTermEl.textContent = fullCartData.shipping.data.term;
      }

      // Пересчитываем итого на основе данных из полной корзины
      this.updateTotalFromFullCartData(fullCartData);

      // Отправляем событие обновления корзины
      this.dispatchCartUpdatedEvent(fullCartData);
    } catch (error) {
      console.warn('Failed to fetch full cart data for update:', error);
      // Fallback: обновляем субтотал из delta данных
      this.updateSubtotalFromDelta(deltaData);

      // Fallback: создаем минимальный объект для совместимости
      const minimalCartData = {
        id: 'cart',
        currency: 'RUB',
        subtotal: deltaData.totals.subtotal,
        discountTotal: deltaData.totals.discountTotal,
        total: deltaData.totals.total,
        shipping: { cost: 0 },
        items: [] // Пустой массив, так как у нас только delta данные
      };
      this.dispatchCartUpdatedEvent(minimalCartData);
    }
  }

  /**
   * Обновляет итоговую сумму на основе данных из полной корзины
   */
  private updateTotalFromFullCartData(cartData: Cart): void {
    // Делегируем в общий апдейтер
    updateCartSummary(cartData);
  }

  /**
   * Пересчитывает итоговую сумму на основе текущих данных в DOM
   */
  private recalculateTotalFromCurrentData(): void {
    const subtotalEl = document.getElementById('cart-subtotal');
    const shippingEl = document.getElementById('cart-shipping');
    const totalEl = document.getElementById('cart-total');

    if (!totalEl) return;

    let subtotal = 0;
    let shippingCost = 0;

    // Парсим субтотал
    if (subtotalEl && subtotalEl.textContent) {
      const subtotalText = subtotalEl.textContent.replace(/[^\d.,]/g, '').replace(',', '.');
      subtotal = parseFloat(subtotalText) || 0;
    }

    // Парсим стоимость доставки
    if (shippingEl && shippingEl.textContent && shippingEl.textContent !== 'Расчет менеджером') {
      const shippingText = shippingEl.textContent.replace(/[^\d.,]/g, '').replace(',', '.');
      shippingCost = parseFloat(shippingText) || 0;
    }

    // Рассчитываем и обновляем итого
    const calculatedTotal = subtotal + shippingCost;
    totalEl.textContent = this.options.formatPrice!(calculatedTotal);
  }

  /**
   * Показывает спиннер
   */
  private showSpinner(): void {
    const spinnerEl = document.getElementById('cart-spinner');
    if (spinnerEl) {
      // Прямое управление DOM вместо методов компонента
      spinnerEl.style.display = 'flex';
      const overlay = spinnerEl.querySelector('.spinner-overlay') as HTMLElement;
      if (overlay) {
        overlay.style.display = 'block';
      }
      console.log('Spinner shown via direct DOM manipulation');
    } else {
      console.warn('Spinner element not found');
    }
  }

  /**
   * Скрывает спиннер
   */
  private hideSpinner(): void {
    const spinnerEl = document.getElementById('cart-spinner');
    if (spinnerEl) {
      // Прямое управление DOM вместо методов компонента
      spinnerEl.style.display = 'none';
      const overlay = spinnerEl.querySelector('.spinner-overlay') as HTMLElement;
      if (overlay) {
        overlay.style.display = 'none';
      }
      console.log('Spinner hidden via direct DOM manipulation');
    } else {
      console.warn('Spinner element not found');
    }
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
