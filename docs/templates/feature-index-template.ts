// Шаблон для главного экспорта feature модуля
// Замените FeatureName на реальное название

import { FeatureNameComponent } from './ui/component';
import type { FeatureNameRequest, FeatureNameResponse } from '@shared/types/api';

// Интерфейс опций компонента
export interface FeatureNameOptions {
  autoLoad?: boolean;
  showErrors?: boolean;
  itemId?: number;
}

/**
 * Инициализирует компонент FeatureName
 */
export function init(
  root: HTMLElement,
  opts: FeatureNameOptions = {}
): () => void {
  const component = new FeatureNameComponent(root, opts);
  return () => component.destroy();
}

// Экспорт API функций для внешнего использования
export {
  getFeatureNames,
  getFeatureName,
  createFeatureName,
  updateFeatureName,
  deleteFeatureName
} from './api';

// Экспорт типов
export type { FeatureNameOptions, FeatureNameRequest, FeatureNameResponse };

// Экспорт компонента (для продвинутого использования)
export { FeatureNameComponent };
