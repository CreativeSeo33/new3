import { Component } from '@shared/ui/Component';

export interface QuantitySelectorOptions {
  min?: number;
  max?: number;
  value?: number;
  disabled?: boolean;
  productId?: number | undefined;
  onChange?: ((value: number) => void) | undefined;
}

export class QuantitySelector extends Component {
  private value: number;
  private min: number;
  private max: number;
  private disabled: boolean;
  private productId?: number | undefined;
  private onChange?: ((value: number) => void) | undefined;

  private input!: HTMLInputElement;
  private decreaseBtn!: HTMLButtonElement;
  private increaseBtn!: HTMLButtonElement;

  constructor(el: HTMLElement, opts: QuantitySelectorOptions = {}) {
    super(el, opts);

    this.min = opts.min ?? 1;
    this.max = opts.max ?? Infinity;
    this.value = Math.max(this.min, Math.min(opts.value ?? 1, this.max));
    this.disabled = opts.disabled ?? false;
    this.productId = opts.productId;
    this.onChange = opts.onChange;

    this.init();
  }

  init(): void {
    this.setupElements();
    this.setupEventListeners();
    this.updateUI();
    this.emitChange();
  }

  private setupElements(): void {
    // Создаем структуру компонента
    this.el.innerHTML = `
      <div class="quantity-selector flex items-center border border-gray-300 rounded-lg overflow-hidden"
           role="group"
           aria-label="Выбор количества товара">
        <button type="button"
                class="decrease-btn w-10 h-10 flex items-center justify-center bg-gray-50 hover:bg-gray-100 border-r border-gray-300 disabled:opacity-50 disabled:cursor-not-allowed"
                aria-label="Уменьшить количество на 1"
                type="button">
          <svg class="w-4 h-4 text-gray-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14"/>
          </svg>
        </button>

        <input type="number"
               class="quantity-input flex-1 text-center w-16 h-10 border-0 focus:outline-none focus:ring-2 focus:ring-blue-500"
               min="${this.min}"
               max="${this.max}"
               step="1"
               aria-label="Количество товара"
               aria-valuemin="${this.min}"
               aria-valuemax="${this.max}"
               aria-valuenow="${this.value}">

        <button type="button"
                class="increase-btn w-10 h-10 flex items-center justify-center bg-gray-50 hover:bg-gray-100 border-l border-gray-300 disabled:opacity-50 disabled:cursor-not-allowed"
                aria-label="Увеличить количество на 1"
                type="button">
          <svg class="w-4 h-4 text-gray-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m6-6H6"/>
          </svg>
        </button>
      </div>
    `;

    // Получаем ссылки на элементы
    this.decreaseBtn = this.el.querySelector('.decrease-btn') as HTMLButtonElement;
    this.increaseBtn = this.el.querySelector('.increase-btn') as HTMLButtonElement;
    this.input = this.el.querySelector('.quantity-input') as HTMLInputElement;
  }

  private setupEventListeners(): void {
    // Обработчики для кнопок
    this.decreaseBtn.addEventListener('click', () => this.decrease());
    this.increaseBtn.addEventListener('click', () => this.increase());

    // Обработчики для поля ввода
    this.input.addEventListener('input', (e) => this.onInput(e));
    this.input.addEventListener('blur', () => this.onBlur());
    this.input.addEventListener('keydown', (e) => this.onKeydown(e));

    // Обработчики клавиш для кнопок
    this.decreaseBtn.addEventListener('keydown', (e) => this.onButtonKeydown(e, 'decrease'));
    this.increaseBtn.addEventListener('keydown', (e) => this.onButtonKeydown(e, 'increase'));

    // Слушаем обновления stock
    this.el.addEventListener('stock:updated', ((e: Event) => this.onStockUpdated(e as CustomEvent)) as EventListener);
  }

  private decrease(): void {
    if (this.disabled || this.value <= this.min) return;
    this.setValue(this.value - 1);
  }

  private increase(): void {
    if (this.disabled || this.value >= this.max) return;
    this.setValue(this.value + 1);
  }

  private onInput(e: Event): void {
    const target = e.target as HTMLInputElement;
    const newValue = parseInt(target.value, 10);

    if (isNaN(newValue)) {
      // Если введено не число, ничего не делаем
      return;
    }

    // Ограничиваем значение границами, но не устанавливаем сразу
    const clampedValue = Math.max(this.min, Math.min(newValue, this.max));
    this.value = clampedValue;
    this.emitChange();
  }

