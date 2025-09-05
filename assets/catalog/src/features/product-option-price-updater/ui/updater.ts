import { Component } from '@shared/ui/Component';
import { formatPrice } from '@shared/lib/formatPrice';

/**
 * Обновляет цену товара при изменении выбранных опций
 */
export class ProductOptionPriceUpdater extends Component {
  private priceElement: HTMLElement | null;
  private oldPriceElement: HTMLElement | null;
  private discountBadge: HTMLElement | null;
  private basePrice: number = 0;
  private baseOldPrice: number = 0;
  private currentPrice: number = 0;

  constructor(el: HTMLElement, opts: Record<string, any> = {}) {
    super(el, opts);

    // Находим элементы цены
    this.priceElement = document.querySelector('#product-price');
    this.oldPriceElement = document.querySelector('#old-price');
    this.discountBadge = document.querySelector('#discount-badge');

    if (!this.priceElement) {
      console.warn('ProductOptionPriceUpdater: No price element found');
      return;
    }

    // Получаем базовую цену из текста элемента
    this.basePrice = this.extractPriceFromText(this.priceElement.textContent || '');
    this.baseOldPrice = this.oldPriceElement ?
      this.extractPriceFromText(this.oldPriceElement.textContent || '') : 0;
    this.currentPrice = this.basePrice;

    this.init();
  }

  init(): void {
    // Слушаем изменения опций товара
    window.addEventListener('product:options-changed' as any, this.handleOptionsChanged.bind(this));
  }

  /**
   * Обработчик изменения выбранных опций
   */
  private handleOptionsChanged(e: any): void {
    const { selectedOptions } = e.detail;

    if (!selectedOptions || selectedOptions.length === 0) {
      // Если нет выбранных опций, показываем базовую цену
      this.updatePriceDisplay(this.basePrice, this.baseOldPrice);
      return;
    }

    // Находим опцию с ценой (обычно последняя выбранная)
    const optionWithPrice = selectedOptions.find((option: any) => option.price > 0);

    if (optionWithPrice && optionWithPrice.price > 0) {
      // Показываем цену выбранной опции
      const optionPrice = optionWithPrice.price;
      const optionOldPrice = optionWithPrice.price; // Для простоты, старая цена = новой

      this.updatePriceDisplay(optionPrice, optionOldPrice);
    } else {
      // Если у выбранной опции нет цены, показываем базовую
      this.updatePriceDisplay(this.basePrice, this.baseOldPrice);
    }
  }

  /**
   * Обновляет отображение цены
   */
  private updatePriceDisplay(newPrice: number, oldPrice: number = 0): void {
    if (!this.priceElement) return;

    // Если цена не изменилась, выходим
    if (newPrice === this.currentPrice) return;

    // Анимируем изменение цены
    this.animatePriceChange(newPrice > this.currentPrice);

    setTimeout(() => {
      // Обновляем текущую цену
      this.priceElement!.textContent = formatPrice(newPrice);

      // Обновляем старую цену
      if (this.oldPriceElement && oldPrice > newPrice) {
        this.oldPriceElement.textContent = formatPrice(oldPrice);
        this.oldPriceElement.style.display = 'block';
      } else if (this.oldPriceElement) {
        this.oldPriceElement.style.display = 'none';
      }

      // Обновляем процент скидки
      if (this.discountBadge && oldPrice > newPrice) {
        const discountPercent = Math.round((1 - newPrice / oldPrice) * 100);
        this.discountBadge.textContent = `-${discountPercent}%`;
        this.discountBadge.style.display = 'block';
      } else if (this.discountBadge) {
        this.discountBadge.style.display = 'none';
      }

      // Возвращаем нормальный вид после анимации
      setTimeout(() => {
        this.resetPriceAnimation();
      }, 200);

      // Обновляем текущую цену
      this.currentPrice = newPrice;
    }, 150);
  }

  /**
   * Извлекает числовую цену из текстового содержимого
   */
  private extractPriceFromText(text: string): number {
    // Убираем все нецифровые символы кроме точки и запятой
    const cleanText = text.replace(/[^\d.,]/g, '').replace(',', '.');
    const price = parseFloat(cleanText);
    return isNaN(price) ? 0 : price;
  }

  /**
   * Добавляет анимацию изменения цены
   */
  private animatePriceChange(isIncrease: boolean): void {
    if (!this.priceElement) return;

    this.priceElement.style.transition = 'all 0.3s ease';
    this.priceElement.style.transform = 'scale(1.1)';
    this.priceElement.style.color = isIncrease ? '#dc2626' : '#059669';
  }

  /**
   * Сбрасывает анимацию цены
   */
  private resetPriceAnimation(): void {
    if (!this.priceElement) return;

    this.priceElement.style.transform = 'scale(1)';
    this.priceElement.style.color = '';
  }

  destroy(): void {
    // Удаляем слушатель событий
    window.removeEventListener('product:options-changed' as any, this.handleOptionsChanged.bind(this));

    super.destroy();
  }
}
