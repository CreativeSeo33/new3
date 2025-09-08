import { Component } from '@shared/ui/Component';
import { updateCartItemQuantity, removeCartItem, getCart, getCartSummary } from '../api';
import { formatPrice } from '@shared/lib/formatPrice';
import type { Cart, CartDelta } from '@shared/types/api';
import { getCart as getFullCart } from '@features/add-to-cart/api';

// Импортируем модальное окно
import { Modal } from '@shared/ui/modal-simple.js';

export interface CartItemsManagerOptions {
  formatPrice?: (price: number) => string;
  useFullMode?: boolean; // Принудительно использовать полный режим вместо delta
  debugMode?: boolean; // Включить подробное логирование для отладки
  suppressItemNotFoundErrors?: boolean; // Подавлять ошибки "товар не найден"
}

export class CartItemsManager extends Component {
  protected options: CartItemsManagerOptions;
  private removeModal: Modal | null = null;
  private pendingRemoveItem: { itemId: string; button: HTMLElement } | null = null;
  private confirmRemoveHandler: (() => void) | null = null;
  private cancelRemoveHandler: (() => void) | null = null;
  private modalClickHandler: ((e: Event) => void) | null = null;
  private modalInstanceId: string;

  constructor(el: HTMLElement, opts: CartItemsManagerOptions = {}) {
    super(el, opts);

    this.options = {
      formatPrice: formatPrice,
      ...opts
    };

    // Генерируем уникальный ID для этого экземпляра
    this.modalInstanceId = 'cartManager_' + Math.random().toString(36).substr(2, 9);

    // Создаем обработчики один раз
    this.confirmRemoveHandler = this.confirmRemove.bind(this);
    this.cancelRemoveHandler = this.cancelRemove.bind(this);

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
      let resultData;

      // Проверяем, нужно ли использовать полный режим
      if (this.options.useFullMode) {
        console.log('Using full mode for cart update (debug mode)');
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

      // Обновляем общую сумму из delta данных
      this.updateTotalFromDelta(resultData);

      // Отправляем событие обновления корзины с полными данными (для совместимости)
      // Получаем полную корзину асинхронно, но не блокируем UI
      this.dispatchCartUpdatedEventAsync(resultData);

    } catch (error: any) {
      console.error('Error updating item quantity:', error);

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
        this.updateTotal(fullCartData.total);
        this.dispatchCartUpdatedEvent(fullCartData);
      } catch (fallbackError) {
        console.error('Fallback update also failed:', fallbackError);
      }
    } finally {
      target.disabled = false;
    }
  }

  /**
   * Обработчик клика по кнопке удаления
   */
  private handleRemoveClick(e: Event): void {
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

    // Сохраняем информацию о товаре для удаления
    this.pendingRemoveItem = {
      itemId: String(numericId), // Сохраняем как строку для совместимости
      button: target
    };

    // Показываем модальное окно подтверждения
    this.showRemoveConfirmation();
  }

  /**
   * Показывает модальное окно подтверждения удаления
   */
  private showRemoveConfirmation(): void {
    if (!this.pendingRemoveItem) {
      return;
    }

    // Создаем модальное окно если его нет
    if (!this.removeModal) {
      this.removeModal = new Modal(this.el, {
        type: 'html',
        html: `
          <div class="text-center p-6">
            <div class="mb-4">
              <svg class="w-16 h-16 text-red-500 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/>
              </svg>
            </div>
            <h3 class="text-xl font-semibold text-gray-900 mb-2">Удалить товар из корзины?</h3>
            <p class="text-gray-600 mb-6">Это действие нельзя будет отменить</p>
            <div class="flex gap-3 justify-center">
              <button data-modal-action="confirm"
                      data-instance-id="${this.modalInstanceId}"
                      class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors">
                Да, удалить
              </button>
              <button data-modal-action="cancel"
                      data-instance-id="${this.modalInstanceId}"
                      class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 transition-colors">
                Отмена
              </button>
            </div>
          </div>
        `,
        width: 400,
        height: 280,
        closeOnOverlay: true,
        closeOnEscape: true,
        showCloseButton: false,
        onOpen: () => {
          // Используем MutationObserver для отслеживания появления модального окна
          const findModalContainer = () => {
            // Пробуем разные селекторы для поиска контейнера модального окна
            let modalContainer = document.querySelector('.fancybox__content');
            if (!modalContainer) {
              modalContainer = document.querySelector('.fancybox__slide');
            }
            if (!modalContainer) {
              modalContainer = document.querySelector('[data-fancybox-content]');
            }
            if (!modalContainer) {
              // Ищем контейнер по другим признакам Fancybox
              modalContainer = document.querySelector('.fancybox-container');
            }
            if (!modalContainer) {
              // Последняя попытка - ищем любой элемент с классом fancybox
              const fancyboxElements = document.querySelectorAll('[class*="fancybox"]');
              if (fancyboxElements.length > 0) {
                modalContainer = fancyboxElements[fancyboxElements.length - 1] as HTMLElement;
              }
            }
            return modalContainer;
          };

          // Проверяем сразу
          let modalContainer = findModalContainer();
          if (modalContainer) {
            console.log('Modal container found immediately');
            this.setupModalEventDelegation(modalContainer);
          } else {
            console.log('Modal container not found immediately, setting up observer...');

            // Настраиваем MutationObserver для отслеживания изменений в DOM
            const observer = new MutationObserver((mutations) => {
              modalContainer = findModalContainer();
              if (modalContainer) {
                console.log('Modal container found via observer');
                observer.disconnect(); // Прекращаем наблюдение
                this.setupModalEventDelegation(modalContainer);
              }
            });

            // Начинаем наблюдение за изменениями в body
            observer.observe(document.body, {
              childList: true,
              subtree: true
            });

            // Также проверяем через таймер на случай, если observer не сработает
            let retryCount = 0;
            const checkInterval = setInterval(() => {
              retryCount++;
              modalContainer = findModalContainer();
              if (modalContainer) {
                console.log('Modal container found via interval check');
                clearInterval(checkInterval);
                observer.disconnect();
                this.setupModalEventDelegation(modalContainer);
              } else if (retryCount > 50) { // Максимум 5 секунд
                console.error('Modal container not found after multiple attempts');
                clearInterval(checkInterval);
                observer.disconnect();
              }
            }, 100);
          }
        },
        onClose: () => {
          // Глобальные функции больше не используются
          console.log('Modal closing for instance:', this.modalInstanceId);
        }
      });
    }

    // Глобальные функции больше не нужны - используем делегирование событий

    this.removeModal.open();
  }

  /**
   * Настраивает делегирование событий для модального окна
   */
  private setupModalEventDelegation(modalContainer: Element): void {
    console.log('Setting up event delegation for modal:', this.modalInstanceId, 'container:', modalContainer);
    const handleModalClick = (e: Event) => {
      const target = e.target as HTMLElement;
      const action = target.getAttribute('data-modal-action');
      const instanceId = target.getAttribute('data-instance-id');

      if (action && instanceId === this.modalInstanceId) {
        e.preventDefault();
        console.log('Modal button clicked:', action, 'for instance:', instanceId);

        if (action === 'confirm' && this.confirmRemoveHandler) {
          this.confirmRemoveHandler();
        } else if (action === 'cancel' && this.cancelRemoveHandler) {
          this.cancelRemoveHandler();
        }
      }
    };

    modalContainer.addEventListener('click', handleModalClick);
    console.log('Event delegation set up for modal container');

    // Сохраняем ссылку на обработчик для возможного удаления
    (modalContainer as any)._cartManagerClickHandler = handleModalClick;
  }

  /**
   * Подтверждает удаление товара
   */
  private async confirmRemove(): Promise<void> {
    console.log('confirmRemove called with instance:', this.modalInstanceId);
    console.log('Available global functions:', Object.keys(window).filter(key => key.includes('cartManager')));

    if (!this.pendingRemoveItem) {
      console.log('No pending remove item');
      return;
    }

    const { itemId, button } = this.pendingRemoveItem;
    console.log('Removing item:', itemId, 'with button:', button);

    // Дополнительная проверка ID перед отправкой
    const numericId = parseInt(itemId, 10);
    if (isNaN(numericId) || numericId <= 0) {
      console.error('Invalid item ID for removal:', itemId);
      this.showError('Некорректный ID товара для удаления');

      // Закрываем модальное окно
      setTimeout(() => {
        if (this.removeModal) {
          this.removeModal.close();
        }
        this.pendingRemoveItem = null;
      }, 500);
      return;
    }

    // Показываем загрузку
    const originalText = button.textContent;
    button.textContent = 'Удаление...';
    button.setAttribute('disabled', 'true');

    // Не закрываем модальное окно сразу, чтобы пользователь видел процесс

    try {
      console.log('Starting removal of item:', itemId);
      let resultData;

      // Проверяем, нужно ли использовать полный режим
      if (this.options.useFullMode) {
        console.log('Using full mode for cart removal (debug mode)');
        const fullCartData = await getFullCart();
        resultData = {
          version: 0, // Не важно для отображения
          changedItems: [],
          removedItemIds: [Number(itemId)],
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

      // Обновляем общую сумму из delta данных
      this.updateTotalFromDelta(resultData);

      // Отправляем событие обновления корзины асинхронно (для совместимости)
      this.dispatchCartUpdatedEventAsync(resultData);

      // Если корзина пуста (проверяем по delta данным), перезагружаем страницу
      if (resultData.totals.itemsCount === 0) {
        window.location.reload();
        return; // Не закрываем окно, так как страница перезагрузится
      }

      // Небольшая задержка перед закрытием окна
      setTimeout(() => {
        console.log('Closing modal after successful removal');
        // Сначала очищаем функции
        delete (window as any)[`cartManagerConfirmRemove_${this.modalInstanceId}`];
        delete (window as any)[`cartManagerCancelRemove_${this.modalInstanceId}`];
        console.log('Functions cleaned up before closing modal');

        // Потом закрываем модальное окно
        if (this.removeModal) {
          this.removeModal.close();
        }
        this.pendingRemoveItem = null;
      }, 300);

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
          itemId: itemId,
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

      // В случае ошибки закрываем окно через небольшую задержку
      setTimeout(() => {
        console.log('Closing modal due to error');
        if (this.removeModal) {
          this.removeModal.close();
        }
        this.pendingRemoveItem = null;
      }, 1000);
    } finally {
      button.textContent = originalText;
      button.removeAttribute('disabled');
    }
  }

  /**
   * Отменяет удаление товара
   */
  private cancelRemove(): void {
    // Небольшая задержка перед закрытием для лучшего UX
    setTimeout(() => {
      if (this.removeModal) {
        this.removeModal.close();
      }
      this.pendingRemoveItem = null;
    }, 100);
  }

  /**
   * Обновляет данные строки товара
   */
  private updateRowData(row: HTMLTableRowElement, cartData: Cart, itemId: string): void {
    const item = cartData.items.find((i: any) => i.id === itemId);
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

    // Обновляем цену (с учетом опций)
    const priceCell = row.querySelector('td:nth-child(2)') as HTMLTableCellElement;
    if (priceCell) {
      priceCell.textContent = this.options.formatPrice!(changedItem.effectiveUnitPrice);
    }

    // Обновляем сумму строки
    const rowTotalCell = row.querySelector('.row-total') as HTMLTableCellElement;
    if (rowTotalCell) {
      rowTotalCell.textContent = this.options.formatPrice!(changedItem.rowTotal);
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

    const totalEl = document.getElementById('cart-total');
    if (totalEl) {
      totalEl.textContent = this.options.formatPrice!(deltaData.totals.total);
    }
  }

  /**
   * Асинхронно отправляет событие обновления корзины с полными данными
   * для совместимости с существующими компонентами
   */
  private async dispatchCartUpdatedEventAsync(deltaData: CartDelta): Promise<void> {
    try {
      // Получаем полную корзину асинхронно
      const fullCartData = await getFullCart();
      this.dispatchCartUpdatedEvent(fullCartData);
    } catch (error) {
      console.warn('Failed to fetch full cart data for event, using delta data:', error);
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
    // Уничтожаем модальное окно если оно создано
    if (this.removeModal) {
      this.removeModal.destroy();
      this.removeModal = null;
    }

    // Очищаем pending item
    this.pendingRemoveItem = null;

    // Очищаем обработчики
    this.confirmRemoveHandler = null;
    this.cancelRemoveHandler = null;
    this.modalClickHandler = null;

    // Очистка ресурсов происходит в базовом классе Component
    super.destroy();
  }
}
