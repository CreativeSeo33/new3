// Шаблон для API слоя feature модуля
// Замените FeatureName, RequestData, ResponseData на реальные типы

import { get, post, patch, del } from '@shared/api/http';
import type { FeatureNameRequest, FeatureNameResponse } from '@shared/types/api';

/**
 * Получить список элементов
 */
export async function getFeatureNames(): Promise<FeatureNameResponse[]> {
  return get<FeatureNameResponse[]>('/api/feature-names');
}

/**
 * Получить элемент по ID
 */
export async function getFeatureName(id: number): Promise<FeatureNameResponse> {
  return get<FeatureNameResponse>(`/api/feature-names/${id}`);
}

/**
 * Создать новый элемент
 */
export async function createFeatureName(data: FeatureNameRequest): Promise<FeatureNameResponse> {
  return post<FeatureNameResponse>('/api/feature-names', data);
}

/**
 * Обновить элемент
 */
export async function updateFeatureName(id: number, data: Partial<FeatureNameRequest>): Promise<FeatureNameResponse> {
  return patch<FeatureNameResponse>(`/api/feature-names/${id}`, data);
}

/**
 * Удалить элемент
 */
export async function deleteFeatureName(id: number): Promise<void> {
  return del(`/api/feature-names/${id}`);
}
