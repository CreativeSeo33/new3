// Тест для проверки сохранения поля type
const testData = {
  name: 'Тестовый товар',
  slug: 'testovyy-tovar',
  price: 1000,
  salePrice: 800,
  status: true,
  quantity: 10,
  type: 'variable',
  description: 'Тестовое описание',
  metaTitle: 'Тестовый товар',
  sortOrder: 1,
  optionsJson: [],
  optionAssignments: []
};

console.log('Тестовые данные:', testData);
console.log('Поле type:', testData.type);
console.log('Тип товара:', testData.type === 'variable' ? 'Вариативный' : 'Простой');

// Симуляция payload для API
const payload = {
  name: testData.name || null,
  slug: testData.slug || null,
  price: testData.price,
  salePrice: testData.salePrice,
  status: testData.status ?? null,
  quantity: testData.quantity,
  sortOrder: testData.sortOrder ?? null,
  type: testData.type || null,
  description: testData.description ?? null,
  metaTitle: testData.metaTitle || null,
  optionsJson: testData.optionsJson ?? null,
};

console.log('Payload для API:', payload);
console.log('Поле type в payload:', payload.type);
console.log('Тест пройден!');
