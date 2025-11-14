export interface ProductSliderOptions {
  containerSelector?: string; // селектор контейнера с карточками
}

export function init(rootEl: HTMLElement, opts: ProductSliderOptions = {}): () => void {
  const containerSelector = opts.containerSelector || '[data-testid="category-grid"]';
  const gridContainer = rootEl.querySelector(containerSelector) as HTMLElement | null;

  if (!gridContainer) {
    try { console.warn('Product grid container not found for product-slider'); } catch {}
    return () => {};
  }

  // Создаём swiper-container и откладываем инициализацию до установки параметров
  const swiperContainer = document.createElement('swiper-container') as any;
  swiperContainer.setAttribute('init', 'false');

  // Переносим только безопасные классы (без grid/grid-cols-*), чтобы слайдер растягивался на всю ширину
  try {
    const original = (gridContainer.className || '').split(/\s+/).filter(Boolean);
    const filtered = original.filter(c => !/^grid$/.test(c) && !/^grid-cols-/.test(c) && !/^(sm|md|lg|xl|2xl):grid-cols-/.test(c));
    const classes = Array.from(new Set([...filtered, 'w-full']));
    (swiperContainer as HTMLElement).className = classes.join(' ');
  } catch {}

  // Переносим карточки товаров в слайды
  const originalCards = Array.from(gridContainer.children);
  originalCards.forEach((card) => {
    const slide = document.createElement('swiper-slide');
    slide.appendChild(card); // переносим ноду как есть, сохраняя обработчики
    (swiperContainer as HTMLElement).appendChild(slide);
  });

  // Заменяем grid на swiper
  const parent = gridContainer.parentElement;
  gridContainer.replaceWith(swiperContainer as HTMLElement);

  // Кнопки навигации
  const navPrev = document.createElement('div');
  navPrev.className = 'swiper-button-prev';
  navPrev.setAttribute('aria-label', 'Предыдущий слайд');

  const navNext = document.createElement('div');
  navNext.className = 'swiper-button-next';
  navNext.setAttribute('aria-label', 'Следующий слайд');

  // Пагинация
  const pagination = document.createElement('div');
  pagination.className = 'swiper-pagination';

  // Добавляем кнопки и пагинацию рядом со слайдером (важно до initialize)
  try {
    parent?.appendChild(navPrev);
    parent?.appendChild(navNext);
    parent?.appendChild(pagination);
  } catch {}

  // Параметры Swiper (Element API — через свойства)
  Object.assign(swiperContainer, {
    slidesPerView: 1,
    spaceBetween: 8,
    navigation: {
      enabled: true,
      nextEl: '.swiper-button-next',
      prevEl: '.swiper-button-prev',
    },
    pagination: {
      enabled: true,
      el: '.swiper-pagination',
      clickable: true,
      dynamicBullets: false,
    },
    breakpoints: {
      640: { slidesPerView: 2, spaceBetween: 12 },
      1024: { slidesPerView: 5, spaceBetween: 16 },
    },
  });

  // Инициализируем после установки свойств
  try { (swiperContainer as any).initialize(); } catch {}

  // Cleanup
  return () => {
    try {
      const instance = (swiperContainer as any)?.swiper;
      if (instance && typeof instance.destroy === 'function') {
        instance.destroy(true, true);
      }
    } catch (e) {
      try { console.error('Swiper cleanup error:', e); } catch {}
    }

    // Удаляем кнопки навигации и пагинацию, созданные виджетом
    try { navPrev.remove(); } catch {}
    try { navNext.remove(); } catch {}
    try { pagination.remove(); } catch {}
  };
}


