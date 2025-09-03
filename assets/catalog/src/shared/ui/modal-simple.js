/**
 * Модуль модального окна на базе Fancybox
 */

// Импортируем Fancybox
import { Fancybox } from '@fancyapps/ui';
import '@fancyapps/ui/dist/fancybox/fancybox.css';

/**
 * Класс модального окна
 */
export class Modal {
  constructor(el, opts = {}) {
    this.el = el;
    this.fancyboxInstance = null;
    this.modalOptions = {
      closeOnOverlay: true,
      closeOnEscape: true,
      showCloseButton: true,
      ...opts
    };

    this.init();
  }

  init() {
    // Добавляем обработчик клика для открытия модального окна
    this.handleClick = (e) => {
      e.preventDefault();
      this.open();
    };

    this.el.addEventListener('click', this.handleClick);

    // Добавляем класс для стилизации
    this.el.classList.add('modal-trigger');
  }

  /**
   * Открывает модальное окно
   */
  open() {
    try {
      const slides = [];

      if (this.modalOptions.type === 'html' && this.modalOptions.html) {
        // HTML контент
        slides.push({
          html: this.modalOptions.html
        });
      } else if (this.modalOptions.src) {
        // Контент из src
        const slide = {
          src: this.modalOptions.src,
          type: this.modalOptions.type || 'inline'
        };

        if (this.modalOptions.filter) {
          slide.filter = this.modalOptions.filter;
        }

        slides.push(slide);
      }

      if (slides.length === 0) {
        console.warn('Modal: No content specified');
        return;
      }

      // Настройки Fancybox
      const fancyboxOptions = {
        mainClass: 'modal-fancybox',
        on: {
          ready: () => {
            if (this.modalOptions.onOpen) {
              this.modalOptions.onOpen();
            }
          },
          destroy: () => {
            if (this.modalOptions.onClose) {
              this.modalOptions.onClose();
            }
          }
        },
        closeButton: this.modalOptions.showCloseButton !== false ? 'top' : false,
        backdropClick: this.modalOptions.closeOnOverlay !== false ? 'close' : false,
        keyboard: this.modalOptions.closeOnEscape !== false ? true : false
      };

      // Добавляем размеры если указаны
      if (this.modalOptions.width) {
        fancyboxOptions.width = this.modalOptions.width;
      }
      if (this.modalOptions.height) {
        fancyboxOptions.height = this.modalOptions.height;
      }

      // Открываем модальное окно
      this.fancyboxInstance = Fancybox.show(slides, fancyboxOptions);

    } catch (error) {
      console.error('Modal: Error opening modal', error);
      if (this.modalOptions.onError) {
        this.modalOptions.onError(error);
      }
    }
  }

  /**
   * Закрывает модальное окно
   */
  close() {
    if (this.fancyboxInstance) {
      this.fancyboxInstance.close();
      this.fancyboxInstance = null;
    }
  }

  /**
   * Проверяет, открыто ли модальное окно
   */
  isOpen() {
    return this.fancyboxInstance !== null;
  }

  /**
   * Обновляет содержимое модального окна
   */
  updateContent(newOptions) {
    this.modalOptions = { ...this.modalOptions, ...newOptions };
  }

  destroy() {
    // Закрываем модальное окно если оно открыто
    this.close();

    // Удаляем обработчик событий
    if (this.handleClick) {
      this.el.removeEventListener('click', this.handleClick);
    }

    // Удаляем класс
    this.el.classList.remove('modal-trigger');
  }
}

/**
 * Инициализирует модуль модального окна
 */
export function init(root, opts = {}) {
  const modal = new Modal(root, opts);
  return () => modal.destroy();
}
