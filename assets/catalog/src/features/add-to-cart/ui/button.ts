import { Component } from '@shared/ui/Component';
import { addToCart } from '../api';
import { formatPrice } from '@shared/lib/formatPrice';
import type { Cart } from '@shared/types/api';

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
    this.on('click', this.handleClick.bind(this));
  }

  /**
   * Обработчик клика по кнопке
   */
  private async handleClick(e: Event): Promise<void> {
    e.preventDefault();

    const button = this.el as HTMLButtonElement;
    if (button.disabled) return;

    // Собираем выбранные опции из формы
    const optionAssignmentIds = this.getSelectedOptions();

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
        if (assignmentId) {
          optionAssignmentIds.push(assignmentId);
        }
      });
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
    if (this.mainContent) {
      this.mainContent.innerHTML = `
        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
        </svg>
        <span class="text-lg">Добавлено!</span>
      `;
    }
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
  }
}
