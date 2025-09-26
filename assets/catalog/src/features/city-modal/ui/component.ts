import { Component } from '@shared/ui/Component';
import { fetchCities, selectCity, type CityItem } from '../api';

export interface CityModalOptions {
  // reserved for future
}

export class CityModalComponent extends Component {
  private closeButton: HTMLElement | null = null;
  private backdrop: HTMLElement | null = null;
  private list: HTMLElement | null = null;
  private abortController: AbortController | null = null;
  private spinnerTemplate: HTMLTemplateElement | null = null;

  constructor(el: HTMLElement, opts: CityModalOptions = {}) {
    super(el, opts);
  }

  init(): void {
    this.closeButton = this.$('[data-close]');
    this.backdrop = this.$('[data-backdrop]');
    this.list = this.$('[data-list]');
    this.spinnerTemplate = this.el.querySelector('template[data-spinner-template]');

    if (this.closeButton) {
      this.on('click', (e: Event) => {
        const target = e.target as HTMLElement;
        if (target.closest('[data-close]')) {
          this.hide();
        }
      });
    }

    if (this.backdrop) {
      this.on('click', (e: Event) => {
        if (e.target === this.backdrop) this.hide();
      });
    }

    // реагируем на глобальные события открытия/закрытия
    window.addEventListener('city-modal:open', this.handleExternalOpen);
    window.addEventListener('city-modal:close', this.handleExternalClose);

    // загрузка данных при первом открытии (ленивая)
    // если компонент уже видим (редко), подгрузим сразу
    if (!this.el.classList.contains('hidden')) {
      void this.ensureLoaded();
    }
  }

  public async ensureLoaded(): Promise<void> {
    if (!this.list) return;

    if (this.list.dataset.loaded === '1') return;
    // показать спиннер
    const spinner = this.instantiateSpinnerClone();
    if (spinner) this.list.appendChild(spinner);

    this.abortController?.abort();
    this.abortController = new AbortController();

    try {
      const items = await fetchCities();
      this.renderList(items);
      this.list.dataset.loaded = '1';
    } catch (e) {
      this.renderError('Ошибка загрузки');
    } finally {
      spinner?.remove();
    }
  }

  private instantiateSpinnerClone(): HTMLElement | null {
    if (!this.spinnerTemplate) return null;
    const fragment = this.spinnerTemplate.content.cloneNode(true) as DocumentFragment;
    const root = document.createElement('div');
    root.appendChild(fragment);
    return root.firstElementChild as HTMLElement;
  }

  private renderError(text: string): void {
    if (!this.list) return;
    this.list.innerHTML = `<div class="p-4 text-sm text-red-600">${text}</div>`;
  }

  private renderList(items: CityItem[]): void {
    if (!this.list) return;
    this.list.innerHTML = '';
    if (items.length === 0) {
      this.list.innerHTML = '<div class="p-4 text-sm text-gray-500">Нет городов</div>';
      return;
    }
    const ul = document.createElement('ul');
    ul.className = 'divide-y';
    for (const item of items) {
      const li = document.createElement('li');
      li.className = 'px-4 py-3 hover:bg-gray-50 cursor-pointer text-sm';
      li.textContent = item.name || '';
      li.addEventListener('click', () => this.handleSelect(item, li));
      ul.appendChild(li);
    }
    this.list.appendChild(ul);
  }

  private async handleSelect(item: CityItem, li: HTMLLIElement): Promise<void> {
    let spinner: HTMLElement | null = null;
    try {
      spinner = this.instantiateSpinnerClone();
      if (spinner && this.list) this.list.appendChild(spinner);
      li.classList.add('opacity-60');

      // Сохраним KLADR в sessionStorage для последующего сабмита checkout
      try {
        const kladr = (item as any).fiasId || '';
        if (typeof kladr === 'string' && kladr.length > 0) {
          const key = 'checkout_form';
          const raw = sessionStorage.getItem(key) || '{}';
          const data = JSON.parse(raw);
          data.cityKladr = kladr;
          sessionStorage.setItem(key, JSON.stringify(data));
        }
      } catch {}

      await selectCity(item);

      const labelEl = document.querySelector('[data-city-modal-trigger] span');
      if (labelEl) labelEl.textContent = item.name || 'Ваш город';

      this.hide();
      try {
        window.dispatchEvent(new CustomEvent('cart:updated', { detail: { cityChanged: true } }));
      } catch {}
      window.location.reload();
    } catch (e) {
      if (this.list) {
        const err = document.createElement('div');
        err.className = 'p-3 text-sm text-red-600';
        err.textContent = 'Не удалось сохранить город. Попробуйте ещё раз';
        this.list.prepend(err);
      }
    } finally {
      li.classList.remove('opacity-60');
      spinner?.remove();
    }
  }

  public show(): void {
    this.el.classList.remove('hidden');
    void this.ensureLoaded();
  }

  public hide(): void {
    this.el.classList.add('hidden');
  }

  private handleExternalOpen = (): void => {
    this.show();
  };

  private handleExternalClose = (): void => {
    this.hide();
  };

  destroy(): void {
    super.destroy();
    this.abortController?.abort();
    this.abortController = null;
    window.removeEventListener('city-modal:open', this.handleExternalOpen);
    window.removeEventListener('city-modal:close', this.handleExternalClose);
  }
}


