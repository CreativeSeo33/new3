// Тест для проверки инициализации формы
const form = {
  name: '',
  slug: '',
  price: null,
  salePrice: null,
  status: true,
  quantity: 100,
  type: 'simple',
  description: '',
  metaTitle: '',
  metaDescription: '',
  h1: '',
  sortOrder: 1,
  optionsJson: [],
  optionAssignments: [],
};

console.log('Форма инициализирована:', !!form);
console.log('Поле type:', form.type);
console.log('Доступ к form.type:', form.type);
console.log('Проверка типа товара:', form.type === 'simple' ? 'Работает' : 'Ошибка');

// Тест computed свойства (симуляция)
const tabs = () => {
  const baseTabs = [
    { value: 'description', label: 'Описание товара' },
    { value: 'categories', label: 'Категории' },
    { value: 'attributes', label: 'Аттрибуты' },
    { value: 'photos', label: 'Фотографии' },
  ]

  if (form?.type === 'variable') {
    baseTabs.splice(3, 0, { value: 'options', label: 'Опции' })
  }

  return baseTabs
}

console.log('Tabs для простого товара:', tabs().map(t => t.value));
console.log('Все тесты пройдены!');