  private onBlur(): void {
    // При потере фокуса нормализуем значение
    const currentValue = parseInt(this.input.value, 10);
    if (isNaN(currentValue) || currentValue < this.min) {
      this.setValue(this.min);
    } else if (currentValue > this.max) {
      this.setValue(this.max);
    } else {
      this.setValue(currentValue);
    }
  }

  private onKeydown(e: KeyboardEvent): void {
    if (e.key === 'Enter') {
      e.preventDefault();
      this.input.blur(); // Вызываем нормализацию
    }
  }

  private onButtonKeydown(e: KeyboardEvent, action: 'decrease' | 'increase'): void {
    if (e.key === 'Enter' || e.key === ' ') {
      e.preventDefault();
      if (action === 'decrease') {
        this.decrease();
      } else {
        this.increase();
      }
    }
  }

  private onStockUpdated(e: CustomEvent): void {
    const availableQuantity = e.detail?.availableQuantity;
    if (typeof availableQuantity === 'number' && availableQuantity > 0) {
      // Обновляем максимальное количество на основе доступного stock
      this.setMax(availableQuantity);

      // Если текущее значение превышает доступное, корректируем его
      if (this.value > availableQuantity) {
        this.setValue(availableQuantity);
      }
    }
  }

  private setValue(newValue: number): void {
    const clampedValue = Math.max(this.min, Math.min(newValue, this.max));
    if (clampedValue !== this.value) {
      this.value = clampedValue;
      this.updateUI();
      this.emitChange();
    }
  }

  private updateUI(): void {
    // Обновляем значение в поле ввода
    this.input.value = this.value.toString();
    this.input.min = this.min.toString();
    this.input.max = this.max.toString();

    // Обновляем атрибуты доступности
    this.input.setAttribute('aria-valuenow', this.value.toString());
    this.input.setAttribute('aria-valuemin', this.min.toString());
    this.input.setAttribute('aria-valuemax', this.max.toString());

    // Обновляем состояние кнопок
    const canDecrease = !this.disabled && this.value > this.min;
    const canIncrease = !this.disabled && this.value < this.max;

    this.decreaseBtn.disabled = !canDecrease;
    this.increaseBtn.disabled = !canIncrease;
    this.input.disabled = this.disabled;

    // Обновляем атрибуты доступности
    this.decreaseBtn.setAttribute('aria-disabled', (!canDecrease).toString());
    this.increaseBtn.setAttribute('aria-disabled', (!canIncrease).toString());
    this.input.setAttribute('aria-disabled', this.disabled.toString());
  }

  private emitChange(): void {
    if (this.onChange) {
      this.onChange(this.value);
    }

    // Генерируем CustomEvent для синхронизации с другими компонентами
    const event = new CustomEvent('quantity:change', {
      detail: { value: this.value },
      bubbles: true
    });
    this.el.dispatchEvent(event);
  }

  // Публичные методы для внешнего управления
  public getValue(): number {
    return this.value;
  }

  public setValueExternal(newValue: number): void {
    this.setValue(newValue);
  }

  public setMax(max: number): void {
    this.max = max;
    if (this.value > this.max) {
      this.setValue(this.max);
    } else {
      this.updateUI();
    }
  }

  public setMin(min: number): void {
    this.min = min;
    if (this.value < this.min) {
      this.setValue(this.min);
    } else {
      this.updateUI();
    }
  }

  public setDisabled(disabled: boolean): void {
    this.disabled = disabled;
    this.updateUI();
  }

  public focus(): void {
    this.input.focus();
  }

  public   destroy(): void {
    // Удаляем обработчики событий
    this.decreaseBtn.removeEventListener('click', this.decrease);
    this.increaseBtn.removeEventListener('click', this.increase);
    this.input.removeEventListener('input', this.onInput);
    this.input.removeEventListener('blur', this.onBlur);
    this.input.removeEventListener('keydown', this.onKeydown);
    // Note: Event listeners are managed by the component lifecycle
    // The bound methods will be garbage collected when the component is destroyed

    super.destroy();
  }
}
