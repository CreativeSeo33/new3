// Шаблон для создания типов API
// Замените FeatureName на реальное название

/**
 * Запрос на создание/обновление элемента
 */
export interface FeatureNameRequest {
  name: string;
  description?: string;
  categoryId?: number;
  isActive?: boolean;
  metadata?: Record<string, any>;
}

/**
 * Ответ API с данными элемента
 */
export interface FeatureNameResponse {
  id: number;
  name: string;
  description?: string;
  categoryId?: number;
  isActive: boolean;
  createdAt: string;
  updatedAt: string;
  metadata?: Record<string, any>;
}

/**
 * Ответ API со списком элементов
 */
export interface FeatureNameListResponse {
  data: FeatureNameResponse[];
  total: number;
  page: number;
  limit: number;
}

/**
 * Параметры запроса для списка
 */
export interface FeatureNameListParams {
  page?: number;
  limit?: number;
  search?: string;
  categoryId?: number;
  isActive?: boolean;
  sortBy?: string;
  sortOrder?: 'asc' | 'desc';
}

/**
 * Статистика по элементам
 */
export interface FeatureNameStats {
  total: number;
  active: number;
  inactive: number;
  byCategory: Record<number, number>;
}

/**
 * Событие создания элемента
 */
export interface FeatureNameCreatedEvent {
  type: 'created';
  data: FeatureNameResponse;
}

/**
 * Событие обновления элемента
 */
export interface FeatureNameUpdatedEvent {
  type: 'updated';
  data: FeatureNameResponse;
  previousData: FeatureNameResponse;
}

/**
 * Событие удаления элемента
 */
export interface FeatureNameDeletedEvent {
  type: 'deleted';
  data: { id: number };
}

// Union тип для всех событий
export type FeatureNameEvent =
  | FeatureNameCreatedEvent
  | FeatureNameUpdatedEvent
  | FeatureNameDeletedEvent;
