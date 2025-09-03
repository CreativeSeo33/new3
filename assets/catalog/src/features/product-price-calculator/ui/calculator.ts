import { Component } from '@shared/ui/Component';
import { formatPrice } from '@shared/lib/formatPrice';

/**
 * Калькулятор цены товара с учетом выбранных опций
 */
export class PriceCalculator extends Component {
  private priceElement: HTMLElement | null;
  private oldPriceElement: HTMLElement | null;
  private discountBadge: HTMLElement | null;
  private priceDescription: HTMLElement | null;
  private basePrice: number = 0;
  private oldPrice: number = 0;
  private currentPrice: number = 0;
  private currentPriceModifier: number = 0;

  constructor(el: HTMLElement, opts: Record<string, any> = {}) {
    super(el, opts);

    // Находим элементы цены
    this.priceElement = el.querySelector('#product-price');
    this.oldPriceElement = el.querySelector('#old-price');
    this.discountBadge = el.querySelector('#discount-badge');
    this.priceDescription = el.querySelector('#price-description');

    if (!this.priceElement) {
      console.warn('PriceCalculator: No price element found');
      return;
    }

    // Получаем базовую цену
    this.basePrice = this.dataset.int('basePrice', 0);
    this.oldPrice = parseInt(this.oldPriceElement?.dataset.oldPrice || '0', 10);

    // Текущее состояние
    this.currentPrice = this.basePrice;
    this.currentPriceModifier = 0;

    this.init();
  }

  init(): void {
    // Слушаем изменения опций товара
    window.addEventListener('product:options-changed' as any, this.handleOptionsChanged.bind(this));

    // Инициализируем отображение цены
    this.updatePriceDisplay();
  }

  /**
   * Обработчик изменения выбранных опций
   */
  private handleOptionsChanged(e: CustomEvent): void {
    const { totalPriceModifier } = e.detail;
    this.currentPriceModifier = totalPriceModifier;

    this.updatePriceDisplay();
  }

  /**
   * Рассчитывает новую цену на основе модификаторов
   */
  private calculateNewPrice(): number {
    return this.basePrice + this.currentPriceModifier;
  }

  /**
   * Обновляет отображение цены с анимацией
   */
  private updatePriceDisplay(): void {
    const newPrice = this.calculateNewPrice();
    const newOldPrice = this.oldPrice + (newPrice - this.basePrice);

    // Если цена не изменилась, выходим
    if (newPrice === this.currentPrice || !this.priceElement) return;

    const isPriceIncrease = newPrice > this.currentPrice;

    // Анимируем изменение цены
    this.animatePriceChange(isPriceIncrease);

    setTimeout(() => {
      // Обновляем текущую цену
      this.priceElement!.textContent = formatPrice(newPrice);

      // Обновляем старую цену
      if (this.oldPriceElement) {
        this.oldPriceElement.textContent = formatPrice(newOldPrice);
      }

      // Обновляем процент скидки
      if (this.discountBadge && newOldPrice > newPrice) {
        const discountPercent = Math.round((1 - newPrice / newOldPrice) * 100);
        this.discountBadge.textContent = `-${discountPercent}%`;
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

  /**
   * Получает текущую цену
   */
  getCurrentPrice(): number {
    return this.currentPrice;
  }

  /**
   * Получает базовую цену
   */
  getBasePrice(): number {
    return this.basePrice;
  }

  /**
   * Получает текущий модификатор цены
   */
  getCurrentPriceModifier(): number {
    return this.currentPriceModifier;
  }

  /**
   * Устанавливает новую базовую цену
   */
  setBasePrice(price: number): void {
    this.basePrice = price;
    this.updatePriceDisplay();
  }

  destroy(): void {
    // Удаляем слушатель событий
    window.removeEventListener('product:options-changed' as any, this.handleOptionsChanged.bind(this));

    super.destroy();
  }
}
