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
}

/**
 * Компонент кнопки добавления в корзину
 */
export class AddToCartButton extends Component {
  private productId: number;
  private qty: number;
  private loadingSpinner: HTMLElement | null;
  private mainContent: HTMLElement | null;
  private originalContent: string;
  private isProcessing: boolean = false;
  private successModal: Modal | null = null;
  private clickHandlerBound: boolean = false;

  constructor(el: HTMLElement, opts: AddToCartButtonOptions = {}) {
    super(el, opts);

    this.productId = this.dataset.int('productId', opts.productId);
    this.qty = opts.qty ?? 1;
    this.loadingSpinner = this.$('.loading-spinner');
    this.mainContent = this.$('.relative');
    this.originalContent = this.mainContent?.innerHTML ?? '';

    if (!this.productId) {
      console.error('AddToCartButton: productId is required');
      return;
    }

    this.init();
  }

  init() {
    // Регистрируем обработчик только один раз
    if (!this.clickHandlerBound) {
      this.on('click', this.handleClick.bind(this));
      this.clickHandlerBound = true;
      console.log('Add to cart click handler bound for product:', this.productId);
    } else {
      console.log('Add to cart click handler already bound for product:', this.productId);
    }
  }

  /**
   * Обработчик клика по кнопке
   */
  private async handleClick(e: Event): Promise<void> {
    // Проверяем, не уничтожен ли компонент
    if (this.isDestroyed()) {
      console.log('Component is destroyed, ignoring click');
      return;
    }

    console.log('Add to cart button clicked for product:', this.productId);
    e.preventDefault();
    e.stopImmediatePropagation(); // Предотвращаем множественные срабатывания

    const button = this.el as HTMLButtonElement;

    // Дополнительная проверка на disabled
    if (button.disabled) {
      console.log('Button is disabled, ignoring click');
      return;
    }

    // Проверяем, не обрабатывается ли уже запрос
    if (this.isProcessing) {
      console.log('Already processing, ignoring duplicate click');
      return;
    }

    // Устанавливаем флаг обработки
    this.isProcessing = true;
    console.log('Starting add to cart process');

    // Проверяем и блокируем кнопку как можно раньше
    if (button.disabled) {
      console.log('Button is already disabled, but continuing due to processing flag');
    }

    // Немедленно блокируем кнопку
    button.disabled = true;
    console.log('Button disabled, processing add to cart');

    // Собираем выбранные опции из формы
    const optionAssignmentIds = this.getSelectedOptions();
    console.log('Selected option assignment IDs:', optionAssignmentIds);

    // Показываем состояние загрузки
    this.showLoading();

    try {
      // Добавляем товар в корзину
      const cartData: Cart = await addToCart(this.productId, this.qty, optionAssignmentIds);

      // Отправляем событие обновления корзины
      window.dispatchEvent(new CustomEvent('cart:updated', { detail: cartData }));

      // Показываем успешное состояние
      this.showSuccess();

      // Возвращаем оригинальное состояние через 2 секунды
      setTimeout(() => {
        this.showOriginal();
      }, 2000);

    } catch (error) {
      console.error('Error adding to cart:', error);

      // Показываем состояние ошибки
      this.showError();

      // Возвращаем оригинальное состояние через 2 секунды
      setTimeout(() => {
        this.showOriginal();
      }, 2000);
    } finally {
      this.hideLoading();
      this.isProcessing = false;
      console.log('Add to cart process completed');
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
      console.log('Found selected radio inputs:', selectedOptions.length);

      selectedOptions.forEach(option => {
        const assignmentId = parseInt(option.value, 10);
        const optionName = option.name;
        console.log(`Selected option: ${optionName} = ${assignmentId}`);
        if (assignmentId) {
          optionAssignmentIds.push(assignmentId);
        }
      });

      // Сортируем для консистентности
      optionAssignmentIds.sort((a, b) => a - b);
      console.log('Sorted option assignment IDs:', optionAssignmentIds);
    } else {
      console.log('No form found for option selection');
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
    console.log('Button state reset, isProcessing = false');
  }

  destroy(): void {
    // Уничтожаем модальное окно если оно создано
    if (this.successModal) {
      this.successModal.destroy();
      this.successModal = null;
    }

    // Сбрасываем флаги
    this.clickHandlerBound = false;
    this.isProcessing = false;

    super.destroy();
  }
}
