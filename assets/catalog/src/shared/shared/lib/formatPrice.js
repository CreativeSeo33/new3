/**
 * Форматирует цену в рублях
 * @param {number} price - Цена в копейках или рублях
 * @param {boolean} isKopecks - Если true, то цена в копейках
 * @returns {string} Отформатированная цена
 */
export function formatPrice(price, isKopecks = false) {
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
 * @param {number} price - Цена в копейках или рублях
 * @param {boolean} isKopecks - Если true, то цена в копейках
 * @returns {string} Отформатированная цена без валюты
 */
export function formatPriceNumber(price, isKopecks = false) {
  const rubles = isKopecks ? price / 100 : price;

  return new Intl.NumberFormat('ru-RU', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 0
  }).format(rubles);
}
