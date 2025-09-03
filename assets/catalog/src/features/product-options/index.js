import { ProductOptionsManager } from './ui/manager';

/**
 * Инициализирует управление опциями товара
 * @param {HTMLElement} root - Корневой элемент формы с опциями
 * @param {Object} opts - Опции
 * @returns {Function} Функция для уничтожения компонента
 */
export function init(root, opts = {}) {
  const manager = new ProductOptionsManager(root, opts);
  return () => manager.destroy();
}

// Экспортируем для внешнего использования
export { ProductOptionsManager } from './ui/manager';
