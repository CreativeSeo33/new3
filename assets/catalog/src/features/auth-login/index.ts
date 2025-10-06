import { AuthLoginComponent } from './ui/component';

export interface AuthLoginOptions {
  showErrors?: boolean;
}

export function init(root: HTMLElement, opts: AuthLoginOptions = {}): () => void {
  const component = new AuthLoginComponent(root, opts);
  return () => component.destroy();
}

export { login } from './api';
export { AuthLoginComponent };


