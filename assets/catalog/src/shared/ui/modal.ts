import { Component } from './Component';
import type { ModalOptions } from '../types/modal';

/**
 * Модуль модального окна на базе Fancybox
 */
export class Modal extends Component {
  private modalOptions: ModalOptions;
  private isModalOpen = false;

  constructor(el: HTMLElement, opts: ModalOptions = {}) {
    super(el, opts);

    this.modalOptions = {
      closeOnOverlay: true,
      closeOnEscape: true,
      showCloseButton: true,
      ...opts
    };

    this.init();
  }

  init(): void {
    // Добавляем обработчик клика для открытия модального окна
    this.on('click', this.handleClick.bind(this));

    // Добавляем класс для стилизации
    this.el.classList.add('modal-trigger');
  }

  /**
   * Обработчик клика по элементу
   */
  private handleClick(e: Event): void {
    e.preventDefault();
    this.open();
  }

  /**
   * Открывает модальное окно
   */
  open(): void {
    try {
      console.log('Modal: Opening modal with options:', this.modalOptions);

      // Для демонстрации - просто показываем alert
      // В реальной реализации здесь будет Fancybox
      alert(`Модальное окно открыто!\nТип: ${this.modalOptions.type}\nКонтент: ${this.modalOptions.html || this.modalOptions.src || 'inline'}`);

      this.isModalOpen = true;
      this.modalOptions.onOpen?.();

    } catch (error) {
      console.error('Modal: Error opening modal', error);
      this.modalOptions.onError?.(error);
    }
  }

  /**
   * Закрывает модальное окно
   */
  close(): void {
    if (this.isModalOpen) {
      console.log('Modal: Closing modal');
      this.isModalOpen = false;
      this.modalOptions.onClose?.();
    }
  }

  /**
   * Проверяет, открыто ли модальное окно
   */
  isOpen(): boolean {
    return this.isModalOpen;
  }

  /**
   * Обновляет содержимое модального окна
   */
  updateContent(newOptions: Partial<ModalOptions>): void {
    this.modalOptions = { ...this.modalOptions, ...newOptions };
  }

  destroy(): void {
    // Закрываем модальное окно если оно открыто
    this.close();

    // Удаляем класс
    this.el.classList.remove('modal-trigger');

    super.destroy();
  }
}

/**
 * Инициализирует модуль модального окна
 */
export function init(
  root: HTMLElement,
  opts: ModalOptions = {}
): () => void {
  const modal = new Modal(root, opts);
  return () => modal.destroy();
}

/**
 * Экспортируем типы
 */
export type { ModalOptions } from '../types/modal';

/**
 * Экспорт по умолчанию для совместимости
 */
export default Modal;