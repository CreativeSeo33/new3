import { Component } from '@shared/ui/Component';

export interface LoadMoreOptions {
  pageSize?: number;
  onLoadMore?: (page: number) => Promise<void>;
}

export interface LoadMoreState {
  isLoading: boolean;
  hasError: boolean;
  loadedCount: number;
  totalCount: number | null;
  pageSize: number;
  currentPage: number;
}

/**
 * Компонент управления кнопкой "Показать еще товары"
 */
export class LoadMoreButton extends Component {
  private state: LoadMoreState;
  private container: HTMLElement | null = null;
  private button: HTMLButtonElement | null = null;
  private loader: HTMLElement | null = null;
  private buttonContent: HTMLElement | null = null;
  private buttonLoader: HTMLElement | null = null;
  protected options: LoadMoreOptions;
  private clickHandler: ((e: Event) => Promise<void>) | null = null;
  private productsLoadedHandler: ((e: CustomEvent) => void) | null = null;
  private productsErrorHandler: (() => void) | null = null;

  constructor(el: HTMLElement, options: LoadMoreOptions = {}) {
    super(el, options);
    this.options = options;
    
    // Инициализация состояния из data-атрибутов контейнера
    const loadedCount = this.dataset.int('loadedCount', 0);
    const totalCountRaw = this.dataset.raw('totalCount');
    const totalCount = totalCountRaw && totalCountRaw !== '0' ? parseInt(totalCountRaw, 10) : null;
    const pageSize = this.dataset.int('pageSize', options.pageSize || 20);
    const currentPage = this.dataset.int('currentPage', 1);

    this.state = {
      isLoading: false,
      hasError: false,
      loadedCount,
      totalCount: totalCount !== null && Number.isFinite(totalCount) && totalCount > 0 ? totalCount : null,
      pageSize,
      currentPage,
    };

    this.init();
  }

  init(): void {
    // Контейнер - это сам элемент el
    this.container = this.el;
    this.button = this.$('[data-load-more-button]') as HTMLButtonElement | null;
    this.loader = this.$('[data-load-more-loader]');
    this.buttonContent = this.$('.btn-content');
    this.buttonLoader = this.$('.btn-loader');

    // Сохраняем ссылки на обработчики для правильной очистки
    this.clickHandler = this.handleClick.bind(this);
    this.productsLoadedHandler = this.handleProductsLoaded.bind(this) as (e: CustomEvent) => void;
    this.productsErrorHandler = this.handleProductsError.bind(this);

    if (this.button && this.clickHandler) {
      this.button.addEventListener('click', this.clickHandler);
    }

    // Обновляем видимость при инициализации
    this.updateVisibility();

    // Слушаем события обновления товаров (от facets контроллера)
    if (this.productsLoadedHandler) {
      window.addEventListener('products:loaded', this.productsLoadedHandler as EventListener);
    }
    if (this.productsErrorHandler) {
      window.addEventListener('products:error', this.productsErrorHandler as EventListener);
    }
  }

  destroy(): void {
    if (this.button && this.clickHandler) {
      this.button.removeEventListener('click', this.clickHandler);
    }
    if (this.productsLoadedHandler) {
      window.removeEventListener('products:loaded', this.productsLoadedHandler as EventListener);
    }
    if (this.productsErrorHandler) {
      window.removeEventListener('products:error', this.productsErrorHandler as EventListener);
    }
    super.destroy();
  }

  /**
   * Обработчик клика по кнопке
   */
  private async handleClick(e: Event): Promise<void> {
    e.preventDefault();
    
    if (this.state.isLoading || this.shouldHideButton()) {
      return;
    }

    await this.loadMore();
  }

  /**
   * Загрузка следующей страницы товаров
   */
  private async loadMore(): Promise<void> {
    if (this.state.isLoading) return;

    const nextPage = this.state.currentPage + 1;
    this.setState({ isLoading: true, hasError: false });
    this.updateVisibility();

    try {
      if (this.options.onLoadMore) {
        await this.options.onLoadMore(nextPage);
      } else {
        await this.loadMoreViaUrl(nextPage);
      }

      const updates: Partial<LoadMoreState> = { isLoading: false, hasError: false };

      if (this.options.onLoadMore) {
        const nextLoadedCount = this.state.loadedCount + this.state.pageSize;
        updates.currentPage = nextPage;
        updates.loadedCount =
          this.state.totalCount !== null
            ? Math.min(nextLoadedCount, this.state.totalCount)
            : nextLoadedCount;
      }

      this.setState(updates);
    } catch (error) {
      console.error('Ошибка загрузки товаров:', error);
      this.setState({ isLoading: false, hasError: true });
    } finally {
      this.updateVisibility();
    }
  }

