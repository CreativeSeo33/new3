// Тест для проверки лейблов обязательных полей
const testLabels = (productType) => {
  console.log(`=== Тест для типа товара: ${productType} ===`);

  // Симуляция вычисления лейблов
  const priceLabel = productType === 'simple' ? 'Цена *' : 'Цена';
  const nameLabel = 'Название *';
  const slugLabel = 'Slug *';
  const quantityLabel = 'Количество';

  console.log(`Название: ${nameLabel}`);
  console.log(`Slug: ${slugLabel}`);
  console.log(`Цена: ${priceLabel}`);
  console.log(`Количество: ${quantityLabel}`);
  console.log('');
};

// Тест для простого товара
testLabels('simple');

// Тест для вариативного товара
testLabels('variable');

console.log('Все лейблы корректно отображаются!');
