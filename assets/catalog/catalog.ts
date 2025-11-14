// ai:bootstrap area=catalog uses=registry
import './styles.css';
import 'flyonui/flyonui';
// Инициализируем глобальный noUiSlider для HSRangeSlider (Preline ожидает window.noUiSlider)
import * as noUiSliderModule from 'nouislider/dist/nouislider.js';
// @ts-ignore
if (typeof window !== 'undefined') { (window as any).noUiSlider = (noUiSliderModule as any).default || (noUiSliderModule as any); }
import { Fancybox } from '@fancyapps/ui';
import '@fancyapps/ui/dist/fancybox/fancybox.css';
import { register } from 'swiper/element/bundle';
import { bootstrap } from '@/app/bootstrap';

// Import Stimulus
import '../bootstrap.js';

// Регистрируем Swiper Element
register();

// Инициализация модульной системы (работает как после, так и до DOMContentLoaded)
function startApp(): void {
  // Запускаем модульную систему
  bootstrap();

  // Инициализируем раскрывающиеся блоки (el-disclosure)
  initDisclosureToggles();

  // Гарантируем инициализацию FlyonUI overlays и делегирование открытия
  initFlyonUIModals();

  // Инициализация галереи (оставляем как есть, так как это не модуль)
  setTimeout((): void => {
    initGallery();
  }, 100);
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', startApp);
} else {
  // DOM уже готов — запускаем немедленно
  startApp();
}

/**
 * Инициализация галереи изображений товара
 */
