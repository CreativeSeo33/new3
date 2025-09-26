import { Component } from '@shared/ui/Component';
import { post } from '@shared/api/http';
import type { SuggestionItem } from '../api';

export interface AutocompleteOptions {
  minChars?: number;
  debounceMs?: number;
  maxItems?: number;
  fetcher: (q: string, opts?: { limit?: number; signal?: AbortSignal }) => Promise<SuggestionItem[]>;
  placeholder?: string;
  inputSelector?: string; // если хотим указать существующий input внутри контейнера
}

export class Autocomplete extends Component {
  private inputEl!: HTMLInputElement;
  private listEl!: HTMLDivElement;
  private abortController: AbortController | null = null;
  private debouncedHandler!: (e: Event) => void;
  private documentClickHandler!: (e: MouseEvent) => void;
  private optionsTyped: Required<Pick<AutocompleteOptions, 'minChars' | 'debounceMs' | 'maxItems'>> & Omit<AutocompleteOptions, 'minChars' | 'debounceMs' | 'maxItems'>;

  constructor(el: HTMLElement, options: AutocompleteOptions) {
    super(el, options);
    this.optionsTyped = {
      minChars: options.minChars ?? 3,
      debounceMs: options.debounceMs ?? 300,
      maxItems: options.maxItems ?? 10,
      ...options,
    } as any;
  }

  init(): void {
    this.setupElements();
    this.setupEvents();
  }

  private setupElements(): void {
    // Находим или создаем input
    const withinRoot = this.optionsTyped.inputSelector ? this.$<HTMLInputElement>(this.optionsTyped.inputSelector) : (this.el as HTMLElement).querySelector('input');
    const existInput = withinRoot || (this.optionsTyped.inputSelector ? (document.querySelector(this.optionsTyped.inputSelector) as HTMLInputElement | null) : null);
    if (existInput) {
      this.inputEl = existInput as HTMLInputElement;
    } else {
      this.inputEl = document.createElement('input');
      this.inputEl.type = 'text';
      this.inputEl.className = 'py-2 px-3 ps-10 block w-full border border-gray-200 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none';
      if (this.optionsTyped.placeholder) this.inputEl.placeholder = this.optionsTyped.placeholder;
      this.el.appendChild(this.inputEl);
    }

    // Контейнер списка
    this.listEl = document.createElement('div');
    this.listEl.className = 'absolute left-0 right-0 top-full mt-2 w-full bg-white border border-gray-200 rounded-lg shadow-md max-h-60 overflow-auto z-20 hidden';

    // ARIA
    const listId = `ac-list-${Math.random().toString(36).slice(2, 9)}`;
    this.listEl.id = listId;
    this.listEl.setAttribute('role', 'listbox');
    this.inputEl.setAttribute('role', 'combobox');
    this.inputEl.setAttribute('aria-expanded', 'false');
    this.inputEl.setAttribute('aria-controls', listId);

    // Если input внутри корня — используем относительное позиционирование и иконку
    const inputInsideRoot = this.el.contains(this.inputEl);
    if (inputInsideRoot) {
      this.el.classList.add('relative');
      // Иконка поиска слева
      const iconWrap = document.createElement('div');
      iconWrap.className = 'absolute inset-y-0 start-0 flex items-center pointer-events-none ps-3';
      iconWrap.innerHTML = `
        <svg class="size-4 text-gray-500" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></svg>
      `;
      this.el.appendChild(iconWrap);
      this.el.appendChild(this.listEl);
    } else {
      // Внешний input: оборачиваем в относительный контейнер и рендерим список абсолютно
      const wrapper = document.createElement('div');
      wrapper.className = 'relative';
      const parent = this.inputEl.parentElement;
      if (parent) {
        parent.insertBefore(wrapper, this.inputEl);
        wrapper.appendChild(this.inputEl);
        wrapper.appendChild(this.listEl);
      } else {
        // fallback
        this.inputEl.insertAdjacentElement('afterend', this.listEl);
      }
    }
  }

  private setupEvents(): void {
    const debounce = (fn: (e: Event) => void, wait: number) => {
      let t: number | undefined;
      return (e: Event) => {
        if (t) window.clearTimeout(t);
        t = window.setTimeout(() => fn(e), wait);
      };
    };

    this.debouncedHandler = debounce(this.onInput.bind(this), this.optionsTyped.debounceMs);
    this.inputEl.addEventListener('input', this.debouncedHandler);
    const onKeydown = (e: KeyboardEvent) => { if (e.key === 'Escape') this.hideList(); };
    this.inputEl.addEventListener('keydown', onKeydown);
    this.documentClickHandler = (e: MouseEvent) => {
      const target = e.target as Node | null;
      if (target !== this.inputEl && !this.listEl.contains(target as Node)) this.hideList();
    };
    document.addEventListener('click', this.documentClickHandler);
  }

  private async onInput(e: Event): Promise<void> {
    const q = (e.target as HTMLInputElement).value.trim();
    if (q.length < this.optionsTyped.minChars) { this.hideList(); return; }

    if (this.abortController) this.abortController.abort();
    this.abortController = new AbortController();
    try {
      const items = await this.optionsTyped.fetcher(q, { limit: this.optionsTyped.maxItems, signal: this.abortController.signal });
      this.renderList(items);
    } catch (err: any) {
      if (err?.name !== 'AbortError') this.hideList();
    }
  }

  private renderList(items: SuggestionItem[]): void {
    this.listEl.innerHTML = '';
    if (!items.length) { this.hideList(); return; }
    const list = document.createElement('ul');
    list.setAttribute('role', 'listbox');
    list.className = 'divide-y divide-gray-100';
    for (const it of items) {
      const li = document.createElement('li');
      li.setAttribute('role', 'option');
      li.setAttribute('aria-selected', 'false');
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'w-full text-left px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 focus:bg-gray-100';
      btn.textContent = it.label;
      btn.addEventListener('click', async () => {
        this.inputEl.value = it.value;
        this.hideList();
        this.dispatchSelected(it);
        // Optional commit behavior: save city and reload
        if ((this.el.dataset.commit || '').toLowerCase() === 'select-city') {
          try {
            const cityName = it.value;
            await post('/api/delivery/select-city', { cityName }, { headers: { 'Accept': 'application/json' } });
            window.location.reload();
          } catch (e) {
            // silent fail: keep input value
          }
        }
      });
      li.appendChild(btn);
      list.appendChild(li);
    }
    this.listEl.appendChild(list);
    this.listEl.classList.remove('hidden');
    this.inputEl.setAttribute('aria-expanded', 'true');
  }

  private dispatchSelected(item: SuggestionItem): void {
    const ev = new CustomEvent('autocomplete:selected', { detail: item, bubbles: true });
    this.el.dispatchEvent(ev);
  }

  private hideList(): void {
    this.listEl.classList.add('hidden');
    this.listEl.innerHTML = '';
    this.inputEl.setAttribute('aria-expanded', 'false');
  }

  destroy(): void {
    try { this.inputEl.removeEventListener('input', this.debouncedHandler); } catch(_) {}
    try { document.removeEventListener('click', this.documentClickHandler); } catch(_) {}
    if (this.abortController) this.abortController.abort();
    super.destroy();
  }
}


