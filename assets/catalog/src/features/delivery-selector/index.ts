import { DeliverySelector, type DeliverySelectorOptions } from './ui/component';

export interface Options extends DeliverySelectorOptions {}

export function init(root: HTMLElement, opts: Options = {}): () => void {
  const component = new DeliverySelector(root, opts);
  return () => component.destroy();
}

export * from './api';
export { DeliverySelector };
export type { DeliverySelectorOptions };


