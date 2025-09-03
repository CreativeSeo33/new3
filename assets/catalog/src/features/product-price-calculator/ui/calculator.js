import { Component } from '@shared/ui/Component';
import { formatPrice } from '@shared/lib/formatPrice';

/**
 * Калькулятор цены товара с учетом выбранных опций
 */
export class PriceCalculator extends Component {
  /**
   * @param {HTMLElement} el - Элемент с ценой товара
   * @param {Object} opts - Опции
   */
  constructor(el, opts = {}) {
    super(el, opts);

    // Находим элементы цены
    this.priceElement = el.querySelector('#product-price') || el;
    this.oldPriceElement = el.querySelector('#old-price');
    this.discountBadge = el.querySelector('#discount-badge');
    this.priceDescription = el.querySelector('#price-description');

    if (!this.priceElement) {
      console.warn('PriceCalculator: No price element found');
      return;
    }

    // Получаем базовую цену
    this.basePrice = parseInt(this.priceElement.dataset.basePrice, 10) || 0;
    this.oldPrice = parseInt(this.oldPriceElement?.dataset.oldPrice, 10) || 0;

    // Текущее состояние
    this.currentPrice = this.basePrice;
    this.currentPriceModifier = 0;

    this.init();
  }

  init() {
    // Слушаем изменения опций товара
    window.addEventListener('product:options-changed', this.handleOptionsChanged.bind(this));

    // Инициализируем отображение цены
    this.updatePriceDisplay();
  }

  /**
   * Обработчик изменения выбранных опций
   * @param {CustomEvent} e - Событие изменения опций
   */
  handleOptionsChanged(e) {
    const { totalPriceModifier } = e.detail;
    this.currentPriceModifier = totalPriceModifier;

    this.updatePriceDisplay();
  }

  /**
   * Рассчитывает новую цену на основе модификаторов
   * @returns {number} Новая цена
   */
  calculateNewPrice() {
    return this.basePrice + this.currentPriceModifier;
  }

  /**
   * Обновляет отображение цены с анимацией
   */
  updatePriceDisplay() {
    const newPrice = this.calculateNewPrice();
    const newOldPrice = this.oldPrice + (newPrice - this.basePrice);

    // Если цена не изменилась, выходим
    if (newPrice === this.currentPrice) return;

    const isPriceIncrease = newPrice > this.currentPrice;

    // Анимируем изменение цены
    this.animatePriceChange(isPriceIncrease);

    setTimeout(() => {
      // Обновляем текущую цену
      this.priceElement.textContent = formatPrice(newPrice);

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
   * @param {boolean} isIncrease - Увеличение или уменьшение цены
   */
  animatePriceChange(isIncrease) {
    this.priceElement.style.transition = 'all 0.3s ease';
    this.priceElement.style.transform = 'scale(1.1)';
    this.priceElement.style.color = isIncrease ? '#dc2626' : '#059669';
  }

  /**
   * Сбрасывает анимацию цены
   */
  resetPriceAnimation() {
    this.priceElement.style.transform = 'scale(1)';
    this.priceElement.style.color = '';
  }

  /**
   * Получает текущую цену
   * @returns {number} Текущая цена
   */
  getCurrentPrice() {
    return this.currentPrice;
  }

  /**
   * Получает базовую цену
   * @returns {number} Базовая цена
   */
  getBasePrice() {
    return this.basePrice;
  }

  /**
   * Получает текущий модификатор цены
   * @returns {number} Модификатор цены
   */
  getCurrentPriceModifier() {
    return this.currentPriceModifier;
  }

  /**
   * Устанавливает новую базовую цену
   * @param {number} price - Новая базовая цена
   */
  setBasePrice(price) {
    this.basePrice = price;
    this.updatePriceDisplay();
  }

  destroy() {
    // Удаляем слушатель событий
    window.removeEventListener('product:options-changed', this.handleOptionsChanged.bind(this));

    super.destroy();
  }
}
