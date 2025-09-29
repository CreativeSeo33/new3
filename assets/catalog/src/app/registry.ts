// ai:registry area=catalog key=data-module export=init root=@features
/**
 * Тип для функции инициализации модуля
 */
type ModuleInitFunction = (el: HTMLElement, opts?: Record<string, any>) => (() => void) | void | undefined;

/**
 * Реестр модулей для ленивой загрузки
 */
export const registry: Record<string, () => Promise<ModuleInitFunction>> = {
  // Фичи
  'add-to-cart': () => import('../features/add-to-cart').then(m => m.init),
  'product-options': () => import('../features/product-options').then(m => m.init),
  'product-option-price-updater': () => import('../features/product-option-price-updater').then(m => m.init),
  'product-price-calculator': () => import('../features/product-price-calculator').then(m => m.init),
  'cart-items-manager': () => import('../features/cart-items-manager').then(m => m.init),
  'quantity-selector': () => import('../features/quantity-selector').then(m => m.init),
  'delivery-methods': () => import('../features/delivery-methods').then(m => m.init),
  'autocomplete': () => import('../features/autocomplete').then(m => m.init),
  'city-modal': () => import('../features/city-modal').then(m => m.init),
  'city-modal-trigger': () => import('../features/city-modal-trigger').then(m => m.init),
  'mobile-menu': () => import('../features/mobile-menu').then(m => m.init),
  'checkout-form': () => import('../features/checkout-form').then(m => m.init),
  'delivery-selector': () => import('../features/delivery-selector').then(m => m.init),
  'wishlist-toggle': () => import('../features/wishlist').then(m => m.init),

  // Виджеты
  'cart-counter': () => import('../widgets/cart-counter').then(m => m.init),
  'wishlist-counter': () => import('../widgets/wishlist-counter').then(m => m.init),
  'facets-panel': () => import('../widgets/facets-panel').then(m => m.init),
  'facets': () => import('../features/facets').then(m => m.init),

  // Shared UI компоненты
  'modal': () => import('../shared/ui/modal-simple.js').then(m => m.init),
  'spinner': () => import('../shared/ui/spinner').then(m => m.init),

  // Сущности (пока пустые)
  // product: () => import('@entities/product').then(m => m.init),
};

/**
 * Инициализирует модуль по имени
 */
export async function initModule(
  el: HTMLElement
): Promise<(() => void) | null> {
  const name = el.dataset.module;
  if (!name || !registry[name]) {
    // Module not found in registry
    return null;
  }

  try {
    const init = await registry[name]();
    const destroy = init(el, { ...el.dataset });

    // Сохраняем функцию уничтожения для cleanup
    (el as any).__destroy = typeof destroy === 'function' ? destroy : null;

    return destroy || null;
  } catch (error) {
    // Error initializing module
    return null;
  }
}
