import { Controller } from '@hotwired/stimulus';
import { Fancybox } from '@fancyapps/ui';

export default class extends Controller {
  static targets = ['image', 'indicator'];
  static values = { images: Array };

  connect() {
    // Выделяем первый индикатор как активный по умолчанию
    this.setActiveIndicator(0);
  }

  showImage(event) {
    try {
      const target = event.currentTarget;
      if (!target) return;
      const indexAttr = target.dataset.productGalleryImageIndexValue;
      const index = parseInt(indexAttr || '', 10);
      const urls = this.imagesValue || [];
      if (!Number.isInteger(index) || index < 0 || index >= urls.length) return;

      const imgEl = this.imageTarget;
      if (imgEl && typeof urls[index] === 'string' && urls[index]) {
        if (imgEl.src !== urls[index]) {
          imgEl.src = urls[index];
        }
      }

      this.setActiveIndicator(index);
    } catch (_) {
      // noop: не ломаем UX на краевых случаях
    }
  }

  openFancybox() {
    try {
      const urls = Array.isArray(this.imagesValue) ? this.imagesValue : [];
      if (!urls.length) return;

      // Преобразуем URLы md/sm/md2 -> xl для увеличенных фото
      const toXl = (u) => {
        try {
          let s = String(u || '');
          s = s.replace('/media/cache/resolve/md2/', '/media/cache/resolve/xl/')
               .replace('/media/cache/resolve/md/', '/media/cache/resolve/xl/')
               .replace('/media/cache/resolve/sm/', '/media/cache/resolve/xl/')
               .replace('/media/cache/md2/', '/media/cache/xl/')
               .replace('/media/cache/md/', '/media/cache/xl/')
               .replace('/media/cache/sm/', '/media/cache/xl/');
          return s;
        } catch (_) {
          return u;
        }
      };

      const items = urls
        .filter((u) => typeof u === 'string' && u)
        .map((u) => ({
          src: toXl(u),
          caption: 'Изображение товара',
        }));

      if (!items.length) return;

      // Открываем Fancybox с настройками как на странице товара
      // (zoom, toolbar, thumbs, infinite: false), стартуем с первого слайда
      Fancybox.show(items, {
        startIndex: 0,
        Carousel: {
          infinite: false,
        },
        // @ts-ignore
        Images: {
          zoom: true,
          Panzoom: {
            maxScale: 3,
          },
        },
        Toolbar: {
          display: [
            { id: 'prev', position: 'center' },
            { id: 'counter', position: 'center' },
            { id: 'next', position: 'center' },
            'zoom',
            'fullscreen',
            'download',
            'thumbs',
            'close',
          ],
        },
        Thumbs: {
          type: 'modern',
        },
      });
    } catch (_) {
      // graceful degradation
    }
  }

  setActiveIndicator(index) {
    try {
      const items = this.indicatorTargets || [];
      if (!Array.isArray(items) || items.length === 0) return;
      items.forEach((el, i) => {
        el.classList.remove('border-yellow-500');
        el.classList.add('border-gray-300');
      });
      const active = items[index];
      if (active) {
        active.classList.remove('border-gray-300');
        active.classList.add('border-yellow-500');
      }
    } catch (_) {
      // noop
    }
  }

  navigateToProduct(event) {
    try {
      // Не мешаем модификаторам/средней кнопке и т.п.
      if (event.defaultPrevented) return;
      if (event.button !== 0) return; // только левый клик
      if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

      event.preventDefault();

      // Ищем ссылку внутри корневого элемента контроллера
      const link = this.element.querySelector('a[href]');
      if (!link) return;

      const href = link.getAttribute('href');
      if (!href) return;

      // Уважим таргет, если вдруг он есть
      const target = link.getAttribute('target');
      if (target === '_blank') {
        window.open(href, '_blank', 'noopener,noreferrer');
      } else {
        window.location.assign(href);
      }
    } catch (_) {
      // graceful
    }
  }
}


