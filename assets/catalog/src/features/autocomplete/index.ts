import { Autocomplete, type AutocompleteOptions } from './ui/component';
import { fetchFiasCities } from './api';
export type { AutocompleteOptions } from './ui/component';
export { fetchFiasCities } from './api';

export function init(root: HTMLElement, opts: Partial<AutocompleteOptions> = {}): () => void {
  // Конфиг через data-атрибуты
  const minChars = Number(root.dataset.minChars || opts.minChars || 3);
  const debounceMs = Number(root.dataset.debounce || opts.debounceMs || 300);
  const maxItems = Number(root.dataset.maxItems || opts.maxItems || 10);

  // По умолчанию — FIAS города; можно переопределить через opts.fetcher
  const fetcher = opts.fetcher || fetchFiasCities;

  const component = new Autocomplete(root, {
    minChars,
    debounceMs,
    maxItems,
    fetcher: fetcher,
    inputSelector: root.dataset.inputSelector,
    placeholder: root.dataset.placeholder,
  } as AutocompleteOptions);

  component.init();
  return () => component.destroy();
}


