import { Component } from '@shared/ui/Component';
import { addToCart } from '../api';
import { formatPrice } from '@shared/lib/formatPrice';
import type { Cart } from '@shared/types/api';

// Импортируем модальное окно
import { Modal } from '@shared/ui/modal-simple.js';

/**
 * Опции компонента кнопки добавления в корзину
 */
interface AddToCartButtonOptions {
  productId?: number;
  qty?: number;
  quantitySelector?: HTMLElement;
}

/**
 * Компонент кнопки добавления в корзину
 */
export class AddToCartButton extends Component {
  private productId: number;
  private qty: number;
  private quantitySelector: HTMLElement | null = null;
  private loadingSpinner: HTMLElement | null;
  private mainContent: HTMLElement | null;
  private originalContent: string;
  private isProcessing: boolean = false;
  private successModal: Modal | null = null;
  private clickHandlerBound: boolean = false;
  private currentIdempotencyKey: string | null = null;

  constructor(el: HTMLElement, opts: AddToCartButtonOptions = {}) {
    super(el, opts);

    this.productId = this.dataset.int('productId', opts.productId);
    this.qty = this.dataset.int('quantity', opts.qty ?? 1);
    this.quantitySelector = opts.quantitySelector || this.findQuantitySelector();

    this.loadingSpinner = this.$('.loading-spinner');
    this.mainContent = this.$('.relative');
    this.originalContent = this.mainContent?.innerHTML ?? '';

    if (!this.productId) {
      return;
    }

    this.init();
  }

  init(): void {
    // Регистрируем обработчик только один раз
    if (!this.clickHandlerBound) {
      this.on('click', this.handleClick.bind(this));
      this.clickHandlerBound = true;
    }

    // Слушаем изменения количества
    if (this.quantitySelector) {
      this.quantitySelector.addEventListener('quantity:change', ((e: Event) => this.handleQuantityChange(e as CustomEvent)) as EventListener);
    }
  }

  /**
   * Находит элемент quantity-selector на странице
   */
  private findQuantitySelector(): HTMLElement | null {
    const form = this.el.closest('form') || this.el.closest('[data-module="product-options"]')?.parentElement;
    if (form) {
      return form.querySelector('[data-module="quantity-selector"]') as HTMLElement;
    }
    return document.querySelector('[data-module="quantity-selector"]') as HTMLElement;
  }

  /**
   * Обработчик изменения количества
   */
  private handleQuantityChange(e: CustomEvent): void {
    const newQuantity = e.detail?.value;
    if (typeof newQuantity === 'number' && newQuantity >= 1) {
      this.qty = newQuantity;
      this.el.setAttribute('data-quantity', newQuantity.toString());
    }
  }

  /**
   * Обработчик клика по кнопке
   */
  private async handleClick(e: Event): Promise<void> {
    // Проверяем, не уничтожен ли компонент
    if (this.isDestroyed()) {
      return;
    }

    e.preventDefault();
    e.stopImmediatePropagation(); // Предотвращаем множественные срабатывания

    const button = this.el as HTMLButtonElement;

    // Дополнительная проверка на disabled
    if (button.disabled) {
      return;
    }

    // Проверяем, не обрабатывается ли уже запрос
    if (this.isProcessing) {
      return;
    }

    // Устанавливаем флаг обработки
    this.isProcessing = true;

    // Проверяем и блокируем кнопку как можно раньше
    if (button.disabled) {
      // Button is already disabled, but continuing due to processing flag
    }

    // Немедленно блокируем кнопку
    button.disabled = true;

    // Собираем выбранные опции из формы
    const optionAssignmentIds = this.getSelectedOptions();

    // Показываем состояние загрузки
    this.showLoading();

    try {
      // Получаем ключ попытки (сохраняется между ретраями)
      const idempotencyKey = this.getAttemptKey();

      // Добавляем товар в корзину (всегда запрашиваем полный ответ для UI)
      const cartData = await addToCart(this.productId, this.qty, optionAssignmentIds, {
        responseMode: 'full',
        idempotencyKey
      }) as Cart;

      // Отправляем событие обновления корзины с count для совместимости
      const eventDetail = {
        ...cartData,
        count: cartData.totalItemQuantity || (cartData.items || []).reduce((sum, item) => sum + (item.qty || 0), 0),
        preventRefresh: true
      };
      window.dispatchEvent(new CustomEvent('cart:updated', { detail: eventDetail }));

      // Показываем успешное состояние
      this.showSuccess();

      // Возвращаем оригинальное состояние через 2 секунды
      setTimeout(() => {
        this.showOriginal();
      }, 2000);

    } catch (error: any) {
      // Error adding to cart

      // Проверяем тип ошибки
      if (this.isInsufficientStockError(error)) {
        this.handleInsufficientStockError(error);
        return;
      }

      // Обычная ошибка
      this.showError();

      // Возвращаем оригинальное состояние через 2 секунды
      setTimeout(() => {
        this.showOriginal();
      }, 2000);
    } finally {
      this.hideLoading();
      this.isProcessing = false;
      // Завершаем попытку после каждого запроса
      this.endAttempt();
    }
  }

