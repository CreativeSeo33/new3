import { Spinner } from './ui/spinner';

/**
 * Опции для компонента spinner
 */
export interface SpinnerOptions {
  size?: 'small' | 'medium' | 'large';
  color?: 'primary' | 'secondary' | 'white';
  visible?: boolean;
  overlay?: boolean;
}

/**
 * Инициализирует компонент спиннера
 */
export function init(
  root: HTMLElement,
  opts: SpinnerOptions = {}
): () => void {
  const spinner = new Spinner(root, opts);
  return () => spinner.destroy();
}

// Экспортируем класс для продвинутого использования
export { Spinner };