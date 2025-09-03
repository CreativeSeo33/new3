import { Component } from '@shared/ui/Component';

/**
 * Менеджер опций товара
 * Управляет выбором опций и отправляет события об изменениях
 */
export class ProductOptionsManager extends Component {
  /**
   * @param {HTMLElement} el - Форма с опциями товара
   * @param {Object} opts - Опции
   */
  constructor(el, opts = {}) {
    super(el, opts);

    this.optionInputs = this.$$('input[type="radio"][name^="option-"]');
    this.selectedOptions = new Map();

    if (this.optionInputs.length === 0) {
      console.warn('ProductOptionsManager: No option inputs found');
      return;
    }

    this.init();
  }

  init() {
    // Инициализируем текущие выбранные значения
    this.updateSelectedOptions();

    // Слушаем изменения
    this.on('change', this.handleOptionChange.bind(this));

    // Отправляем начальное событие
    this.emitOptionsChanged();
  }

  /**
   * Обработчик изменения опции
   * @param {Event} e - Событие изменения
   */
  handleOptionChange(e) {
    if (e.target.type === 'radio' && e.target.name.startsWith('option-')) {
      // Добавляем анимацию для выбранного лейбла
      this.animateLabelSelection(e.target);

      // Обновляем выбранные опции
      this.updateSelectedOptions();

      // Отправляем событие об изменении
      this.emitOptionsChanged();

      // Небольшая задержка для визуального эффекта
      setTimeout(() => {
        // Здесь можно добавить дополнительные эффекты
      }, 100);
    }
  }

  /**
   * Добавляет анимацию для выбранного лейбла
   * @param {HTMLInputElement} input - Выбранный radio input
   */
  animateLabelSelection(input) {
    const label = input.nextElementSibling;
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
  updateSelectedOptions() {
    this.selectedOptions.clear();

    this.optionInputs.forEach(input => {
      if (input.checked) {
        const optionData = {
          id: parseInt(input.value, 10),
          name: input.dataset.optionName,
          value: input.dataset.optionValue,
          price: parseInt(input.dataset.optionPrice, 10) || 0
        };
        this.selectedOptions.set(input.name, optionData);
      }
    });
  }

  /**
   * Отправляет событие об изменении выбранных опций
   */
  emitOptionsChanged() {
    const optionsData = Array.from(this.selectedOptions.values());
    const totalPriceModifier = optionsData.reduce((sum, option) => sum + option.price, 0);

    // Отправляем событие для других модулей (например, калькулятора цены)
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
   * @returns {Array} Массив выбранных опций
   */
  getSelectedOptions() {
    return Array.from(this.selectedOptions.values());
  }

  /**
   * Получает общий модификатор цены от всех выбранных опций
   * @returns {number} Сумма модификаторов цены
   */
  getTotalPriceModifier() {
    return Array.from(this.selectedOptions.values())
      .reduce((sum, option) => sum + option.price, 0);
  }

  /**
   * Получает ID выбранных опций для отправки в корзину
   * @returns {Array<number>} Массив ID опций
   */
  getSelectedOptionIds() {
    return Array.from(this.selectedOptions.values())
      .map(option => option.id);
  }
}