  /**
   * Получает выбранные опции из формы
   */
  private getSelectedOptions(): number[] {
    const optionAssignmentIds: number[] = [];
    const form = this.el.closest('form');

    if (form) {
      const selectedOptions = form.querySelectorAll<HTMLInputElement>('input[type="radio"][name^="option-"]:checked');

      selectedOptions.forEach(option => {
        const assignmentId = parseInt(option.value, 10);
        const optionName = option.name;
        if (assignmentId) {
          optionAssignmentIds.push(assignmentId);
        }
      });

      // Сортируем для консистентности
      optionAssignmentIds.sort((a, b) => a - b);
    }

    return optionAssignmentIds;
  }

  /**
   * Показывает состояние загрузки
   */
  private showLoading(): void {
    const button = this.el as HTMLButtonElement;
    button.disabled = true;

    if (this.loadingSpinner && this.mainContent) {
      this.loadingSpinner.style.opacity = '1';
      this.mainContent.classList.add('opacity-0');
    }
  }

  /**
   * Скрывает состояние загрузки
   */
  private hideLoading(): void {
    if (this.loadingSpinner) {
      this.loadingSpinner.style.opacity = '0';
    }
  }

  /**
   * Показывает успешное состояние
   */
  private showSuccess(): void {
    // Показываем галочку на кнопке
    if (this.mainContent) {
      this.mainContent.innerHTML = `
        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
        </svg>
        <span class="text-lg">Добавлено!</span>
      `;
    }

    // Открываем модальное окно с сообщением
    if (!this.successModal) {
      this.successModal = new Modal(this.el, {
        type: 'html',
        html: `
          <div class="text-center p-6">
            <div class="mb-4">
              <svg class="w-16 h-16 text-green-500 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
              </svg>
            </div>
            <h3 class="text-xl font-semibold text-gray-900 mb-2">Товар добавлен в корзину!</h3>
            <p class="text-gray-600 mb-4">Вы можете продолжить покупки или перейти в корзину</p>
            <div class="flex gap-3 justify-center">
              <button onclick="this.closest('.fancybox__slide').querySelector('.fancybox__close').click()"
                      class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 transition-colors">
                Продолжить покупки
              </button>
              <a href="/cart" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                Перейти в корзину
              </a>
            </div>
          </div>
        `,
        width: 400,
        height: 300,
        closeOnOverlay: true,
        closeOnEscape: true,
        showCloseButton: true
      });
    }

    this.successModal.open();
  }

  /**
   * Показывает состояние ошибки
   */
  private showError(): void {
    if (this.mainContent) {
      this.mainContent.innerHTML = `
        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
        </svg>
        <span class="text-lg">Ошибка</span>
      `;
    }
  }

  /**
   * Показывает оригинальное содержимое кнопки
   */
  private showOriginal(): void {
    if (this.mainContent) {
      this.mainContent.innerHTML = this.originalContent;
      this.mainContent.classList.remove('opacity-0');
    }

    const button = this.el as HTMLButtonElement;
    button.disabled = false;

    // Сбрасываем флаг обработки
    this.isProcessing = false;
  }

