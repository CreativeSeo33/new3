import type { FacetResponse } from './api';
import { getFacets } from './api';
import { Component } from '@shared/ui/Component';

export interface FacetsOptions {
  categoryIdAttr?: string; // data-category-id by default
}

class FacetsComponent extends Component {
  private categoryId: number;

  constructor(el: HTMLElement, opts: FacetsOptions = {}) {
    super(el, opts);
    const attr = opts.categoryIdAttr ?? 'categoryId';
    this.categoryId = parseInt(el.dataset[attr] || '0', 10) || 0;
  }

  init(): void {
    void this.load();
  }

  private async load(): Promise<void> {
    if (!this.categoryId) return;
    try {
      const data: FacetResponse = await getFacets({ category: this.categoryId });
      this.render(data);
    } catch (e) {
      // no-op basic
    }
  }

  private render(data: FacetResponse): void {
    const container = this.$('[data-facets]');
    if (!container) return;
    container.innerHTML = '';
    for (const [code, facet] of Object.entries(data.facets)) {
      const block = document.createElement('div');
      block.setAttribute('data-facet', code);
      const title = document.createElement('div');
      title.textContent = `${code}`;
      block.appendChild(title);
      if (facet.type === 'range') {
        const r = document.createElement('div');
        r.textContent = `min: ${facet.min ?? '-'} max: ${facet.max ?? '-'}`;
        block.appendChild(r);
      } else if (facet.values) {
        const list = document.createElement('ul');
        for (const v of facet.values) {
          const li = document.createElement('li');
          li.textContent = `${v.label} (${v.count})`;
          list.appendChild(li);
        }
        block.appendChild(list);
      }
      container.appendChild(block);
    }
  }
}

export function init(root: HTMLElement, opts: FacetsOptions = {}): () => void {
  const c = new FacetsComponent(root, opts);
  c.init();
  return () => c.destroy();
}

export { getFacets };


