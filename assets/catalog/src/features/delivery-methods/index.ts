import { DeliveryMethods, type DeliveryMethodsOptions } from './ui/component';

export function init(root: HTMLElement, opts: DeliveryMethodsOptions = {}): () => void {
  const cmp = new DeliveryMethods(root, opts);
  return () => cmp.destroy();
}

export * from './api';
export { DeliveryMethods } from './ui/component';
export type { DeliveryMethodsOptions } from './ui/component';


