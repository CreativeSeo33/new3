/**
 * Типы для модуля модального окна
 */

export interface ModalOptions {
  type?: 'inline' | 'html' | 'ajax' | 'clone';
  src?: string;
  html?: string;
  filter?: string;
  width?: number;
  height?: number;
  closeOnOverlay?: boolean;
  closeOnEscape?: boolean;
  showCloseButton?: boolean;
  onOpen?: () => void;
  onClose?: () => void;
  onError?: (error: any) => void;
}

export interface ModalSlide {
  src?: string;
  html?: string;
  type?: 'inline' | 'html' | 'ajax' | 'clone';
  filter?: string;
}

export type FancyboxInstance = any; // Тип из @fancyapps/ui