function initGallery(): void {
  const mainSwiperEl = document.querySelector('.product-gallery-swiper');
  const thumbnails = document.querySelectorAll<HTMLElement>('.thumbnail-item');

  if (!mainSwiperEl) return;

  // Функция для обновления активного состояния миниатюр
  const updateActiveThumbnail = (activeThumbnail: HTMLElement | null | undefined): void => {
    thumbnails.forEach(thumb => {
      thumb.classList.remove('active');
    });

    if (activeThumbnail) {
      activeThumbnail.classList.add('active');
    }
  };

  // Обработчик клика по слайдам (открывает Fancybox)
  const handleSlideClick = (e: Event): void => {
    e.preventDefault();

    const slide = (e.target as HTMLElement | null)?.closest('.gallery-slide');
    if (!slide) return;

    // Собираем все изображения для галереи
    const allImages: Array<{ src: string; caption?: string }> = [];
    const slides = document.querySelectorAll<HTMLElement>('.gallery-slide');

    slides.forEach(slide => {
      const ds = (slide as HTMLElement).dataset;
      if (ds.imageSrc) {
        allImages.push({
          src: ds.imageSrc,
          caption: ds.caption || 'Изображение товара'
        });
      }
    });

    // Находим индекс текущего слайда
    const currentIndex = Array.from(slides).indexOf(slide as HTMLElement);

    // Открываем Fancybox с текущим изображением
      try {
        // @ts-ignore - типы Fancybox могут отличаться от установленной версии
        Fancybox.show(allImages as any, {
        startIndex: currentIndex,
        Carousel: {
          infinite: false,
        },
          // @ts-ignore
          Images: {
          zoom: true,
          Panzoom: {
            maxScale: 3,
          }
        },
        Toolbar: {
          display: [
            { id: "prev", position: "center" },
            { id: "counter", position: "center" },
            { id: "next", position: "center" },
            "zoom",
            "fullscreen",
            "download",
            "thumbs",
            "close",
          ],
        },
        Thumbs: {
          type: "modern",
        },
      });
    } catch (error) {
      console.error('Fancybox error:', error);
    }
  };

  // Обработчик клика по миниатюрам (переключает основное изображение)
  const handleThumbnailClick = (e: Event): void => {
    e.preventDefault();

    const thumbnail = e.currentTarget as HTMLElement;
    const imageSrc = thumbnail.dataset.imageSrc as string | undefined;

    if (!imageSrc) return;

    // Находим индекс миниатюры для Swiper
    const thumbsArray = Array.from(thumbnails);
    const thumbnailIndex = thumbsArray.indexOf(thumbnail);

    // Переключаем Swiper на соответствующий слайд
    const swiperEl = mainSwiperEl as any;
    if (swiperEl.swiper) {
      swiperEl.swiper.slideTo(thumbnailIndex);
    }

    // Обновляем активное состояние миниатюр
    updateActiveThumbnail(thumbnail);
  };

  // Добавляем обработчики событий
  const gallerySlides = document.querySelectorAll<HTMLElement>('.gallery-slide');
  gallerySlides.forEach(slide => {
    slide.addEventListener('click', handleSlideClick as EventListener);
  });

  thumbnails.forEach(thumb => {
    thumb.addEventListener('click', handleThumbnailClick);
  });

  // Синхронизация с Swiper
  mainSwiperEl.addEventListener('swiperslidechange', (event: any) => {
    const activeIndex = event.detail?.[0]?.activeIndex ?? 0;
    const activeThumbnail = thumbnails[activeIndex];

    if (activeThumbnail) {
      updateActiveThumbnail(activeThumbnail);
    }
  });

  // Устанавливаем первую миниатюру активной по умолчанию
  if (thumbnails.length > 0) {
    updateActiveThumbnail(thumbnails[0]);
  }

  // Заменяем иконки в кнопках навигации
  const replaceNavigationIcons = (): void => {
    const swiperEl = mainSwiperEl as any;
    if (!swiperEl || !swiperEl.shadowRoot) return;

    // SVG иконка для кнопок навигации
    const iconSVG = `
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="24" height="24" style="max-width: 24px;">
        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
      </svg>
    `;

    // Находим кнопки через shadow DOM
    const buttonPrev = swiperEl.shadowRoot.querySelector('[part="button-prev"]');
    const buttonNext = swiperEl.shadowRoot.querySelector('[part="button-next"]');

    if (buttonPrev) {
      // Проверяем, не заменены ли уже иконки
      const existingSVG = buttonPrev.querySelector('svg[style*="max-width"]');
      if (!existingSVG) {
        buttonPrev.innerHTML = iconSVG;
      }
    }

    if (buttonNext) {
      // Проверяем, не заменены ли уже иконки
      const existingSVG = buttonNext.querySelector('svg[style*="max-width"]');
      if (!existingSVG) {
        // Для кнопки "next" отражаем иконку по горизонтали
        const nextIconSVG = `
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="24" height="24" style="max-width: 24px; transform: scaleX(-1);">
            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
          </svg>
        `;
        buttonNext.innerHTML = nextIconSVG;
      }
    }
  };

  // Используем MutationObserver для отслеживания появления кнопок в shadow DOM
  const observeSwiperButtons = (): void => {
    const swiperEl = mainSwiperEl as any;
    if (!swiperEl) {
      setTimeout(observeSwiperButtons, 10);
      return;
    }

    // Ждём появления shadowRoot
    if (!swiperEl.shadowRoot) {
      setTimeout(observeSwiperButtons, 10);
      return;
    }

    // Сразу пытаемся заменить иконки
    replaceNavigationIcons();

    // Наблюдаем за изменениями в shadow DOM для немедленной замены
    const observer = new MutationObserver((mutations) => {
      let shouldReplace = false;
      mutations.forEach((mutation) => {
        if (mutation.addedNodes.length > 0) {
          mutation.addedNodes.forEach((node) => {
            if (node.nodeType === 1) {
              const el = node as Element;
              if (el.getAttribute && (el.getAttribute('part') === 'button-prev' || el.getAttribute('part') === 'button-next')) {
                shouldReplace = true;
              }
              // Проверяем вложенные элементы
              if (el.querySelector && (el.querySelector('[part="button-prev"]') || el.querySelector('[part="button-next"]'))) {
                shouldReplace = true;
              }
            }
          });
        }
      });
      if (shouldReplace) {
        replaceNavigationIcons();
      }
    });

    // Наблюдаем за изменениями в shadowRoot
    observer.observe(swiperEl.shadowRoot, {
      childList: true,
      subtree: true
    });

    // Также слушаем событие инициализации swiper
    swiperEl.addEventListener('swiper', () => {
      replaceNavigationIcons();
    });

    // Дополнительные попытки замены с небольшими задержками
    setTimeout(replaceNavigationIcons, 50);
    setTimeout(replaceNavigationIcons, 100);
    setTimeout(replaceNavigationIcons, 200);
  };

  // Запускаем наблюдение сразу
  observeSwiperButtons();

  console.log('Gallery initialized successfully');
}

