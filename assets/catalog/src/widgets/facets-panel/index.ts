import { Component } from '@shared/ui/Component';
import { getFacets, type FacetResponse } from '@features/facets/api';

export interface FacetsPanelOptions {
  categoryId?: number;
}

class FacetsPanel extends Component {
  private categoryId: number;

  constructor(el: HTMLElement, opts: FacetsPanelOptions = {}) {
    super(el, opts);
    this.categoryId = opts.categoryId ?? this.dataset.int('categoryId', 0);
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
      this.renderError(e instanceof Error ? e.message : 'Ошибка загрузки фасетов');
    }
  }

  private render(data: FacetResponse): void {
    const container = this.$('[data-facets]');
    if (!container) return;
    container.innerHTML = '';

    for (const [code, facet] of Object.entries(data.facets)) {
      const section = document.createElement('section');
      section.className = 'facets-panel__section';

      const title = document.createElement('h3');
      title.className = 'facets-panel__title';
      title.textContent = code;
      section.appendChild(title);

      if (facet.type === 'range') {
        const r = document.createElement('div');
        r.className = 'facets-panel__range';
        r.textContent = `от ${facet.min ?? '-'} до ${facet.max ?? '-'}`;
        section.appendChild(r);
      } else if (facet.values) {
        const list = document.createElement('ul');
        list.className = 'facets-panel__list';
        for (const v of facet.values) {
          const li = document.createElement('li');
          li.className = 'facets-panel__item';
          li.textContent = `${v.label} (${v.count})`;
          list.appendChild(li);
        }
        section.appendChild(list);
      }

      container.appendChild(section);
    }
  }

  private renderError(message: string): void {
    const container = this.$('[data-facets]');
    if (!container) return;
    container.innerHTML = `<div class="facets-panel__error">${message}</div>`;
  }
}

export function init(root: HTMLElement, opts: FacetsPanelOptions = {}): () => void {
  const w = new FacetsPanel(root, opts);
  w.init();
  return () => w.destroy();
}


