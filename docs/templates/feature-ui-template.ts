// Шаблон для UI компонента feature модуля
// Замените FeatureName на реальное название компонента

import { Component } from '@shared/ui/Component';
import { getFeatureName, createFeatureName } from '../api';
import type { FeatureNameRequest, FeatureNameResponse } from '@shared/types/api';

interface FeatureNameOptions {
  autoLoad?: boolean;
  showErrors?: boolean;
}

export class FeatureNameComponent extends Component {
  private options: FeatureNameOptions;
  private data: FeatureNameResponse | null = null;
  private loading = false;

  constructor(el: HTMLElement, opts: FeatureNameOptions = {}) {
    super(el, opts);

    this.options = {
      autoLoad: false,
      showErrors: true,
      ...opts
    };

    this.init();
  }

  init(): void {
    // Инициализация компонента
    if (this.options.autoLoad) {
      this.loadData();
    }

    // Добавление обработчиков событий
    this.setupEventListeners();
  }

  private setupEventListeners(): void {
    // Обработчик клика
    this.on('click', this.handleClick.bind(this));

    // Обработчик отправки формы
    const form = this.$('form');
    if (form) {
      this.on('submit', this.handleSubmit.bind(this), { passive: false });
    }
  }

  private async handleClick(e: Event): Promise<void> {
    const target = e.target as HTMLElement;

    // Обработка клика по кнопке загрузки
    if (target.matches('[data-action="load"]')) {
      await this.loadData();
    }

    // Обработка клика по кнопке создания
    if (target.matches('[data-action="create"]')) {
      await this.createItem();
    }
  }

  private async handleSubmit(e: Event): Promise<void> {
    e.preventDefault();
    await this.createItem();
  }

  private async loadData(): Promise<void> {
    if (this.loading) return;

    this.loading = true;
    this.showLoading();

    try {
      const id = this.dataset.int('item-id');
      if (id) {
        this.data = await getFeatureName(id);
        this.renderData();
      }
    } catch (error) {
      console.error('Error loading data:', error);
      if (this.options.showErrors) {
        this.showError(error instanceof Error ? error.message : 'Unknown error');
      }
    } finally {
      this.loading = false;
      this.hideLoading();
    }
  }

  private async createItem(): Promise<void> {
    if (this.loading) return;

    const form = this.$('form') as HTMLFormElement;
    if (!form) return;

    const formData = new FormData(form);
    const data: FeatureNameRequest = {
      // Соберите данные из формы
      name: formData.get('name') as string,
      // ... другие поля
    };

    this.loading = true;
    this.showLoading();

    try {
      this.data = await createFeatureName(data);
      this.renderData();
      this.showSuccess('Элемент создан успешно');

      // Отправка события для обновления других компонентов
      window.dispatchEvent(new CustomEvent('feature-name:created', {
        detail: { data: this.data }
      }));

    } catch (error) {
      console.error('Error creating item:', error);
      if (this.options.showErrors) {
        this.showError(error instanceof Error ? error.message : 'Ошибка создания');
      }
    } finally {
      this.loading = false;
      this.hideLoading();
    }
  }

  private renderData(): void {
    if (!this.data) return;

    // Обновление DOM с данными
    const nameEl = this.$('[data-field="name"]');
    if (nameEl) {
      nameEl.textContent = this.data.name;
    }

    // ... обновление других полей
  }

  private showLoading(): void {
    const loader = this.$('[data-loading]');
    if (loader) {
      loader.classList.remove('hidden');
    }
  }

  private hideLoading(): void {
    const loader = this.$('[data-loading]');
    if (loader) {
      loader.classList.add('hidden');
    }
  }

  private showSuccess(message: string): void {
    // Показать сообщение об успехе
    console.log('Success:', message);
  }

  private showError(message: string): void {
    // Показать сообщение об ошибке
    console.error('Error:', message);
  }

  // Геттеры для внешнего доступа
  getData(): FeatureNameResponse | null {
    return this.data;
  }

  isLoading(): boolean {
    return this.loading;
  }

  destroy(): void {
    // Очистка ресурсов
    this.data = null;
    this.loading = false;

    super.destroy();
  }
}