/**
 * Делегирование для FlyonUI overlays (HSOverlay)
 * Обеспечивает работу data-overlay на динамических элементах и после SPA‑инициализаций.
 */
function initFlyonUIModals(): void {
  try {
    // Ничего не делаем, если нет DOM
    if (typeof document === 'undefined') return;

    // Перемещаем оверлеи в <body>, чтобы избежать проблем со stacking context/overflow
    const overlaysToMove = Array.from(document.querySelectorAll<HTMLElement>('.overlay.modal'));
    overlaysToMove.forEach((el) => {
      try {
        if (el.parentElement && el.parentElement !== document.body) {
          document.body.appendChild(el);
        }
      } catch {}
    });

    // Инициализируем все overlays и триггеры
    const overlaysToInit = Array.from(document.querySelectorAll<HTMLElement>('.overlay.modal'));
    overlaysToInit.forEach((el) => {
      try {
        const HS = (window as any).HSOverlay;
        if (HS) {
          // Пробуем получить или создать инстанс
          const existing = HS.getInstance?.(el, true);
          if (!existing) {
            try { new HS(el); } catch {}
          }
        }
      } catch {}
    });

    const allOverlayTriggers = Array.from(document.querySelectorAll<HTMLElement>('[data-overlay]'));
    allOverlayTriggers.forEach((trigger) => {
      const targetSelector = trigger.getAttribute('data-overlay');
      if (!targetSelector) return;
      const targetEl = document.querySelector(targetSelector);
      if (!targetEl) return;
      try { (window as any).HSOverlay?.getInstance(targetEl, true); } catch {}
    });

    // Делегирование кликов: не мешаем другим обработчикам и не отменяем по умолчанию
    const openHandler = (e: Event) => {
      const target = e.target as HTMLElement | null;
      if (!target) return;
      const trigger = target.closest('[data-overlay]') as HTMLElement | null;
      if (!trigger) return;

      const selector = trigger.getAttribute('data-overlay');
      if (!selector) return;

      // Если клик по элементу с data-overlay произошёл ВНУТРИ самого модального окна —
      // не перехватываем (даём HSOverlay закрыть модал), чтобы избежать повторного открытия
      const overlayEl = document.querySelector(selector) as HTMLElement | null;
      if (overlayEl && overlayEl.contains(trigger)) {
        return;
      }

      // Пробуем открыть модал немедленно в capture-фазе
      try {
        const HS = (window as any).HSOverlay;
        if (HS && typeof HS.open === 'function') {
          HS.open(selector);
          return;
        }
        const inst = HS?.getInstance?.(selector, true);
        try { inst?.element?.open?.(); } catch {}
        // Фоллбек: снять hidden у целевого элемента
        try {
          const el = document.querySelector(selector) as HTMLElement | null;
          if (el) {
            el.classList.remove('hidden');
            // Закрытие по кнопкам с data-overlay="#id"
            el.addEventListener('click', (evt) => {
              const t = evt.target as HTMLElement | null;
              if (!t) return;
              const closeBtn = t.closest(`[data-overlay="${selector}"]`);
              if (closeBtn) {
                try { el.classList.add('hidden'); } catch {}
              }
            }, { once: true });
          }
        } catch {}
      } catch {}
    };
    document.addEventListener('click', openHandler, { capture: true });
    document.addEventListener('click', openHandler, { capture: false });
    document.addEventListener('pointerdown', openHandler, { capture: true });
    document.addEventListener('pointerup', openHandler, { capture: false });
  } catch (err) {
    console.warn('FlyonUI modal init warning:', err);
  }
}

