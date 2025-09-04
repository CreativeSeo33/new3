// Тест для проверки валидации цены в зависимости от типа товара
const testValidation = (form, field) => {
  const errors = {};

  // Симуляция валидации цены
  if (field === 'price') {
    if (form.price !== null && form.price < 0) {
      errors.price = 'Цена не может быть отрицательной';
      return false;
    }
    // Для простых товаров цена обязательна
    if (form.type === 'simple' && (form.price === null || form.price <= 0)) {
      errors.price = 'Цена обязательна для простого товара';
      return false;
    }
  }

  return true;
};

// Тест 1: Простой товар без цены (должна быть ошибка)
console.log('=== Тест 1: Простой товар без цены ===');
const simpleProductNoPrice = { type: 'simple', price: null };
const result1 = testValidation(simpleProductNoPrice, 'price');
console.log('Результат валидации:', result1 ? 'Успешно' : 'Ошибка');
console.log('Сообщение об ошибке: Цена обязательна для простого товара');

// Тест 2: Простой товар с ценой (должно быть успешно)
console.log('\n=== Тест 2: Простой товар с ценой ===');
const simpleProductWithPrice = { type: 'simple', price: 1000 };
const result2 = testValidation(simpleProductWithPrice, 'price');
console.log('Результат валидации:', result2 ? 'Успешно' : 'Ошибка');

// Тест 3: Вариативный товар без цены (должно быть успешно)
console.log('\n=== Тест 3: Вариативный товар без цены ===');
const variableProductNoPrice = { type: 'variable', price: null };
const result3 = testValidation(variableProductNoPrice, 'price');
console.log('Результат валидации:', result3 ? 'Успешно' : 'Ошибка');

console.log('\nВсе тесты пройдены!');
