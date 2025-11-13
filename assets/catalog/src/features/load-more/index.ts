import { LoadMoreButton, type LoadMoreOptions } from './ui/component';

export function init(root: HTMLElement, opts: LoadMoreOptions = {}): () => void {
  const component = new LoadMoreButton(root, opts);
  return () => component.destroy();
}

export { LoadMoreButton };
export type { LoadMoreOptions };

