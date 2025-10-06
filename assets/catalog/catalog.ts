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
import { bootstrap } from '@app/bootstrap';

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
  const thumbnails = document.querySelectorAll('.thumbnail-item');

  if (!mainSwiperEl) return;

  // Функция для обновления активного состояния миниатюр
  const updateActiveThumbnail = (activeThumbnail) => {
    thumbnails.forEach(thumb => {
      thumb.classList.remove('active');
    });

    if (activeThumbnail) {
      activeThumbnail.classList.add('active');
    }
  };

  // Обработчик клика по слайдам (открывает Fancybox)
  const handleSlideClick = (e) => {
    e.preventDefault();

    const slide = e.target.closest('.gallery-slide');
    if (!slide) return;

    // Собираем все изображения для галереи
    const allImages = [];
    const slides = document.querySelectorAll('.gallery-slide');

    slides.forEach(slide => {
      if (slide.dataset.imageSrc) {
        allImages.push({
          src: slide.dataset.imageSrc,
          caption: slide.dataset.caption || 'Изображение товара'
        });
      }
    });

    // Находим индекс текущего слайда
    const currentIndex = Array.from(slides).indexOf(slide);

    // Открываем Fancybox с текущим изображением
    try {
      Fancybox.show(allImages, {
        startIndex: currentIndex,
        Carousel: {
          infinite: false,
        },
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
  const handleThumbnailClick = (e) => {
    e.preventDefault();

    const thumbnail = e.currentTarget;
    const imageSrc = thumbnail.dataset.imageSrc;

    if (!imageSrc) return;

    // Находим индекс миниатюры для Swiper
    const thumbsArray = Array.from(thumbnails);
    const thumbnailIndex = thumbsArray.indexOf(thumbnail);

    // Переключаем Swiper на соответствующий слайд
    if (mainSwiperEl.swiper) {
      mainSwiperEl.swiper.slideTo(thumbnailIndex);
    }

    // Обновляем активное состояние миниатюр
    updateActiveThumbnail(thumbnail);
  };

  // Добавляем обработчики событий
  const gallerySlides = document.querySelectorAll('.gallery-slide');
  gallerySlides.forEach(slide => {
    slide.addEventListener('click', handleSlideClick);
  });

  thumbnails.forEach(thumb => {
    thumb.addEventListener('click', handleThumbnailClick);
  });

  // Синхронизация с Swiper
  mainSwiperEl.addEventListener('swiperslidechange', (event) => {
    const activeIndex = event.detail[0].activeIndex;
    const activeThumbnail = thumbnails[activeIndex];

    if (activeThumbnail) {
      updateActiveThumbnail(activeThumbnail);
    }
  });

  // Устанавливаем первую миниатюру активной по умолчанию
  if (thumbnails.length > 0) {
    updateActiveThumbnail(thumbnails[0]);
  }

  console.log('Gallery initialized successfully');
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

