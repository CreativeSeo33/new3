// Шаблон для widget модуля
// Замените WidgetName на реальное название

import { Component } from '@shared/ui/Component';

interface WidgetNameOptions {
  theme?: 'light' | 'dark';
  size?: 'sm' | 'md' | 'lg';
  autoInit?: boolean;
}

export class WidgetNameWidget extends Component {
  private options: WidgetNameOptions;

  constructor(el: HTMLElement, opts: WidgetNameOptions = {}) {
    super(el, opts);

    this.options = {
      theme: 'light',
      size: 'md',
      autoInit: true,
      ...opts
    };

    this.init();
  }

  init(): void {
    // Применение темы
    this.applyTheme();

    // Применение размера
    this.applySize();

    // Настройка обработчиков событий
    this.setupEventListeners();

    // Автоматическая инициализация
    if (this.options.autoInit) {
      this.initializeWidget();
    }
  }

  private applyTheme(): void {
    const { theme } = this.options;

    // Удаление предыдущих классов темы
    this.el.classList.remove('theme-light', 'theme-dark');

    // Добавление класса текущей темы
    this.el.classList.add(`theme-${theme}`);
  }

  private applySize(): void {
    const { size } = this.options;

    // Удаление предыдущих классов размера
    this.el.classList.remove('size-sm', 'size-md', 'size-lg');

    // Добавление класса текущего размера
    this.el.classList.add(`size-${size}`);
  }

  private setupEventListeners(): void {
    // Обработчик клика
    this.on('click', this.handleClick.bind(this));

    // Прослушивание глобальных событий
    window.addEventListener('widget-name:update', this.handleUpdate.bind(this));
  }

  private handleClick(e: Event): void {
    const target = e.target as HTMLElement;

    // Обработка клика по элементам управления
    if (target.matches('[data-action="toggle"]')) {
      this.toggle();
    }

    if (target.matches('[data-action="close"]')) {
      this.close();
    }

    if (target.matches('[data-action="open"]')) {
      this.open();
    }
  }

  private handleUpdate(e: CustomEvent): void {
    // Обработка глобального события обновления
    const { data } = e.detail;
    this.updateContent(data);
  }

  private async initializeWidget(): Promise<void> {
    try {
      // Логика инициализации виджета
      await this.loadContent();
      this.render();
    } catch (error) {
      console.error('Widget initialization error:', error);
      this.showError();
    }
  }

  private async loadContent(): Promise<void> {
    // Загрузка данных для виджета
    // const data = await api.getData();
  }

  private render(): void {
    // Отрисовка содержимого виджета
    this.el.innerHTML = `
      <div class="widget-header">
        <h3>Widget Title</h3>
        <button data-action="close">×</button>
      </div>
      <div class="widget-content">
        <!-- Содержимое виджета -->
      </div>
    `;
  }

  private updateContent(data: any): void {
    // Обновление содержимого виджета
    const contentEl = this.$('.widget-content');
    if (contentEl) {
      // Обновить содержимое
    }
  }

  private toggle(): void {
    this.el.classList.toggle('open');
  }

  private open(): void {
    this.el.classList.add('open');
  }

  private close(): void {
    this.el.classList.remove('open');
  }

  private showError(): void {
    this.el.innerHTML = `
      <div class="error-message">
        Произошла ошибка при загрузке виджета
      </div>
    `;
  }

  // Публичные методы для внешнего управления
  public refresh(): void {
    this.initializeWidget();
  }

  public setTheme(theme: 'light' | 'dark'): void {
    this.options.theme = theme;
    this.applyTheme();
  }

  public setSize(size: 'sm' | 'md' | 'lg'): void {
    this.options.size = size;
    this.applySize();
  }

  destroy(): void {
    // Удаление глобальных обработчиков событий
    window.removeEventListener('widget-name:update', this.handleUpdate.bind(this));

    super.destroy();
  }
}

/**
 * Инициализирует виджет WidgetName
 */
export function init(
  root: HTMLElement,
  opts: WidgetNameOptions = {}
): () => void {
  const widget = new WidgetNameWidget(root, opts);
  return () => widget.destroy();
}

// Экспорт для продвинутого использования
export { WidgetNameWidget };
export type { WidgetNameOptions };
