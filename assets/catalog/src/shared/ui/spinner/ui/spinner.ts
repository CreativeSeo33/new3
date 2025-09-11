import { Component } from '@shared/ui/Component';

/**
 * Опции для компонента spinner
 */
interface SpinnerOptions {
  size?: 'small' | 'medium' | 'large';
  color?: 'primary' | 'secondary' | 'white';
  visible?: boolean;
  overlay?: boolean;
}

/**
 * Компонент загрузочного индикатора (spinner)
 */
export class Spinner extends Component {
  protected options: {
    size: 'small' | 'medium' | 'large';
    color: 'primary' | 'secondary' | 'white';
    visible: boolean;
    overlay: boolean;
  };
  private spinnerElement: HTMLElement;
  private overlayElement?: HTMLElement;

  constructor(el: HTMLElement, opts: SpinnerOptions = {}) {
    super(el, opts);

    // Читаем data-атрибуты, если опции не переданы
    const dataSize = el.dataset.size as 'small' | 'medium' | 'large';
    const dataColor = el.dataset.color as 'primary' | 'secondary' | 'white';
    const dataVisible = el.dataset.visible;
    const dataOverlay = el.dataset.overlay;

    // Определяем visible с правильной логикой
    let visible = false; // По умолчанию скрыт

    if (opts.visible !== undefined) {
      // Если передана опция visible, используем её (учитываем строковые значения)
      const visibleValue = opts.visible;
      visible = visibleValue === true || String(visibleValue) === 'true';
    } else if (dataVisible !== undefined) {
      // Если есть data-атрибут, используем его
      visible = dataVisible === 'true';
    }
    // Если ничего не передано, остается false

    this.options = {
      size: opts.size ?? dataSize ?? 'medium',
      color: opts.color ?? dataColor ?? 'primary',
      visible: visible,
      overlay: opts.overlay ?? (dataOverlay === 'true')
    };

    this.spinnerElement = document.createElement('span');
    this.spinnerElement.className = 'loader';

    this.init();
  }

  init(): void {
    // Создаем структуру спиннера
    this.el.innerHTML = '';

    // Настраиваем основной контейнер
    this.el.style.position = 'absolute'; // Изменено с relative на absolute
    this.el.style.width = '100%';
    this.el.style.height = '100%';
    this.el.style.display = 'flex';
    this.el.style.alignItems = 'center';
    this.el.style.justifyContent = 'center';

    // Проверяем, есть ли вложенный контейнер для центрирования
    let spinnerContainer = this.el.querySelector('.flex.items-center.justify-center.w-full.h-full');
    if (!spinnerContainer) {
      // Если вложенного контейнера нет, создаем его
      spinnerContainer = document.createElement('div');
      spinnerContainer.className = 'flex items-center justify-center w-full h-full';
      this.el.appendChild(spinnerContainer);
    }

    // Очищаем вложенный контейнер
    spinnerContainer.innerHTML = '';

    // Создаем overlay если нужен
    if (this.options.overlay) {
      this.overlayElement = document.createElement('div');
      this.overlayElement.className = 'spinner-overlay';
      spinnerContainer.appendChild(this.overlayElement);
    }

    // Добавляем спиннер во вложенный контейнер
    spinnerContainer.appendChild(this.spinnerElement);

    // Применяем стили
    this.applyStyles();

    // Устанавливаем видимость
    this.setVisible(this.options.visible);
  }

  /**
   * Применяет стили в зависимости от опций
   */
  private applyStyles(): void {
    // Устанавливаем размер
    switch (this.options.size) {
      case 'small':
        this.spinnerElement.style.height = '30px';
        this.spinnerElement.style.width = '30px';
        this.spinnerElement.style.setProperty('--spinner-size', '30px');
        break;
      case 'large':
        this.spinnerElement.style.height = '70px';
        this.spinnerElement.style.width = '70px';
        this.spinnerElement.style.setProperty('--spinner-size', '70px');
        break;
      case 'medium':
      default:
        this.spinnerElement.style.height = '50px';
        this.spinnerElement.style.width = '50px';
        this.spinnerElement.style.setProperty('--spinner-size', '50px');
        break;
    }

    // Настраиваем стили для overlay
    if (this.overlayElement) {
      this.overlayElement.style.position = 'absolute';
      this.overlayElement.style.top = '0';
      this.overlayElement.style.left = '0';
      this.overlayElement.style.width = '100%';
      this.overlayElement.style.height = '100%';
      this.overlayElement.style.backgroundColor = 'rgba(255, 255, 255, 0.9)';
      this.overlayElement.style.zIndex = '-1';
      this.overlayElement.style.display = 'none'; // По умолчанию скрыт
    }

    // Устанавливаем цвет
    switch (this.options.color) {
      case 'secondary':
        this.spinnerElement.style.setProperty('--spinner-color-1', '#6b7280');
        this.spinnerElement.style.setProperty('--spinner-color-2', '#9ca3af');
        break;
      case 'white':
        this.spinnerElement.style.setProperty('--spinner-color-1', '#2563eb');
        this.spinnerElement.style.setProperty('--spinner-color-2', '#e5e7eb');
        break;
      case 'primary':
      default:
        this.spinnerElement.style.setProperty('--spinner-color-1', '#2563eb');
        this.spinnerElement.style.setProperty('--spinner-color-2', '#ff3d00');
        break;
    }
  }

  /**
   * Показывает или скрывает спиннер
   */
  setVisible(visible: boolean): void {
    if (visible) {
      this.el.style.display = 'flex';
      if (this.overlayElement) {
        this.overlayElement.style.display = 'block';
      }
    } else {
      this.el.style.display = 'none';
      if (this.overlayElement) {
        this.overlayElement.style.display = 'none';
      }
    }
  }

  /**
   * Показывает спиннер
   */
  show(): void {
    this.setVisible(true);
  }

  /**
   * Скрывает спиннер
   */
  hide(): void {
    this.setVisible(false);
  }

  /**
   * Изменяет размер спиннера
   */
  setSize(size: 'small' | 'medium' | 'large'): void {
    this.options.size = size;
    this.applyStyles();
  }

  /**
   * Изменяет цвет спиннера
   */
  setColor(color: 'primary' | 'secondary' | 'white'): void {
    this.options.color = color;
    this.applyStyles();
  }

  /**
   * Проверяет, виден ли спиннер
   */
  isVisible(): boolean {
    return this.el.style.display !== 'none';
  }

  destroy(): void {
    // Очищаем содержимое
    this.el.innerHTML = '';

    super.destroy();
  }
}