/**
 * Делегирование кликов для кнопок с command="--toggle"
 * Тогглит видимость целевого el-disclosure по id из commandfor/aria-controls
 * и корректно обновляет aria-expanded на кнопке, чтобы сработали utility-классы
 * вида in-aria-expanded:hidden / not-in-aria-expanded:hidden.
 */
function initDisclosureToggles(): void {
  const buttons = Array.from(document.querySelectorAll<HTMLElement>('[command="--toggle"]'));

  buttons.forEach((button) => {
    const disclosure = resolveDisclosure(button);
    if (!disclosure) return;

    // Синхронизируем состояние сразу и повторно после возможных сторонних инициализаций
    syncDisclosureState(button, disclosure);
    requestAnimationFrame(() => {
      syncDisclosureState(button, disclosure);
    });
  });

  document.addEventListener('click', (event: MouseEvent): void => {
    const target = event.target as HTMLElement | null;
    if (!target) return;

    const toggleButton = target.closest('[command="--toggle"]') as HTMLElement | null;
    if (!toggleButton) return;

    const disclosure = resolveDisclosure(toggleButton);
    if (!disclosure) return;

    const nextExpanded = !isButtonExpanded(toggleButton);
    setDisclosureState(toggleButton, disclosure, nextExpanded);
  });
}

function resolveDisclosure(button: HTMLElement): HTMLElement | null {
  const controlsId = button.getAttribute('commandfor') || button.getAttribute('aria-controls');
  if (!controlsId) return null;

  return document.getElementById(controlsId) as HTMLElement | null;
}

function isButtonExpanded(button: HTMLElement): boolean {
  return button.getAttribute('aria-expanded') === 'true';
}

function syncDisclosureState(button: HTMLElement, disclosure: HTMLElement): void {
  const isVisible = !disclosure.hasAttribute('hidden') && !disclosure.classList.contains('hidden');
  setDisclosureState(button, disclosure, isVisible);
}

function setDisclosureState(button: HTMLElement, disclosure: HTMLElement, expanded: boolean): void {
  const current = isButtonExpanded(button);
  if (current === expanded) {
    // Даже если состояния совпадают, убедимся, что атрибуты и иконки корректны
    button.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    if (expanded) {
      disclosure.removeAttribute('hidden');
      try { disclosure.classList.remove('hidden'); } catch {}
    } else {
      if (!disclosure.hasAttribute('hidden')) {
        disclosure.setAttribute('hidden', '');
      }
      try { disclosure.classList.add('hidden'); } catch {}
    }
    syncToggleIcons(button, expanded);
    return;
  }

  button.setAttribute('aria-expanded', expanded ? 'true' : 'false');

  if (expanded) {
    disclosure.removeAttribute('hidden');
    try { disclosure.classList.remove('hidden'); } catch {}
  } else {
    disclosure.setAttribute('hidden', '');
    try { disclosure.classList.add('hidden'); } catch {}
  }

  syncToggleIcons(button, expanded);
}

// Локальный помощник: переключает видимость SVG иконок внутри кнопки
function syncToggleIcons(buttonEl: HTMLElement, expanded: boolean): void {
  try {
    // Классы Tailwind с двоеточиями нужно экранировать
    const plus = buttonEl.querySelector<SVGElement>('.in-aria-expanded\\:hidden');
    const minus = buttonEl.querySelector<SVGElement>('.not-in-aria-expanded\\:hidden');

    if (expanded) {
      // Раскрыт: показываем минус, скрываем плюс
      if (plus) { try { plus.setAttribute('hidden', ''); plus.classList.add('hidden'); } catch {} }
      if (minus) { try { minus.removeAttribute('hidden'); minus.classList.remove('hidden'); } catch {} }
    } else {
      // Свёрнут: показываем плюс, скрываем минус
      if (minus) { try { minus.setAttribute('hidden', ''); minus.classList.add('hidden'); } catch {} }
      if (plus) { try { plus.removeAttribute('hidden'); plus.classList.remove('hidden'); } catch {} }
    }
  } catch {}
}

