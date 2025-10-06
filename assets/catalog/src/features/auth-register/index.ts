import { AuthRegisterComponent } from './ui/component';

export interface AuthRegisterOptions {
  showErrors?: boolean;
}

export function init(root: HTMLElement, opts: AuthRegisterOptions = {}): () => void {
  const component = new AuthRegisterComponent(root, opts);
  return () => component.destroy();
}

export { register } from './api';
export { AuthRegisterComponent };


