import { initModule } from './registry';

/**
 * Инициализирует модуль на элементе
 */
function initNode(el: HTMLElement): void {
  const name = el.dataset.module;
  if (!name) return;

  initModule(el).catch((error: unknown) => {
    console.error(`Failed to initialize module "${name}":`, error);
  });
}

/**
 * Сканирует DOM на наличие элементов с data-module
 */
export function scan(root: Document | Element = document): void {
  const modules = root.querySelectorAll<HTMLElement>('[data-module]');
  modules.forEach(initNode);
}

/**
 * Уничтожает модуль на элементе
 */
function destroyNode(el: HTMLElement): void {
  const destroyFn = (el as any).__destroy;
  if (destroyFn && typeof destroyFn === 'function') {
    try {
      destroyFn();
    } catch (error) {
      console.error('Error destroying module:', error);
    }
    (el as any).__destroy = null;
  }
}

// MutationObserver для автоматической инициализации новых модулей
const mo = new MutationObserver((mutations: MutationRecord[]) => {
  mutations.forEach(mutation => {
    // Инициализируем новые модули
    mutation.addedNodes.forEach(node => {
      if (node.nodeType === Node.ELEMENT_NODE) {
        const element = node as HTMLElement;
        if (element.matches?.('[data-module]')) {
          initNode(element);
        } else {
          // Рекурсивно сканируем добавленные элементы
          scan(element);
        }
      }
    });

    // Уничтожаем удаленные модули
    mutation.removedNodes.forEach(node => {
      if (node.nodeType === Node.ELEMENT_NODE) {
        const element = node as HTMLElement;
        if (element.matches?.('[data-module]')) {
          destroyNode(element);
        } else {
          // Рекурсивно уничтожаем модули в удаленных элементах
          const modules = element.querySelectorAll?.<HTMLElement>('[data-module]');
          modules?.forEach(destroyNode);
        }
      }
    });
  });
});

/**
 * Запускает bootstrap процесс
 */
export function bootstrap(): void {
  // Инициализируем существующие модули
  scan();

  // Начинаем наблюдение за изменениями DOM
  mo.observe(document.body, {
    childList: true,
    subtree: true
  });

  console.log('Catalog bootstrap initialized');
}

/**
 * Останавливает bootstrap процесс
 */
export function teardown(): void {
  // Отключаем наблюдение
  mo.disconnect();

  // Уничтожаем все активные модули
  const modules = document.querySelectorAll<HTMLElement>('[data-module]');
  modules.forEach(destroyNode);

  console.log('Catalog bootstrap stopped');
}
