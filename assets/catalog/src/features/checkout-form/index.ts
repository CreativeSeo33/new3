import { CheckoutFormComponent, type CheckoutFormOptions } from './ui/component';

export type { CheckoutFormOptions };

export function init(root: HTMLElement, opts: CheckoutFormOptions = {}): () => void {
  const component = new CheckoutFormComponent(root, opts);
  component.init();
  return () => component.destroy();
}

export { submitCheckout } from './api';