  /**
   * Загрузка через URL (дефолтная реализация)
   */
  private async loadMoreViaUrl(page: number): Promise<void> {
    const url = new URL(window.location.href);
    url.searchParams.set('page', String(page));
    url.searchParams.forEach((value, key) => {
      const trimmed = value.trim();
      if (trimmed === '') {
        url.searchParams.delete(key);
        return;
      }
      if (trimmed !== value) {
        url.searchParams.set(key, trimmed);
      }
    });

    const response = await fetch(url.toString(), {
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        Accept: 'text/html',
      },
    });

    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }

    const html = await response.text();
    const parser = new DOMParser();
    const doc = parser.parseFromString(html, 'text/html');

    const grid = document.querySelector('[data-testid="category-grid"]');
    const newGrid = doc.querySelector('[data-testid="category-grid"]');

    if (grid && newGrid) {
      const fragment = document.createDocumentFragment();
      Array.from(newGrid.children).forEach(product => {
        fragment.appendChild(product.cloneNode(true));
      });
      grid.appendChild(fragment);
    }

    const newContainer = doc.querySelector('[data-load-more-container]');
    if (newContainer) {
      const nextState: Partial<LoadMoreState> = {};

      const rawLoadedCount = newContainer.getAttribute('data-loaded-count');
      const parsedLoadedCount = rawLoadedCount ? parseInt(rawLoadedCount, 10) : NaN;
      if (Number.isFinite(parsedLoadedCount) && parsedLoadedCount >= 0) {
        nextState.loadedCount = parsedLoadedCount;
      }

      const rawTotalCount = newContainer.getAttribute('data-total-count');
      const parsedTotalCount = rawTotalCount ? parseInt(rawTotalCount, 10) : NaN;
      nextState.totalCount =
        Number.isFinite(parsedTotalCount) && parsedTotalCount > 0 ? parsedTotalCount : null;

      const rawPageSize = newContainer.getAttribute('data-page-size');
      const parsedPageSize = rawPageSize ? parseInt(rawPageSize, 10) : NaN;
      if (Number.isFinite(parsedPageSize) && parsedPageSize > 0) {
        nextState.pageSize = parsedPageSize;
      }

      const rawCurrentPage = newContainer.getAttribute('data-current-page');
      const parsedCurrentPage = rawCurrentPage ? parseInt(rawCurrentPage, 10) : NaN;
      if (Number.isFinite(parsedCurrentPage) && parsedCurrentPage > 0) {
        nextState.currentPage = parsedCurrentPage;
      } else {
        nextState.currentPage = page;
      }

      this.setState(nextState);
    }

    window.dispatchEvent(
      new CustomEvent('products:loaded', {
        detail: {
          page,
          loadedCount: this.state.loadedCount,
          totalCount: this.state.totalCount,
        },
      }),
    );
  }

  /**
   * Обработчик события загрузки товаров
   */
  private handleProductsLoaded = (e: CustomEvent): void => {
    const { loadedCount, totalCount } = e.detail || {};
    
    if (typeof loadedCount === 'number') {
      this.setState({ loadedCount });
    }
    
    if (typeof totalCount === 'number' || totalCount === null) {
      this.setState({ totalCount });
    }

    this.setState({ isLoading: false, hasError: false });
    this.updateVisibility();
  };

  /**
   * Обработчик события ошибки загрузки
   */
  private handleProductsError = (): void => {
    this.setState({ isLoading: false, hasError: true });
    this.updateVisibility();
  };

  /**
   * Обновление состояния
   */
  private setState(updates: Partial<LoadMoreState>): void {
    this.state = { ...this.state, ...updates };
  }

  /**
   * Проверка, должна ли кнопка быть скрыта
   */
  private shouldHideButton(): boolean {
    const { loadedCount, totalCount, pageSize } = this.state;

    if (totalCount === null || totalCount <= 0) {
      return true;
    }

    if (pageSize <= 0) {
      return true;
    }

    if (loadedCount <= 0) {
      return true;
    }

    if (loadedCount >= totalCount) {
      return true;
    }

    if (loadedCount % pageSize !== 0 && loadedCount < totalCount) {
      return true;
    }

    return false;
  }

  /**
   * Обновление видимости кнопки и индикатора загрузки
   */
  private updateVisibility(): void {
    const { isLoading, hasError } = this.state;
    const hideButton = this.shouldHideButton();
    const shouldShowLoader = isLoading && !hasError && (hideButton || !this.button);
    const shouldShowContainer = !hideButton || shouldShowLoader;

    if (this.container) {
      this.container.style.display = shouldShowContainer ? '' : 'none';
    }

    if (this.button) {
      if (hideButton) {
        this.button.style.display = 'none';
        this.button.disabled = true;
        this.button.setAttribute('aria-disabled', 'true');
        this.button.removeAttribute('aria-busy');
      } else {
        this.button.style.display = '';
        this.button.disabled = isLoading;

        if (isLoading) {
          this.button.setAttribute('aria-disabled', 'true');
          this.button.setAttribute('aria-busy', 'true');
        } else {
          this.button.removeAttribute('aria-disabled');
          this.button.removeAttribute('aria-busy');
        }
      }

      if (this.buttonContent) {
        this.buttonContent.classList.toggle('hidden', isLoading);
      }

      if (this.buttonLoader) {
        this.buttonLoader.classList.toggle('hidden', !isLoading);
      }

      if (!hideButton && isLoading) {
        this.button.classList.add('opacity-50', 'cursor-not-allowed', 'pointer-events-none');
      } else {
        this.button.classList.remove('opacity-50', 'cursor-not-allowed', 'pointer-events-none');
      }
    }

    if (this.loader) {
      this.loader.style.display = shouldShowLoader ? '' : 'none';
    }
  }

  /**
   * Обновление состояния извне (публичный метод)
   */
  public updateState(updates: Partial<LoadMoreState>): void {
    this.setState(updates);
    this.updateVisibility();
  }

  /**
   * Получение текущего состояния
   */
  public getState(): Readonly<LoadMoreState> {
    return { ...this.state };
  }
}

