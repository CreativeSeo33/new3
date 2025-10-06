import { AuthPasswordComponent } from './ui/component';

export interface AuthPasswordOptions {}

export function init(root: HTMLElement, opts: AuthPasswordOptions = {}): () => void {
  const component = new AuthPasswordComponent(root, opts);
  return () => component.destroy();
}

export { passwordRequest, passwordConfirm } from './api';
export { AuthPasswordComponent };


