import './styles.css';
import { Fancybox } from '@fancyapps/ui';
import '@fancyapps/ui/dist/fancybox/fancybox.css';
import { register } from 'swiper/element/bundle';
import { bootstrap } from '@app/bootstrap';

// Import Stimulus
import '../bootstrap.js';

// Регистрируем Swiper Element
register();

// Инициализация модульной системы
document.addEventListener('DOMContentLoaded', (): void => {
  // Запускаем модульную систему
  bootstrap();

  // Инициализация галереи (оставляем как есть, так как это не модуль)
  setTimeout((): void => {
    initGallery();
  }, 100);
});

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

