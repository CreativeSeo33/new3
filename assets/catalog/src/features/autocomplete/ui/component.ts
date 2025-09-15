import { Component } from '@shared/ui/Component';
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
      this.inputEl.className = 'w-full border rounded px-3 py-2';
      if (this.optionsTyped.placeholder) this.inputEl.placeholder = this.optionsTyped.placeholder;
      this.el.appendChild(this.inputEl);
    }

    // Контейнер списка
    this.listEl = document.createElement('div');
    this.listEl.className = 'mt-1 border rounded bg-white shadow hidden max-h-60 overflow-auto z-10';
    this.el.appendChild(this.listEl);
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
    for (const it of items) {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'w-full text-left px-3 py-2 hover:bg-gray-100';
      btn.textContent = it.label;
      btn.addEventListener('click', () => {
        this.inputEl.value = it.value;
        this.hideList();
        this.dispatchSelected(it);
      });
      this.listEl.appendChild(btn);
    }
    this.listEl.classList.remove('hidden');
  }

  private dispatchSelected(item: SuggestionItem): void {
    const ev = new CustomEvent('autocomplete:selected', { detail: item, bubbles: true });
    this.el.dispatchEvent(ev);
  }

  private hideList(): void {
    this.listEl.classList.add('hidden');
    this.listEl.innerHTML = '';
  }

  destroy(): void {
    try { this.inputEl.removeEventListener('input', this.debouncedHandler); } catch(_) {}
    try { document.removeEventListener('click', this.documentClickHandler); } catch(_) {}
    if (this.abortController) this.abortController.abort();
    super.destroy();
  }
}


