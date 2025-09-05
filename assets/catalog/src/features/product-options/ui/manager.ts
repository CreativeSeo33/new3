import { Component } from '@shared/ui/Component';
import type { ProductOptionData } from '@shared/types/api';

/**
 * Менеджер опций товара
 */
export class ProductOptionsManager extends Component {
  private selectedOptions = new Map<string, ProductOptionData>();

  constructor(el: HTMLElement, opts: Record<string, any> = {}) {
    super(el, opts);

    const optionInputs = this.$$<HTMLInputElement>('input[type="radio"][name^="option-"]');
    if (optionInputs.length === 0) {
      console.warn('ProductOptionsManager: No option inputs found');
      return;
    }

    this.init();
  }

  init(): void {
    // Инициализируем текущие выбранные значения
    this.updateSelectedOptions();

    // Слушаем изменения
    this.on('change', this.handleOptionChange.bind(this));

    // Отправляем начальное событие
    this.emitOptionsChanged();
  }

  /**
   * Обработчик изменения опции
   */
  private handleOptionChange(e: Event): void {
    const target = e.target as HTMLInputElement;
    if (target.type === 'radio' && target.name.startsWith('option-')) {
      // Добавляем анимацию для выбранного лейбла
      this.animateLabelSelection(target);

      // Обновляем выбранные опции
      this.updateSelectedOptions();

      // Отправляем событие об изменении
      this.emitOptionsChanged();
    }
  }

  /**
   * Добавляет анимацию для выбранного лейбла
   */
  private animateLabelSelection(input: HTMLInputElement): void {
    const label = input.nextElementSibling as HTMLElement;
    if (label) {
      label.style.transition = 'all 0.2s ease';
      label.style.transform = 'scale(1.02)';
      label.style.boxShadow = '0 4px 12px rgba(59, 130, 246, 0.15)';

      setTimeout(() => {
        label.style.transform = 'scale(1)';
        label.style.boxShadow = '';
      }, 200);
    }
  }

  /**
   * Обновляет карту выбранных опций
   */
  private updateSelectedOptions(): void {
    this.selectedOptions.clear();

    const optionInputs = this.$$<HTMLInputElement>('input[type="radio"][name^="option-"]:checked');

    optionInputs.forEach(input => {
      const salePriceAttr = input.getAttribute('data-sale-price');
      const salePriceValue = salePriceAttr ? parseInt(salePriceAttr, 10) : 0;

      const optionData: ProductOptionData = {
        id: parseInt(input.value, 10),
        name: input.dataset.optionName || '',
        value: input.dataset.optionValue || '',
        price: parseInt(input.dataset.optionPrice || '0', 10),
        setPrice: input.dataset.setPrice === 'true',
        salePrice: salePriceValue
      };

      this.selectedOptions.set(input.name, optionData);
    });
  }

  /**
   * Отправляет событие об изменении выбранных опций
   */
  private emitOptionsChanged(): void {
    const optionsData = Array.from(this.selectedOptions.values());
    const totalPriceModifier = optionsData.reduce((sum, option) => sum + option.price, 0);

    window.dispatchEvent(new CustomEvent('product:options-changed', {
      detail: {
        selectedOptions: optionsData,
        totalPriceModifier,
        formElement: this.el
      }
    }));
  }

  /**
   * Получает текущие выбранные опции
   */
  getSelectedOptions(): ProductOptionData[] {
    return Array.from(this.selectedOptions.values());
  }

  /**
   * Получает общий модификатор цены от всех выбранных опций
   */
  getTotalPriceModifier(): number {
    return Array.from(this.selectedOptions.values())
      .reduce((sum, option) => sum + option.price, 0);
  }

  /**
   * Получает ID выбранных опций для отправки в корзину
   */
  getSelectedOptionIds(): number[] {
    return Array.from(this.selectedOptions.values())
      .map(option => option.id);
  }
}