  /**
   * Проверяет, является ли ошибка недостатком stock
   */
  private isInsufficientStockError(error: any): boolean {
    return error?.response?.status === 409 &&
           error?.response?.data?.error === 'insufficient_stock';
  }

  /**
   * Обрабатывает ошибку недостатка stock
   */
  private handleInsufficientStockError(error: any): void {
    const responseData = error.response?.data;
    const availableQuantity = responseData?.availableQuantity || 0;
    const message = responseData?.message || 'Недостаточно товара на складе';

    // Обновляем максимальное количество в quantity-selector
    if (this.quantitySelector && availableQuantity > 0) {
      this.quantitySelector.dispatchEvent(new CustomEvent('stock:updated', {
        detail: { availableQuantity }
      }));
    }

    // Показываем специальное модальное окно для ошибки stock
    this.showStockErrorModal(message, availableQuantity);

    // Возвращаем кнопку в исходное состояние
    setTimeout(() => {
      this.showOriginal();
    }, 3000);
  }

  /**
   * Показывает модальное окно с ошибкой stock
   */
  private showStockErrorModal(message: string, availableQuantity: number): void {
    const modal = new Modal(this.el, {
      type: 'html',
      html: `
        <div class="text-center p-6">
          <div class="mb-4">
            <svg class="w-16 h-16 text-orange-500 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"/>
            </svg>
          </div>
          <h3 class="text-xl font-semibold text-gray-900 mb-2">Недостаточно товара</h3>
          <p class="text-gray-600 mb-4">${message}</p>
          ${availableQuantity > 0 ? `
            <div class="bg-orange-50 border border-orange-200 rounded-lg p-4 mb-4">
              <p class="text-sm text-orange-800">
                Доступно для заказа: <strong>${availableQuantity} шт.</strong>
              </p>
            </div>
            <div class="flex gap-3 justify-center">
              <button onclick="this.closest('.fancybox__slide').querySelector('.fancybox__close').click()"
                      class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 transition-colors">
                Изменить количество
              </button>
              <button onclick="this.closest('.fancybox__slide').querySelector('.fancybox__close').click(); setTimeout(() => { const btn = document.querySelector('[data-module=add-to-cart]'); if (btn) btn.click(); }, 100);"
                      class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                Добавить ${availableQuantity} шт.
              </button>
            </div>
          ` : `
            <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-4">
              <p class="text-sm text-red-800">
                Товар временно отсутствует на складе
              </p>
            </div>
            <div class="flex gap-3 justify-center">
              <button onclick="this.closest('.fancybox__slide').querySelector('.fancybox__close').click()"
                      class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 transition-colors">
                Закрыть
              </button>
            </div>
          `}
        </div>
      `,
      width: 450,
      height: availableQuantity > 0 ? 350 : 300,
      closeOnOverlay: true,
      closeOnEscape: true,
      showCloseButton: true
    });

    modal.open();
  }

  /**
   * Создает новый UUID для попытки
   */
  private newAttemptKey(): string {
    // Используем crypto.randomUUID() если доступен, иначе fallback
    if (typeof crypto !== 'undefined' && (crypto as any).randomUUID) {
      return `cart-add-${(crypto as any).randomUUID()}`;
    }
    // Fallback для старых браузеров
    return `cart-add-${Math.random().toString(36).slice(2)}-${Date.now()}`;
  }

  /**
   * Возвращает ключ текущей попытки, создавая новый если необходимо
   */
  private getAttemptKey(): string {
    if (!this.currentIdempotencyKey) {
      this.currentIdempotencyKey = this.newAttemptKey();
    }
    return this.currentIdempotencyKey;
  }

  /**
   * Завершает текущую попытку (очищает ключ)
   */
  private endAttempt(): void {
    this.currentIdempotencyKey = null;
  }

  destroy(): void {
    // Удаляем слушатель изменений количества
    // Note: The event listener will be cleaned up automatically when the element is removed

    // Уничтожаем модальное окно если оно создано
    if (this.successModal) {
      this.successModal.destroy();
      this.successModal = null;
    }

    // Сбрасываем флаги и ключи
    this.clickHandlerBound = false;
    this.isProcessing = false;
    this.currentIdempotencyKey = null;

    super.destroy();
  }
}
