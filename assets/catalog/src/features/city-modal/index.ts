import { CityModalComponent, type CityModalOptions } from './ui/component';

export type { CityModalOptions };

export function init(root: HTMLElement, opts: CityModalOptions = {}): () => void {
  const component = new CityModalComponent(root, opts);
  component.init();
  return () => component.destroy();
}

export { fetchCities, selectCity } from './api';


