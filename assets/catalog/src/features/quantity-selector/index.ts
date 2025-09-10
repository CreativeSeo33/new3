import { QuantitySelector, QuantitySelectorOptions } from './ui/quantity-selector';
import * as api from './api/index';

export { QuantitySelector };
export type { QuantitySelectorOptions };
export { api };

// Инициализация компонента
export interface InitOptions extends QuantitySelectorOptions {
  productId?: number;
  autoLoadStock?: boolean;
}

export function init(
  root: HTMLElement,
  opts: InitOptions = {}
): () => void {
  // Читаем параметры из data-атрибутов
  const dataOpts: QuantitySelectorOptions = {
    min: parseInt(root.dataset.min || '1', 10),
    max: parseInt(root.dataset.max || '999', 10),
    value: parseInt(root.dataset.value || '1', 10),
    disabled: root.dataset.disabled === 'true',
    productId: parseInt(root.dataset.productId || '0', 10) || undefined,
    ...opts
  };

  const component = new QuantitySelector(root, dataOpts);
  return () => component.destroy();
}

// Типы для экспорта
export type {
  QuantitySelector as QuantitySelectorComponent,
  InitOptions as QuantitySelectorInitOptions
};

// Переэкспорт API функций для удобства
export const {
  getProductStock,
  getCartQuantityInfo,
  calculateMaxAllowed,
  canAddQuantity,
  getRecommendedMax
} = api;
