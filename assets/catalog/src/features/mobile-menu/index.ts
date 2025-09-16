import { MobileMenuComponent, type MobileMenuOptions } from './ui/component';

export type { MobileMenuOptions };

export function init(root: HTMLElement, opts: MobileMenuOptions = {}): () => void {
  const component = new MobileMenuComponent(root, opts);
  component.init();
  return () => component.destroy();
}


