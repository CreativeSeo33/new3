/**
 * Форматирует цену в рублях
 * @param price - Цена в копейках или рублях
 * @param isKopecks - Если true, то цена в копейках
 * @returns Отформатированная цена
 */
export function formatPrice(price: number, isKopecks = false): string {
  const rubles = isKopecks ? price / 100 : price;

  return new Intl.NumberFormat('ru-RU', {
    style: 'currency',
    currency: 'RUB',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0
  }).format(rubles);
}

/**
 * Форматирует цену для отображения без валюты
 * @param price - Цена в копейках или рублях
 * @param isKopecks - Если true, то цена в копейках
 * @returns Отформатированная цена без валюты
 */
export function formatPriceNumber(price: number, isKopecks = false): string {
  const rubles = isKopecks ? price / 100 : price;

  return new Intl.NumberFormat('ru-RU', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 0
  }).format(rubles);
}
